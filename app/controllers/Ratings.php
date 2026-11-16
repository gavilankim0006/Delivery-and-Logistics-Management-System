<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

/**
 * Ratings Controller
 * Customers rate completed deliveries (1-5 stars plus an optional comment).
 */
class Ratings extends Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->call->helper('auth');
        $this->call->model('Order_model');
        require_role(['customer']);
    }

    protected function currentCustomerId(): ?int
    {
        $user = auth_user();
        return isset($user['customer_id']) && (int)$user['customer_id'] > 0
            ? (int)$user['customer_id']
            : null;
    }

    public function submit($id)
    {
        if (!is_numeric($id) || (int)$id <= 0) {
            redirect('orders');
            return;
        }
        $id = (int)$id;

        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verify_csrf()) {
            http_response_code(400);
            flash('error', 'Invalid request or security token.');
            redirect('orders/' . $id);
            return;
        }

        $customerId = $this->currentCustomerId();
        $order = $customerId ? $this->Order_model->get_by_id($id, $customerId) : null;
        if (!$order) {
            http_response_code(404);
            flash('error', 'Order not found or permission denied.');
            redirect('orders');
            return;
        }
        if ($order['status'] !== 'delivered') {
            flash('error', 'You can rate a delivery once it has been completed.');
            redirect('orders/' . $id);
            return;
        }

        $rating = (int)($_POST['rating'] ?? 0);
        if ($rating < 1 || $rating > 5) {
            flash('error', 'Choose a rating between 1 and 5 stars.');
            redirect('orders/' . $id);
            return;
        }

        $comment = trim((string)($_POST['comment'] ?? ''));
        if (mb_strlen($comment) > 500) {
            flash('error', 'Please keep your feedback to 500 characters or fewer.');
            redirect('orders/' . $id);
            return;
        }

        // Attribute the rating to the most recently assigned rider, if any.
        $assignment = $this->db->raw(
            'SELECT rider_id FROM rider_assignments WHERE order_id = ? ORDER BY id DESC LIMIT 1',
            [$id]
        )->fetch(PDO::FETCH_ASSOC);
        $riderId = $assignment ? (int)$assignment['rider_id'] : null;
        if ($riderId === null && !empty($order['rider_id'])) {
            $riderId = (int)$order['rider_id'];
        }

        $now = date('Y-m-d H:i:s');
        $this->db->raw(
            'INSERT INTO delivery_ratings (order_id, customer_id, rider_id, rating, comment, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE rating = VALUES(rating), comment = VALUES(comment), rider_id = VALUES(rider_id), updated_at = VALUES(updated_at)',
            [$id, $customerId, $riderId, $rating, $comment !== '' ? $comment : null, $now, $now]
        );

        flash('success', "Thanks for the feedback! You rated order #{$order['order_no']} {$rating}/5.");
        redirect('orders/' . $id);
    }
}
