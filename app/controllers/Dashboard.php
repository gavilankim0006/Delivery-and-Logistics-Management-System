<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

/**
 * Dashboard Controller
 * Customer home: live deliveries, monthly spend, and quick re-order shortcuts.
 */
class Dashboard extends Controller
{
    protected function currentCustomerId(): ?int
    {
        $user = auth_user();
        return isset($user['customer_id']) && (int)$user['customer_id'] > 0
            ? (int)$user['customer_id']
            : null;
    }

    public function __construct()
    {
        parent::__construct();
        $this->call->helper('auth');
        $this->call->model('Order_model');
        require_role(['customer']);
    }

    public function index(): void
    {
        $customerId = $this->currentCustomerId();
        if ($customerId === null) {
            http_response_code(403);
            return;
        }

        $stats = $this->Order_model->get_customer_stats($customerId);

        $activeOrders = $this->Order_model->get_list([
            'customer_id' => $customerId,
            'statuses' => ['pending_payment', 'paid', 'booking', 'rider_assigned', 'picked_up'],
            'limit' => 3,
            'offset' => 0
        ]);

        $recentOrders = $this->Order_model->get_list([
            'customer_id' => $customerId,
            'limit' => 5,
            'offset' => 0
        ]);

        $this->call->view('dashboard/index', [
            'page_title' => 'Dashboard',
            'user' => auth_user(),
            'stats' => $stats,
            'active_orders' => $activeOrders,
            'recent_orders' => $recentOrders
        ]);
    }
}
