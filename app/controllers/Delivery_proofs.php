<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

class Delivery_proofs extends Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->call->helper('auth');
        require_role(['customer', 'dispatcher', 'admin', 'rider']);
        $this->call->library('DeliveryProofService');
    }

    public function show(int $orderId): void
    {
        $db = $this->db;
        $role = auth_role();
        if ($role === 'customer') {
            $customerId = (int)(auth_user()['customer_id'] ?? 0);
            $authorized = $customerId > 0 && (bool)$db->raw(
                'SELECT id FROM orders WHERE id = ? AND customer_id = ? AND deleted_at IS NULL LIMIT 1',
                [$orderId, $customerId]
            )->fetch(PDO::FETCH_ASSOC);
        } elseif ($role === 'rider') {
            $authorized = (bool)$db->raw(
                "SELECT dp.id FROM delivery_proofs dp
                 WHERE dp.order_id = ? AND dp.uploaded_by = ?
                   AND EXISTS (
                       SELECT 1 FROM deliveries d
                       WHERE d.order_id = dp.order_id AND d.actor_type = 'rider'
                         AND d.actor_id = dp.uploaded_by AND d.to_status IN ('picked_up', 'delivered')
                   )
                 LIMIT 1",
                [$orderId, (int)auth_id()]
            )->fetch(PDO::FETCH_ASSOC);
        } else {
            $authorized = (bool)$db->raw(
                'SELECT id FROM orders WHERE id = ? AND deleted_at IS NULL LIMIT 1',
                [$orderId]
            )->fetch(PDO::FETCH_ASSOC);
        }

        if (!$authorized) {
            http_response_code(404);
            return;
        }

        $service = new DeliveryProofService($db);
        $proof = $service->getForOrder($orderId);
        $path = $proof ? $service->readFile($proof) : null;
        if (!$proof || !$path) {
            http_response_code(404);
            return;
        }

        header('Content-Type: ' . $proof['mime_type']);
        header('Content-Length: ' . (string)filesize($path));
        header('Content-Disposition: inline; filename="delivery-proof-' . (int)$proof['id'] . '.' . pathinfo($proof['storage_key'], PATHINFO_EXTENSION) . '"');
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: private, no-store, max-age=0');
        readfile($path);
    }
}
