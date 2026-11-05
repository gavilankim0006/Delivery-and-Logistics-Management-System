<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

require_once APP_DIR . 'libraries/SemaphoreService.php';
require_once APP_DIR . 'libraries/FirebasePushService.php';
require_once APP_DIR . 'libraries/EmailService.php';

class DeliveryNotificationService
{
    private $db;
    private ?SemaphoreService $sms;
    private ?FirebasePushService $push;
    private ?EmailService $email;

    public function __construct($db = null, ?SemaphoreService $sms = null, ?FirebasePushService $push = null, ?EmailService $email = null)
    {
        $lava = function_exists('lava_instance') ? lava_instance() : null;
        $this->db = $db ?? ($lava ? $lava->db : null);
        $this->sms = $sms ?? new SemaphoreService();
        $this->push = $push ?? new FirebasePushService();
        $this->email = $email ?? new EmailService();
    }

    /**
     * Enqueue delivery-status notifications for all opted-in channels (SMS + push).
     */
    public function enqueueForTransition(int $orderId, string $status): bool
    {
        $messages = [
            'rider_assigned' => 'A rider has been assigned to delivery order %s.',
            'picked_up' => 'Your delivery order %s has been picked up.',
            'delivered' => 'Your delivery order %s has been delivered.',
            'cancelled' => 'Your delivery order %s has been cancelled.',
            'failed' => 'There is an issue with delivery order %s. Please contact support.',
        ];
        if (!isset($messages[$status])) {
            return false;
        }
        $row = $this->db->raw(
            'SELECT o.id, o.order_no, c.user_id, u.name, u.email, u.phone, u.fcm_token,
                    u.delivery_status_sms_opt_in, u.push_opt_in, u.email_notifications_opt_in
             FROM orders o INNER JOIN customers c ON c.id = o.customer_id
             INNER JOIN users u ON u.id = c.user_id
             WHERE o.id = ? LIMIT 1',
            [$orderId]
        )->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return false;
        }

        $message = sprintf($messages[$status], $row['order_no']);
        $enqueued = false;
        $now = date('Y-m-d H:i:s');

        // Enqueue SMS if opted in
        if ((int)($row['delivery_status_sms_opt_in'] ?? 0) === 1 && trim((string)($row['phone'] ?? '')) !== '') {
            $this->db->raw(
                "INSERT IGNORE INTO delivery_notification_outbox
                 (order_id, user_id, status_code, channel, recipient, message, status, created_at)
                 VALUES (?, ?, ?, 'sms', ?, ?, 'pending', ?)",
                [$orderId, (int)$row['user_id'], $status, $row['phone'], $message, $now]
            );
            $enqueued = true;
        }

        // Enqueue push if opted in and FCM token present
        if ((int)($row['push_opt_in'] ?? 0) === 1 && trim((string)($row['fcm_token'] ?? '')) !== '') {
            $this->db->raw(
                "INSERT IGNORE INTO delivery_notification_outbox
                 (order_id, user_id, status_code, channel, recipient, message, status, created_at)
                 VALUES (?, ?, ?, 'push', ?, ?, 'pending', ?)",
                [$orderId, (int)$row['user_id'], $status, $row['fcm_token'], $message, $now]
            );
            $enqueued = true;
        }

        // Enqueue email if recipient email present and opted in (defaults to enabled if SMS opted in or email opt-in active)
        $emailOptIn = isset($row['email_notifications_opt_in'])
            ? (int)$row['email_notifications_opt_in'] === 1
            : ((int)($row['delivery_status_sms_opt_in'] ?? 0) === 1);

        if ($emailOptIn && trim((string)($row['email'] ?? '')) !== '' && filter_var($row['email'], FILTER_VALIDATE_EMAIL)) {
            $this->db->raw(
                "INSERT IGNORE INTO delivery_notification_outbox
                 (order_id, user_id, status_code, channel, recipient, message, status, created_at)
                 VALUES (?, ?, ?, 'email', ?, ?, 'pending', ?)",
                [$orderId, (int)$row['user_id'], $status, trim($row['email']), $message, $now]
            );
            $enqueued = true;
        }

        return $enqueued;
    }

    /**
     * Dispatch all pending notifications for an order (SMS + push + email).
     */
    public function dispatchPendingForOrder(int $orderId): void
    {
        $smsEnabled = getenv('SEMAPHORE_ENABLED') === '1';
        $pushEnabled = $this->push->isEnabled();
        $emailEnabled = $this->email !== null && $this->email->isEnabled();
        if (!$smsEnabled && !$pushEnabled && !$emailEnabled) {
            return;
        }

        $now = date('Y-m-d H:i:s');
        // Expire stale claims across all channels
        $this->db->raw(
            "UPDATE delivery_notification_outbox
             SET status = 'failed', provider_response = 'Dispatch claim expired; review before retry.', claimed_at = NULL, sent_at = NULL
             WHERE order_id = ? AND status = 'sending' AND claimed_at IS NOT NULL
               AND claimed_at < DATE_SUB(?, INTERVAL 5 MINUTE)",
            [$orderId, $now]
        );

        // Build channel filter
        $channels = [];
        if ($smsEnabled) $channels[] = "'sms'";
        if ($pushEnabled) $channels[] = "'push'";
        if ($emailEnabled) $channels[] = "'email'";
        if (empty($channels)) {
            return;
        }
        $channelIn = implode(',', $channels);

        $stmt = $this->db->raw(
            "SELECT id, channel, status_code, recipient, message FROM delivery_notification_outbox
             WHERE order_id = ? AND status = 'pending' AND channel IN ({$channelIn}) ORDER BY id ASC LIMIT 20",
            [$orderId]
        );
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $channel = $row['channel'] ?? 'sms';
            if ($channel === 'sms' && !$smsEnabled) {
                continue;
            }
            if ($channel === 'push' && !$pushEnabled) {
                continue;
            }
            if ($channel === 'email' && !$emailEnabled) {
                continue;
            }

            $claimToken = bin2hex(random_bytes(16));
            $claimed = $this->db->raw(
                "UPDATE delivery_notification_outbox
                 SET status = 'sending', provider_response = ?, claimed_at = ?, sent_at = NULL
                 WHERE id = ? AND status = 'pending'",
                [$claimToken, $now, (int)$row['id']]
            );
            if ($claimed === false || $claimed->rowCount() !== 1) {
                continue;
            }
            try {
                if ($channel === 'push') {
                    $response = $this->push->sendToToken(
                        (string)$row['recipient'],
                        'Delivery Update',
                        (string)$row['message']
                    );
                    if (!empty($response['invalid_token'])) {
                        $this->clearStaleToken((string)$row['recipient']);
                    }
                    $sent = !empty($response['sent']);
                    $finalStatus = $sent ? 'sent' : 'failed';
                    $providerResponse = json_encode($response['response'] ?? $response, JSON_UNESCAPED_SLASHES);
                } elseif ($channel === 'email') {
                    $orderRow = [];
                    try {
                        $orderStmt = $this->db->raw(
                            'SELECT o.id, o.order_no, o.status,
                                    pa.address_line AS pickup_address, da.address_line AS delivery_address,
                                    r.name AS rider_name, r.phone AS rider_phone, u.name AS customer_name
                             FROM orders o
                             INNER JOIN customers c ON c.id = o.customer_id
                             INNER JOIN users u ON u.id = c.user_id
                             LEFT JOIN addresses pa ON pa.id = o.pickup_address_id
                             LEFT JOIN addresses da ON da.id = o.dropoff_address_id
                             LEFT JOIN deliveries d ON d.order_id = o.id
                             LEFT JOIN riders r ON r.id = d.actor_id AND d.actor_type = \'rider\'
                             WHERE o.id = ? ORDER BY d.id DESC LIMIT 1',
                            [$orderId]
                        );
                        $orderRow = $orderStmt ? ($orderStmt->fetch(PDO::FETCH_ASSOC) ?: []) : [];
                    } catch (Throwable $ignore) {
                        $orderRow = [];
                    }

                    $customerName = (string)($orderRow['customer_name'] ?? 'Valued Customer');
                    $orderNo = (string)($orderRow['order_no'] ?? ('#' . $orderId));
                    $statusCode = (string)($row['status_code'] ?? 'update');

                    $response = $this->email->sendDeliveryStatusNotification(
                        (string)$row['recipient'],
                        $customerName,
                        $orderNo,
                        $statusCode,
                        (string)$row['message'],
                        [
                            'order_id' => $orderId,
                            'pickup_address' => $orderRow['pickup_address'] ?? '',
                            'delivery_address' => $orderRow['delivery_address'] ?? '',
                            'rider_name' => $orderRow['rider_name'] ?? '',
                            'rider_phone' => $orderRow['rider_phone'] ?? '',
                        ]
                    );
                    $sent = !empty($response['success']);
                    $finalStatus = $sent ? 'sent' : 'failed';
                    $providerResponse = !empty($response['success']) ? ($response['message'] ?? 'Email sent.') : ($response['error'] ?? 'Email failed.');
                } else {
                    $response = $this->sms->send((string)$row['recipient'], (string)$row['message']);
                    $sent = !empty($response['sent']);
                    $finalStatus = $sent ? 'sent' : 'failed';
                    $providerResponse = json_encode($response['response'] ?? $response, JSON_UNESCAPED_SLASHES);
                }
            } catch (Throwable $e) {
                $finalStatus = 'failed';
                $providerResponse = $e->getMessage();
            }
            $this->db->raw(
                'UPDATE delivery_notification_outbox SET status = ?, provider_response = ?, claimed_at = NULL, sent_at = ? WHERE id = ? AND status = \'sending\' AND provider_response = ?',
                [$finalStatus, $providerResponse, date('Y-m-d H:i:s'), (int)$row['id'], $claimToken]
            );
        }
    }

    /**
     * Clear a stale FCM token from the users table so it isn't retried.
     */
    private function clearStaleToken(string $fcmToken): void
    {
        $this->db->raw(
            "UPDATE users SET fcm_token = NULL, updated_at = ? WHERE fcm_token = ?",
            [date('Y-m-d H:i:s'), $fcmToken]
        );
    }
}
