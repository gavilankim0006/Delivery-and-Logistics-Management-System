<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

require_once APP_DIR . 'libraries/DispatchRecoveryService.php';

class Dispatch extends Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->call->helper('auth');
        // Both dispatchers and admins can access the dispatch board
        require_role(['dispatcher', 'admin']);
        $this->call->model('Order_model');
    }

    /**
     * Dispatch Board
     */
    public function index()
    {
        $statusFilter = trim($_GET['status'] ?? '');
        $search = trim($_GET['search'] ?? '');
        $dateFilter = trim($_GET['date'] ?? '');
        $boardData = $this->getBoardData($statusFilter, $search, $dateFilter);

        $pendingRequests = $this->db->raw("SELECT cr.id, cr.order_id, cr.request_type, cr.request_text, cr.created_at, o.order_no
            FROM delivery_change_requests cr INNER JOIN orders o ON o.id = cr.order_id
            WHERE cr.status = 'pending' AND cr.request_type = 'hold' ORDER BY cr.created_at ASC LIMIT 30")->fetchAll(PDO::FETCH_ASSOC);

        $this->call->view('dispatch/index', array_merge($boardData, [
            'page_title' => 'Live Dispatch Board',
            'status_filter' => $statusFilter,
            'search' => $search,
            'date_filter' => $dateFilter,
            'dispatch_data_url' => base_url('dispatch/data'),
            'dispatch_base_url' => base_url(),
            'csrf_token' => csrf_token(),
            'pending_request_count' => count($pendingRequests),
            'pending_requests' => $pendingRequests
        ]));
    }

    public function data(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store, max-age=0');
        if (strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
            http_response_code(405);
            echo json_encode(['success' => false, 'error' => 'Method not allowed.']);
            return;
        }

        try {
            $data = $this->getBoardData(
                trim($_GET['status'] ?? ''),
                trim($_GET['search'] ?? ''),
                trim($_GET['date'] ?? '')
            );
            echo json_encode(['success' => true, 'data' => $data], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
        } catch (Throwable $e) {
            error_log('Dispatch board data request failed: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Unable to load dispatch orders.']);
        }
    }

    private function getBoardData(string $statusFilter, string $search, string $dateFilter): array
    {
        $db = $this->db;
        $todayTotal = (int)$db->raw("SELECT COUNT(*) as c FROM orders WHERE DATE(created_at) = CURRENT_DATE()")->fetch()['c'];
        $awaitingBooking = (int)$db->table('orders')->where('status', 'paid')->select_count('*', 'c')->get()['c'];
        $inTransit = (int)$db->raw("SELECT COUNT(*) as c FROM orders WHERE status IN ('booking', 'rider_assigned', 'picked_up')")->fetch()['c'];
        $failedCount = (int)$db->table('orders')->where('status', 'failed')->select_count('*', 'c')->get()['c'];

        $where = "WHERE 1=1";
        $params = [];
        $allowedStatuses = ['draft', 'pending_payment', 'paid', 'booking', 'rider_assigned', 'picked_up', 'delivered', 'cancelled', 'failed'];
        if ($statusFilter !== '' && in_array($statusFilter, $allowedStatuses, true)) {
            $where .= " AND o.status = ?";
            $params[] = $statusFilter;
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFilter) === 1) {
            $where .= " AND DATE(o.created_at) = ?";
            $params[] = $dateFilter;
        }
        if ($search !== '') {
            $where .= " AND (o.order_no LIKE ? OR u.name LIKE ? OR c.company_name LIKE ?)";
            $term = '%' . substr($search, 0, 100) . '%';
            array_push($params, $term, $term, $term);
        }

        $sql = "
            SELECT o.*, u.name AS customer_name, c.company_name,
                   (SELECT MAX(d.created_at) FROM deliveries d WHERE d.order_id = o.id AND d.to_status = o.status) AS status_since,
                   (SELECT COUNT(*) FROM delivery_change_requests cr WHERE cr.order_id = o.id AND cr.status = 'pending') AS pending_request_count,
                   (SELECT MAX(cr.created_at) FROM delivery_change_requests cr WHERE cr.order_id = o.id AND cr.status = 'pending') AS pending_request_at,
                   EXISTS(SELECT 1 FROM dispatch_recovery_locks rl WHERE rl.order_id = o.id) AS recovery_locked,
                   pa.address_line AS pickup_address, pa.contact_phone AS pickup_phone,
                   da.address_line AS dropoff_address, da.contact_phone AS dropoff_phone,
                   r.name AS rider_name, r.phone AS rider_phone, r.plate_number,
                   o.courier_driver_id, o.courier_driver_name, o.courier_driver_phone, o.courier_driver_vehicle, o.share_link
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
        foreach ($orders as &$order) {
            $order['recovery'] = DispatchRecoveryService::recommendation($order);
        }
        unset($order);

        return [
            'orders' => $orders,
            'today_total' => $todayTotal,
            'awaiting_booking' => $awaitingBooking,
            'in_transit' => $inTransit,
            'failed_count' => $failedCount
        ];
    }

    public function review_request(int $id)
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verify_csrf()) {
            http_response_code(400);
            flash('error', 'Invalid request or security token.');
            redirect('dispatch');
            return;
        }
        $decision = trim((string)($_POST['decision'] ?? ''));
        if (!in_array($decision, ['accepted', 'rejected'], true)) {
            flash('error', 'Choose whether to accept or reject this request.');
            redirect('dispatch');
            return;
        }
        $updated = $this->db->raw("UPDATE delivery_change_requests SET status = ?, reviewed_by = ?, reviewed_at = ? WHERE id = ? AND request_type = 'hold' AND status = 'pending'", [$decision, auth_id(), date('Y-m-d H:i:s'), $id]);
        flash($updated && $updated->rowCount() === 1 ? 'success' : 'error', $updated && $updated->rowCount() === 1 ? 'Hold request marked ' . $decision . '.' : 'Pending hold request was not found.');
        redirect('dispatch');
    }

    public function recover(int $id)
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verify_csrf()) {
            flash('error', 'Invalid request or security token.');
            redirect('dispatch');
            return;
        }
        try {
            DispatchRecoveryService::recover($this->db, $this->Order_model, $id, auth_role() ?: 'dispatcher', auth_id());
            flash('success', 'Courier booking recovery completed.');
        } catch (Throwable $e) {
            error_log('Dispatcher recovery failed for order ' . $id . ': ' . $e->getMessage());
            flash('error', 'Recovery was not completed: ' . $e->getMessage());
        }
        redirect('dispatch');
    }
}
