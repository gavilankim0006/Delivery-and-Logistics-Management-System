<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

class DeliveryProofService
{
    private $db;
    private string $storageDir;

    public function __construct($db = null, ?string $storageDir = null)
    {
        $lava = function_exists('lava_instance') ? lava_instance() : null;
        $this->db = $db ?? ($lava ? $lava->db : null);
        $this->storageDir = $storageDir ?? ROOT_DIR . 'runtime/private/delivery-proofs';
    }

    public function completeWithProof(int $orderId, int $riderUserId, string $recipientName, string $note, array $upload, $orderModel): array
    {
        $recipientName = trim($recipientName);
        $note = trim($note);
        if ($orderId < 1 || $riderUserId < 1 || $recipientName === '' || mb_strlen($recipientName) > 100 || mb_strlen($note) > 500) {
            throw new InvalidArgumentException('Enter the recipient name and keep the note within 500 characters.');
        }
        if (($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || empty($upload['tmp_name']) || !is_uploaded_file($upload['tmp_name'])) {
            throw new InvalidArgumentException('Choose a valid proof-of-delivery photo.');
        }
        $size = (int)($upload['size'] ?? 0);
        if ($size < 1 || $size > 5 * 1024 * 1024) {
            throw new InvalidArgumentException('The proof photo must be 5 MB or smaller.');
        }
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($upload['tmp_name']);
        $allowedTypes = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
        if (!isset($allowedTypes[$mime])) {
            throw new InvalidArgumentException('Upload a JPEG, PNG, or WebP image.');
        }
        $image = @getimagesize($upload['tmp_name']);
        if (!$image || $image['mime'] !== $mime || $image[0] < 1 || $image[1] < 1
            || $image[0] > 6000 || $image[1] > 6000 || ($image[0] * $image[1]) > 25000000) {
            throw new InvalidArgumentException('The uploaded file is not a supported image.');
        }

        if (!is_dir($this->storageDir) && !@mkdir($this->storageDir, 0700, true) && !is_dir($this->storageDir)) {
            throw new RuntimeException('Private proof storage is unavailable.');
        }
        @chmod($this->storageDir, 0700);
        $storageKey = bin2hex(random_bytes(32)) . '.' . $allowedTypes[$mime];
        $target = $this->storageDir . DIRECTORY_SEPARATOR . $storageKey;
        $db = $this->db;
        $moved = false;

        $db->transaction();
        try {
            $order = $db->raw(
                'SELECT o.id, o.status, o.fulfillment_mode, o.rider_id
                 FROM orders o WHERE o.id = ? AND o.deleted_at IS NULL FOR UPDATE',
                [$orderId]
            )->fetch(PDO::FETCH_ASSOC);
            if (!$order || $order['status'] !== 'picked_up' || $order['fulfillment_mode'] !== 'internal') {
                throw new DomainException('Proof can only be recorded for an active internal delivery that has been picked up.');
            }
            $assignment = $db->raw(
                "SELECT ra.id FROM rider_assignments ra
                 INNER JOIN riders r ON r.id = ra.rider_id
                 INNER JOIN users u ON u.id = r.user_id
                 INNER JOIN roles ro ON ro.id = u.role_id AND ro.name = 'rider'
                 WHERE ra.order_id = ? AND ra.rider_id = ? AND ra.released_at IS NULL
                   AND r.user_id = ? AND r.deleted_at IS NULL AND u.status = 'active' AND u.deleted_at IS NULL
                 LIMIT 1 FOR UPDATE",
                [$orderId, (int)$order['rider_id'], $riderUserId]
            )->fetch(PDO::FETCH_ASSOC);
            if (!$assignment) {
                throw new DomainException('Only the assigned rider can complete this delivery.');
            }
            $existing = $db->raw('SELECT id FROM delivery_proofs WHERE order_id = ? LIMIT 1 FOR UPDATE', [$orderId])->fetch(PDO::FETCH_ASSOC);
            if ($existing) {
                throw new DomainException('A proof photo has already been submitted for this delivery.');
            }
            if (!move_uploaded_file($upload['tmp_name'], $target)) {
                throw new RuntimeException('The proof photo could not be stored securely.');
            }
            $moved = true;
            @chmod($target, 0600);
            $now = date('Y-m-d H:i:s');
            $db->table('delivery_proofs')->insert([
                'order_id' => $orderId,
                'uploaded_by' => $riderUserId,
                'recipient_name' => $recipientName,
                'note' => $note !== '' ? $note : null,
                'storage_key' => $storageKey,
                'mime_type' => $mime,
                'file_size' => $size,
                'sha256' => hash_file('sha256', $target),
                'captured_at' => $now,
                'created_at' => $now,
            ]);
            $proofId = (int)$db->last_id();
            $orderModel->set_status($orderId, 'delivered', 'Proof of delivery recorded for ' . $recipientName . '.', 'rider', $riderUserId);
            $db->commit();
            try {
                require_once APP_DIR . 'libraries/DeliveryNotificationService.php';
                (new DeliveryNotificationService($db))->dispatchPendingForOrder($orderId);
            } catch (Throwable $notificationError) {
                error_log('Delivery completion notification failed for order ' . $orderId . ': ' . $notificationError->getMessage());
            }
            return ['proof_id' => $proofId, 'order_id' => $orderId];
        } catch (Throwable $e) {
            $db->roll_back();
            if ($moved && is_file($target)) {
                @unlink($target);
            }
            throw $e;
        }
    }

    public function getForOrder(int $orderId): ?array
    {
        $stmt = $this->db->raw(
            'SELECT id, order_id, recipient_name, note, storage_key, mime_type, file_size, sha256, captured_at
             FROM delivery_proofs WHERE order_id = ? LIMIT 1',
            [$orderId]
        );
        $row = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : false;
        return $row ?: null;
    }

    public function readFile(array $proof): ?string
    {
        if (!preg_match('/^[a-f0-9]{64}\\.(jpg|png|webp)$/', (string)($proof['storage_key'] ?? ''))) {
            return null;
        }
        $path = $this->storageDir . DIRECTORY_SEPARATOR . $proof['storage_key'];
        return is_file($path) && is_readable($path) ? $path : null;
    }
}
