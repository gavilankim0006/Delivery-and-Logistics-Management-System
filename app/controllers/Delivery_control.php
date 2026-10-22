<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

require_once APP_DIR . 'libraries/DeliveryControlService.php';

class Delivery_control extends Controller
{
    private DeliveryControlService $control;

    public function __construct()
    {
        parent::__construct();
        $this->call->helper('auth');
        $this->control = new DeliveryControlService($this->db);
    }

    public function show(string $token)
    {
        $order = $this->control->publicOrder($token);
        if (!$order) {
            http_response_code(404);
            $this->call->view('delivery_control/not_found', ['page_title' => 'Delivery link unavailable']);
            return;
        }
        $statusTime = null;
        foreach ($order['timeline'] as $entry) {
            if ($entry['to_status'] === $order['status']) {
                $statusTime = $entry['created_at'];
            }
        }
        $eta = null;
        if ($statusTime && in_array($order['status'], ['rider_assigned', 'picked_up'], true)) {
            $baseMinutes = max(0, (int)$order['estimated_duration_min']);
            $elapsed = max(0, (int)floor((time() - strtotime($statusTime)) / 60));
            $eta = max(0, $baseMinutes - $elapsed);
        }
        $this->call->view('delivery_control/show', [
            'page_title' => 'Delivery control pass', 'order' => $order, 'token' => $token, 'eta_minutes' => $eta
        ]);
    }

    public function submit(string $token)
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verify_csrf()) {
            http_response_code(400);
            $this->call->view('delivery_control/not_found', ['page_title' => 'Delivery link unavailable']);
            return;
        }
        try {
            $type = trim((string)($_POST['request_type'] ?? ''));
            $text = trim((string)($_POST['request_text'] ?? ''));
            $this->control->submit($token, $type, $text);
            flash('success', $type === 'instruction' ? 'Drop-off instructions updated.' : 'Hold request sent to dispatch for review. This does not pause the courier.');
        } catch (Throwable $e) {
            flash('error', $e->getMessage());
        }
        redirect('delivery-control/' . rawurlencode($token));
    }
}
