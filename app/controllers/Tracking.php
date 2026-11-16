<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

/**
 * Tracking Controller
 * Public, token-based order tracking shared by customers with recipients.
 */
class Tracking extends Controller
{
    private ?OrderTrackingService $tracking = null;

    public function __construct()
    {
        parent::__construct();
        $this->call->library('OrderTrackingService');
        $this->tracking = new OrderTrackingService($this->db);
    }

    public function show(string $token): void
    {
        $order = $this->tracking->publicTrack($token);
        if (!$order) {
            http_response_code(404);
            $this->call->view('tracking/not_found', ['page_title' => 'Tracking link unavailable']);
            return;
        }

        $this->call->view('tracking/show', [
            'page_title' => 'Track ' . $order['order_no'],
            'order' => $order,
            'token' => $token
        ]);
    }

    public function data(string $token): void
    {
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: private, no-store, max-age=0');

        $order = $this->tracking->publicTrack($token);
        if (!$order) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Tracking link not found.']);
            return;
        }

        echo json_encode([
            'success' => true,
            'data' => [
                'order' => [
                    'order_no' => $order['order_no'],
                    'status' => $order['status'],
                    'eta_at' => $order['eta_at'],
                    'updated_at' => $order['updated_at']
                ],
                'timeline' => $order['timeline']
            ]
        ], JSON_UNESCAPED_SLASHES);
    }
}
