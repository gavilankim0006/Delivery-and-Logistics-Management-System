<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

class Rider extends Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->call->helper('auth');
        require_role('rider');
        $this->call->model('Rider_model');
        $this->call->model('Order_model');
        $this->call->library('DispatchFulfillmentService');
        $this->call->library('DeliveryProofService');
    }

    public function index(): void
    {
        $rider = $this->Rider_model->find_for_user((int)auth_id());
        $orders = $rider ? $this->Rider_model->get_active_orders((int)auth_id()) : [];
        $this->call->view('rider/index', [
            'page_title' => 'My deliveries',
            'orders' => $orders,
            'portal_linked' => $rider !== null,
        ]);
    }

    public function show(int $id): void
    {
        $order = $this->Rider_model->get_delivery_for_user($id, (int)auth_id());
        if (!$order) {
            http_response_code(404);
            $this->call->view('rider/not_found', ['page_title' => 'Delivery unavailable']);
            return;
        }

        $proof = (new DeliveryProofService($this->db))->getForOrder($id);
        $this->call->view('rider/show', [
            'page_title' => 'Delivery ' . $order['order_no'],
            'order' => $order,
            'proof_recorded' => $proof !== null,
        ]);
    }

    public function location(int $id): void
    {
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store, max-age=0');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verify_csrf()) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid request or security token.']);
            return;
        }

        $payload = json_decode((string)file_get_contents('php://input'), true);
        if (!is_array($payload)) $payload = $_POST;
        $userId = (int)auth_id();
        $action = (string)($payload['action'] ?? 'update');
        $owned = $this->db->raw(
            "SELECT o.id FROM orders o
             INNER JOIN riders r ON r.id = o.rider_id AND r.user_id = ?
             INNER JOIN rider_assignments ra ON ra.order_id = o.id AND ra.rider_id = r.id AND ra.released_at IS NULL
             INNER JOIN users u ON u.id = r.user_id AND u.status = 'active' AND u.deleted_at IS NULL
             INNER JOIN roles ro ON ro.id = u.role_id AND ro.name = 'rider'
             WHERE o.id = ? AND o.fulfillment_mode = 'internal' AND o.status IN ('rider_assigned', 'picked_up')
               AND o.deleted_at IS NULL AND r.deleted_at IS NULL
             LIMIT 1",
            [$userId, $id]
        )->fetch(PDO::FETCH_ASSOC);
        if (!$owned) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Active assignment not found.']);
            return;
        }

        if ($action === 'stop') {
            $this->db->raw(
                'INSERT INTO rider_order_locations (order_id, sharing_enabled, updated_at) VALUES (?, 0, ?) ON DUPLICATE KEY UPDATE sharing_enabled = 0, latitude = NULL, longitude = NULL, accuracy_m = NULL, received_at = NULL, updated_at = VALUES(updated_at)',
                [$id, date('Y-m-d H:i:s')]
            );
            echo json_encode(['success' => true, 'sharing' => false]);
            return;
        }

        $latitude = filter_var($payload['latitude'] ?? null, FILTER_VALIDATE_FLOAT);
        $longitude = filter_var($payload['longitude'] ?? null, FILTER_VALIDATE_FLOAT);
        $accuracy = filter_var($payload['accuracy_m'] ?? null, FILTER_VALIDATE_FLOAT);
        if ($latitude === false || $longitude === false || $accuracy === false
            || $latitude < -90 || $latitude > 90 || $longitude < -180 || $longitude > 180
            || $accuracy < 0 || $accuracy > 1000) {
            http_response_code(422);
            echo json_encode(['success' => false, 'error' => 'Location coordinates or accuracy are invalid.']);
            return;
        }
        $now = date('Y-m-d H:i:s');
        $this->db->transaction();
        try {
            $active = $this->db->raw(
                "SELECT loc.received_at FROM orders o
                 INNER JOIN riders r ON r.id = o.rider_id AND r.user_id = ?
                 INNER JOIN rider_assignments ra ON ra.order_id = o.id AND ra.rider_id = r.id AND ra.released_at IS NULL
                 INNER JOIN users u ON u.id = r.user_id AND u.status = 'active' AND u.deleted_at IS NULL
                 INNER JOIN roles ro ON ro.id = u.role_id AND ro.name = 'rider'
                 LEFT JOIN rider_order_locations loc ON loc.order_id = o.id
                 WHERE o.id = ? AND o.fulfillment_mode = 'internal' AND o.status IN ('rider_assigned', 'picked_up')
                   AND o.deleted_at IS NULL AND r.deleted_at IS NULL FOR UPDATE",
                [$userId, $id]
            )->fetch(PDO::FETCH_ASSOC);
            if (!$active) {
                throw new DomainException('Active assignment not found.');
            }
            $lastLocationAt = !empty($active['received_at']) ? strtotime($active['received_at']) : false;
            if ($lastLocationAt !== false && $lastLocationAt > time() - 20) {
                $this->db->roll_back();
                http_response_code(429);
                header('Retry-After: 20');
                echo json_encode(['success' => false, 'error' => 'Location was updated recently. Please wait before sending another update.']);
                return;
            }
            $this->db->raw(
                'INSERT INTO rider_order_locations (order_id, latitude, longitude, accuracy_m, received_at, sharing_enabled, updated_at)
                 VALUES (?, ?, ?, ?, ?, 1, ?) ON DUPLICATE KEY UPDATE latitude = VALUES(latitude), longitude = VALUES(longitude), accuracy_m = VALUES(accuracy_m), received_at = VALUES(received_at), sharing_enabled = 1, updated_at = VALUES(updated_at)',
                [$id, $latitude, $longitude, $accuracy, $now, $now]
            );
            $this->db->commit();
        } catch (Throwable $e) {
            $this->db->roll_back();
            if ($e instanceof DomainException) {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'Active assignment not found.']);
                return;
            }
            throw $e;
        }
        echo json_encode(['success' => true, 'received_at' => $now]);
    }

    public function pickup(int $id): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verify_csrf()) {
            http_response_code(400);
            flash('error', 'Invalid request or security token.');
            redirect('rider/orders/' . $id);
            return;
        }

        try {
            (new DispatchFulfillmentService($this->db, $this->Order_model))
                ->updateRiderStatus($id, (int)auth_id(), 'picked_up');
            flash('success', 'Pickup recorded successfully.');
        } catch (\Throwable $e) {
            error_log('Rider pickup failed for order ' . $id . ': ' . $e->getMessage());
            flash('error', 'Pickup was not recorded: ' . $e->getMessage());
        }
        redirect('rider/orders/' . $id);
    }

    public function complete(int $id): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verify_csrf()) {
            http_response_code(400);
            flash('error', 'Invalid request or security token.');
            redirect('rider');
            return;
        }

        try {
            (new DeliveryProofService($this->db))->completeWithProof(
                $id,
                (int)auth_id(),
                (string)($_POST['recipient_name'] ?? ''),
                (string)($_POST['note'] ?? ''),
                $_FILES['proof_photo'] ?? [],
                $this->Order_model
            );
            flash('success', 'Delivery completed and proof recorded.');
        } catch (Throwable $e) {
            error_log('Rider delivery completion failed for order ' . $id . ': ' . $e->getMessage());
            flash('error', 'Delivery was not completed. Check the photo, recipient details, and assignment, then try again.');
        }
        redirect('rider/orders/' . $id);
    }

    public function data(): void
    {
        $this->call->library('api');
        if (strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
            $this->api->respond(['success' => false, 'error' => 'Method not allowed.'], 405);
            return;
        }
        $rider = $this->Rider_model->find_for_user((int)auth_id());
        $orders = $rider ? $this->Rider_model->get_active_orders((int)auth_id()) : [];
        $this->api->respond([
            'success' => true,
            'data' => [
                'portal_linked' => $rider !== null,
                'rider' => $rider,
                'orders' => $orders
            ]
        ]);
    }

    public function show_data(int $id): void
    {
        $this->call->library('api');
        if (strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
            $this->api->respond(['success' => false, 'error' => 'Method not allowed.'], 405);
            return;
        }
        $order = $this->Rider_model->get_delivery_for_user($id, (int)auth_id());
        if (!$order) {
            $this->api->respond(['success' => false, 'error' => 'Delivery not found.'], 404);
            return;
        }
        $proof = (new DeliveryProofService($this->db))->getForOrder($id);
        $this->api->respond([
            'success' => true,
            'data' => [
                'order' => $order,
                'proof_recorded' => $proof !== null
            ]
        ]);
    }

    public function api_pickup(int $id): void
    {
        $this->call->library('api');
        if (strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            $this->api->respond(['success' => false, 'error' => 'Method not allowed.'], 405);
            return;
        }
        if (!verify_csrf()) {
            $this->api->respond(['success' => false, 'error' => 'Invalid security token.'], 400);
            return;
        }
        try {
            (new DispatchFulfillmentService($this->db, $this->Order_model))
                ->updateRiderStatus($id, (int)auth_id(), 'picked_up');
            $this->api->respond(['success' => true, 'message' => 'Pickup recorded.']);
        } catch (\Throwable $e) {
            error_log('Rider SPA pickup failed for order ' . $id . ': ' . $e->getMessage());
            $this->api->respond(['success' => false, 'error' => 'Pickup was not recorded: ' . $e->getMessage()], 400);
        }
    }

    public function api_complete(int $id): void
    {
        $this->call->library('api');
        if (strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            $this->api->respond(['success' => false, 'error' => 'Method not allowed.'], 405);
            return;
        }
        if (!verify_csrf()) {
            $this->api->respond(['success' => false, 'error' => 'Invalid security token.'], 400);
            return;
        }
        try {
            (new DeliveryProofService($this->db))->completeWithProof(
                $id,
                (int)auth_id(),
                (string)($_POST['recipient_name'] ?? ''),
                (string)($_POST['note'] ?? ''),
                $_FILES['proof_photo'] ?? [],
                $this->Order_model
            );
            $this->api->respond(['success' => true, 'message' => 'Delivery completed and proof recorded.']);
        } catch (\Throwable $e) {
            error_log('Rider SPA completion failed for order ' . $id . ': ' . $e->getMessage());
            $this->api->respond(['success' => false, 'error' => 'Delivery was not completed. Check the photo, recipient details, and assignment, then try again.'], 400);
        }
    }
}

