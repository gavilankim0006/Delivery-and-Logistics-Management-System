<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

/**
 * Order Tracking Service
 * Customer-managed public tracking links for delivery orders.
 *
 * Links are stored as a SHA-256 hash for validation plus an APP_KEY-encrypted
 * copy so the owning customer can re-display and copy the link until it is
 * revoked or expires. DB-only leakage does not expose usable links.
 */
class OrderTrackingService
{
    private const LINK_TTL_DAYS = 30;
    private const ISSUABLE_STATUSES = ['paid', 'booking', 'rider_assigned', 'picked_up', 'delivered'];

    private $db;

    public function __construct($db = null)
    {
        $lava = function_exists('lava_instance') ? lava_instance() : null;
        $this->db = $db ?? ($lava ? $lava->db : null);
    }

    public function issue(int $orderId, int $customerId): string
    {
        $token = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
        $hash = hash('sha256', $token);
        $cipher = $this->encryptToken($token);
        $now = date('Y-m-d H:i:s');
        $expiresAt = date('Y-m-d H:i:s', time() + self::LINK_TTL_DAYS * 86400);

        $this->db->transaction();
        try {
            $order = $this->db->raw(
                'SELECT id, status FROM orders WHERE id = ? AND customer_id = ? AND deleted_at IS NULL FOR UPDATE',
                [$orderId, $customerId]
            )->fetch(PDO::FETCH_ASSOC);
            if (!$order) {
                throw new InvalidArgumentException('Order not found.');
            }
            if (!in_array($order['status'], self::ISSUABLE_STATUSES, true)) {
                throw new DomainException('A tracking link is available once an order is paid and before it is cancelled.');
            }
            $this->db->raw('UPDATE order_tracking_links SET revoked_at = ? WHERE order_id = ? AND revoked_at IS NULL', [$now, $orderId]);
            $this->db->raw(
                'INSERT INTO order_tracking_links (order_id, token_hash, token_cipher, expires_at, created_at) VALUES (?, ?, ?, ?, ?)',
                [$orderId, $hash, $cipher, $expiresAt, $now]
            );
            $this->db->commit();
        } catch (Throwable $e) {
            $this->db->roll_back();
            throw $e;
        }

        return $token;
    }

    public function hasActiveLink(int $orderId, int $customerId): bool
    {
        return $this->activeLink($orderId, $customerId) !== null;
    }

    /** Returns the active link row for the owning customer, if any. */
    public function activeLink(int $orderId, int $customerId): ?array
    {
        $row = $this->db->raw(
            'SELECT l.id, l.token_cipher, l.expires_at
             FROM order_tracking_links l
             INNER JOIN orders o ON o.id = l.order_id
             WHERE l.order_id = ? AND o.customer_id = ? AND o.deleted_at IS NULL
               AND l.revoked_at IS NULL AND l.expires_at > ?
             ORDER BY l.id DESC LIMIT 1',
            [$orderId, $customerId, date('Y-m-d H:i:s')]
        )->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /** Returns the reusable share URL for the active link, or null when unavailable. */
    public function activeLinkUrl(int $orderId, int $customerId): ?string
    {
        $link = $this->activeLink($orderId, $customerId);
        if (!$link || empty($link['token_cipher'])) {
            return null;
        }
        $token = $this->decryptToken((string)$link['token_cipher']);
        if ($token === null) {
            return null;
        }
        return base_url('track/' . rawurlencode($token));
    }

    public function revoke(int $orderId, int $customerId): bool
    {
        $owned = $this->db->raw(
            'SELECT id FROM orders WHERE id = ? AND customer_id = ? AND deleted_at IS NULL LIMIT 1',
            [$orderId, $customerId]
        )->fetch(PDO::FETCH_ASSOC);
        if (!$owned) {
            return false;
        }
        $this->db->raw(
            'UPDATE order_tracking_links SET revoked_at = ? WHERE order_id = ? AND revoked_at IS NULL',
            [date('Y-m-d H:i:s'), $orderId]
        );
        return true;
    }

    /**
     * Resolve a public tracking token into limited, shareable delivery data.
     * Returns null for invalid, revoked, expired, or rate-limited tokens.
     */
    public function publicTrack(string $token): ?array
    {
        if (!preg_match('/^[A-Za-z0-9_-]{40,50}$/', $token) || !$this->allowPublicRead($token)) {
            return null;
        }
        $hash = hash('sha256', $token);
        $order = $this->db->raw(
            "SELECT o.id, o.order_no, o.status, o.fulfillment_mode, o.distance_km, o.estimated_duration_min,
                    o.created_at, o.updated_at,
                    pa.address_line AS pickup_address, da.address_line AS dropoff_address,
                    p.description AS parcel_description,
                    r.name AS rider_name
             FROM order_tracking_links l
             INNER JOIN orders o ON o.id = l.order_id
             INNER JOIN addresses pa ON pa.id = o.pickup_address_id
             INNER JOIN addresses da ON da.id = o.dropoff_address_id
             LEFT JOIN parcels p ON p.order_id = o.id
             LEFT JOIN riders r ON r.id = o.rider_id
             WHERE l.token_hash = ? AND l.revoked_at IS NULL AND l.expires_at > ? AND o.deleted_at IS NULL
             LIMIT 1",
            [$hash, date('Y-m-d H:i:s')]
        )->fetch(PDO::FETCH_ASSOC);
        if (!$order) {
            return null;
        }

        $order['timeline'] = $this->db->raw(
            'SELECT to_status, created_at FROM deliveries WHERE order_id = ? ORDER BY id ASC',
            [(int)$order['id']]
        )->fetchAll(PDO::FETCH_ASSOC) ?: [];

        // Estimated arrival: pickup time plus the booked duration estimate.
        $order['eta_at'] = null;
        if ($order['status'] === 'picked_up') {
            $pickedUpAt = null;
            foreach ($order['timeline'] as $entry) {
                if ($entry['to_status'] === 'picked_up') {
                    $pickedUpAt = $entry['created_at'];
                }
            }
            $durationMin = (int)($order['estimated_duration_min'] ?? 0);
            if ($pickedUpAt && $durationMin > 0) {
                $order['eta_at'] = date('Y-m-d H:i:s', strtotime($pickedUpAt) + $durationMin * 60);
            }
        }

        // First name only keeps the public page useful without exposing full rider identity.
        if (!empty($order['rider_name'])) {
            $order['rider_name'] = explode(' ', trim((string)$order['rider_name']))[0];
        }

        return $order;
    }

    private function encryptToken(string $token): ?string
    {
        try {
            $this->loadEncryption();
            $encrypted = (new Encryption())->encrypt($token);
            return $encrypted !== false ? (string)$encrypted : null;
        } catch (Throwable $e) {
            error_log('Tracking link token encryption unavailable: ' . $e->getMessage());
            return null;
        }
    }

    private function decryptToken(string $cipher): ?string
    {
        try {
            $this->loadEncryption();
            $token = (new Encryption())->decrypt($cipher);
            return is_string($token) && $token !== '' ? $token : null;
        } catch (Throwable $e) {
            error_log('Tracking link token decryption failed: ' . $e->getMessage());
            return null;
        }
    }

    private function loadEncryption(): void
    {
        if (!class_exists('Encryption')) {
            require_once SYSTEM_DIR . 'libraries/Encryption.php';
        }
    }

    /**
     * File-backed per-token rate limiter for public reads (mirrors DeliveryControlService).
     */
    private function allowPublicRead(string $token): bool
    {
        try {
            $key = hash('sha256', $token);
            $path = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'order_tracking_read_' . substr($key, 0, 32);
            $handle = @fopen($path, 'c+');
            if (!$handle) {
                return false;
            }
            try {
                if (!flock($handle, LOCK_EX)) {
                    return false;
                }
                $state = json_decode(stream_get_contents($handle), true);
                $window = (int)floor(time() / 300);
                $count = isset($state['window'], $state['count']) && (int)$state['window'] === $window ? (int)$state['count'] : 0;
                if ($count >= 120) {
                    return false;
                }
                rewind($handle);
                ftruncate($handle, 0);
                fwrite($handle, json_encode(['window' => $window, 'count' => $count + 1]));
                return true;
            } finally {
                flock($handle, LOCK_UN);
                fclose($handle);
            }
        } catch (Throwable $e) {
            return false;
        }
    }
}
