<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

/**
 * Team Controller
 * Business customers invite sub-users that operate under their account.
 * Only the account owner (the user on the customers record) manages the team.
 */
class Team extends Controller
{
    private const MAX_MEMBERS = 10;

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
        $this->call->model('User_model');
        $this->call->model('Customer_member_model');
        require_role(['customer']);
    }

    public function index(): void
    {
        $customerId = $this->currentCustomerId();
        if ($customerId === null) {
            http_response_code(403);
            return;
        }

        $owner = $this->db->raw(
            'SELECT u.id, u.name, u.email, u.phone, u.status
             FROM customers c INNER JOIN users u ON u.id = c.user_id
             WHERE c.id = ? LIMIT 1',
            [$customerId]
        )->fetch(PDO::FETCH_ASSOC);

        $members = $this->Customer_member_model->get_by_customer($customerId);

        $this->call->view('team/index', [
            'page_title' => 'Team members',
            'owner' => $owner ?: [],
            'members' => $members,
            'is_owner' => $owner && (int)$owner['id'] === (int)auth_id(),
            'member_limit' => self::MAX_MEMBERS
        ]);
    }

    public function store(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verify_csrf()) {
            http_response_code(400);
            flash('error', 'Invalid request or security token.');
            redirect('team');
            return;
        }

        $customerId = $this->currentCustomerId();
        $owner = $customerId ? $this->db->raw(
            'SELECT user_id FROM customers WHERE id = ? LIMIT 1',
            [$customerId]
        )->fetch(PDO::FETCH_ASSOC) : null;

        if (!$owner || (int)$owner['user_id'] !== (int)auth_id()) {
            flash('error', 'Only the account owner can add team members.');
            redirect('team');
            return;
        }

        $name = trim((string)($_POST['name'] ?? ''));
        $email = strtolower(trim((string)($_POST['email'] ?? '')));
        $phone = trim((string)($_POST['phone'] ?? ''));
        $password = (string)($_POST['password'] ?? '');
        $passwordConfirm = (string)($_POST['password_confirm'] ?? '');

        if (mb_strlen($name) < 2 || mb_strlen($name) > 100) {
            flash('error', 'Enter the member\'s full name (2-100 characters).');
            redirect('team');
            return;
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            flash('error', 'Enter a valid email address for the team member.');
            redirect('team');
            return;
        }
        if ($this->User_model->email_in_use($email)) {
            flash('error', 'That email address already belongs to an account. Each member needs a unique email.');
            redirect('team');
            return;
        }
        if ($phone !== '') {
            $cleanedPhone = preg_replace('/[\s\-\(\)\.]/', '', $phone);
            if (!preg_match('/^(?:09|\+639|639)(\d{9})$/', (string)$cleanedPhone, $phoneMatches)) {
                flash('error', 'Enter a valid Philippine mobile number (09XXXXXXXXX or +639XXXXXXXXX).');
                redirect('team');
                return;
            }
            $phone = '09' . $phoneMatches[1];
        }
        if (strlen($password) < 8 || !preg_match('/[a-zA-Z]/', $password) || !preg_match('/\d/', $password)) {
            flash('error', 'The temporary password must be at least 8 characters and include a letter and a number.');
            redirect('team');
            return;
        }
        if ($password !== $passwordConfirm) {
            flash('error', 'Password confirmation does not match.');
            redirect('team');
            return;
        }
        if ($this->Customer_member_model->count_for_customer($customerId) >= self::MAX_MEMBERS) {
            flash('error', 'This account reached the ' . self::MAX_MEMBERS . '-member team limit.');
            redirect('team');
            return;
        }

        $db = $this->db;
        $db->transaction();
        try {
            $db->table('users')->insert([
                'role_id' => 3,
                'name' => $name,
                'email' => $email,
                'phone' => $phone !== '' ? $phone : null,
                'password' => password_hash($password, PASSWORD_BCRYPT),
                'sms_opt_in' => 1,
                'delivery_status_sms_opt_in' => 1,
                'push_opt_in' => 1,
                'email_notifications_opt_in' => 1,
                'status' => 'active',
                'deleted_at' => null,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ]);
            $userId = (int)$db->last_id();
            $this->Customer_member_model->create($customerId, $userId, (int)auth_id());
            $db->commit();

            flash('success', "{$name} can now sign in at the login page with {$email} and operate this account's deliveries.");
        } catch (Throwable $e) {
            $db->roll_back();
            error_log('Team member creation failed: ' . $e->getMessage());
            flash('error', 'We could not add this team member. Please try again.');
        }
        redirect('team');
    }

    public function destroy($id): void
    {
        if (!is_numeric($id) || (int)$id <= 0) {
            redirect('team');
            return;
        }
        $id = (int)$id;

        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verify_csrf()) {
            http_response_code(400);
            flash('error', 'Invalid request or security token.');
            redirect('team');
            return;
        }

        $customerId = $this->currentCustomerId();
        $owner = $customerId ? $this->db->raw(
            'SELECT user_id FROM customers WHERE id = ? LIMIT 1',
            [$customerId]
        )->fetch(PDO::FETCH_ASSOC) : null;

        if (!$owner || (int)$owner['user_id'] !== (int)auth_id()) {
            flash('error', 'Only the account owner can remove team members.');
            redirect('team');
            return;
        }

        $member = $this->Customer_member_model->find_for_customer($id, $customerId);
        if (!$member) {
            flash('error', 'Team member not found.');
            redirect('team');
            return;
        }

        $db = $this->db;
        $db->transaction();
        try {
            // Remove access and deactivate the login; order history keeps the audit trail.
            $this->Customer_member_model->delete_for_customer($id, $customerId);
            $db->raw(
                "UPDATE users SET status = 'inactive', updated_at = ? WHERE id = ? AND deleted_at IS NULL",
                [date('Y-m-d H:i:s'), (int)$member['user_id']]
            );
            $db->commit();
            flash('success', 'Team member removed. Their login has been deactivated.');
        } catch (Throwable $e) {
            $db->roll_back();
            error_log('Team member removal failed: ' . $e->getMessage());
            flash('error', 'We could not remove this team member. Please try again.');
        }
        redirect('team');
    }
}
