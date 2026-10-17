<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

class Dispatch extends Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->call->helper('auth');
        // Both dispatchers and admins can access the dispatch board
        require_role(['dispatcher', 'admin']);
    }

    /**
     * Dispatch Board
     */
    public function index()
    {
        $db = $this->db;

        // Fetch counts for summary cards
        $todayTotal = (int)$db->raw("SELECT COUNT(*) as c FROM orders WHERE DATE(created_at) = CURRENT_DATE()")->fetch()['c'];
        $awaitingBooking = (int)$db->table('orders')->where('status', 'paid')->select_count('*', 'c')->get()['c'];
        $inTransit = (int)$db->raw("SELECT COUNT(*) as c FROM orders WHERE status IN ('booking', 'rider_assigned', 'picked_up')")->fetch()['c'];
        $failedCount = (int)$db->table('orders')->where('status', 'failed')->select_count('*', 'c')->get()['c'];

        // Filters
        $statusFilter = trim($_GET['status'] ?? '');
        $search = trim($_GET['search'] ?? '');
        $dateFilter = trim($_GET['date'] ?? '');

        $where = "WHERE 1=1";
        $params = [];

        if (!empty($statusFilter)) {
            $where .= " AND o.status = ?";
            $params[] = $statusFilter;
        }

        if (!empty($dateFilter)) {
            $where .= " AND DATE(o.created_at) = ?";
            $params[] = $dateFilter;
        }

        if (!empty($search)) {
            $where .= " AND (o.order_no LIKE ? OR u.name LIKE ? OR c.company_name LIKE ?)";
            $st = "%{$search}%";
            $params[] = $st;
            $params[] = $st;
            $params[] = $st;
        }

        $sql = "
            SELECT o.*, u.name AS customer_name, c.company_name,
                   pa.address_line AS pickup_address, pa.contact_phone AS pickup_phone,
                   da.address_line AS dropoff_address, da.contact_phone AS dropoff_phone,
                   r.name AS rider_name, r.phone AS rider_phone, r.plate_number
            FROM orders o
            INNER JOIN customers c ON o.customer_id = c.id
            INNER JOIN users u ON c.user_id = u.id
            INNER JOIN addresses pa ON o.pickup_address_id = pa.id
            INNER JOIN addresses da ON o.dropoff_address_id = da.id
            LEFT JOIN riders r ON o.rider_id = r.id
            {$where}
            ORDER BY o.id DESC
        ";

        $orders = $db->raw($sql, $params)->fetchAll(PDO::FETCH_ASSOC);

        $this->call->view('dispatch/index', [
            'page_title' => 'Live Dispatch Board',
            'orders' => $orders,
            'today_total' => $todayTotal,
            'awaiting_booking' => $awaitingBooking,
            'in_transit' => $inTransit,
            'failed_count' => $failedCount,
            'status_filter' => $statusFilter,
            'search' => $search,
            'date_filter' => $dateFilter
        ]);
    }
}
