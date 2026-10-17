<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

class User_model extends Model
{
    protected $table = 'users';
    protected $primary_key = 'id';
    protected $fillable = ['role_id', 'name', 'email', 'phone', 'password', 'fcm_token', 'status'];

    protected function getDb()
    {
        return $this->db ?? lava_instance()->db;
    }

    /**
     * Find user by email with role details and customer ID
     */
    public function find_by_email(string $email): ?array
    {
        $db = $this->getDb();
        $sql = "SELECT u.*, r.name AS role_name, c.id AS customer_id, c.company_name
                FROM users u
                INNER JOIN roles r ON u.role_id = r.id
                LEFT JOIN customers c ON c.user_id = u.id
                WHERE u.email = ?
                LIMIT 1";
        $stmt = $db->raw($sql, [$email]);
        $row = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : false;
        return $row ?: null;
    }

    /**
     * Find user by ID with role details and customer ID
     */
    public function find_by_id(int $id): ?array
    {
        $db = $this->getDb();
        $sql = "SELECT u.*, r.name AS role_name, c.id AS customer_id, c.company_name
                FROM users u
                INNER JOIN roles r ON u.role_id = r.id
                LEFT JOIN customers c ON c.user_id = u.id
                WHERE u.id = ?
                LIMIT 1";
        $stmt = $db->raw($sql, [$id]);
        $row = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : false;
        return $row ?: null;
    }

    /**
     * Authenticate user credentials
     */
    public function authenticate(string $email, string $password): ?array
    {
        $user = $this->find_by_email($email);
        if (!$user) {
            return null;
        }

        if ($user['status'] !== 'active') {
            return null;
        }

        if (password_verify($password, $user['password'])) {
            return $user;
        }

        return null;
    }

    /**
     * Register a new customer user and customer profile atomically
     */
    public function register_customer(array $userData, array $customerData = []): array
    {
        $db = $this->getDb();

        // Check if email already exists
        if ($this->find_by_email($userData['email'])) {
            return ['success' => false, 'error' => 'Email address is already registered.'];
        }

        $passwordHash = password_hash($userData['password'], PASSWORD_BCRYPT);

        try {
            $userInsert = [
                'role_id' => 3, // customer role
                'name' => trim($userData['name']),
                'email' => strtolower(trim($userData['email'])),
                'phone' => trim($userData['phone'] ?? ''),
                'password' => $passwordHash,
                'status' => 'active',
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ];

            $db->table('users')->insert($userInsert);
            $userId = (int)$db->last_id();

            $customerInsert = [
                'user_id' => $userId,
                'company_name' => trim($customerData['company_name'] ?? ''),
                'default_contact_phone' => trim($userData['phone'] ?? ''),
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ];

            $db->table('customers')->insert($customerInsert);
            $customerId = (int)$db->last_id();

            $newUser = $this->find_by_id($userId);
            return ['success' => true, 'user' => $newUser, 'user_id' => $userId, 'customer_id' => $customerId];
        } catch (\Throwable $e) {
            return ['success' => false, 'error' => 'Failed to create customer account: ' . $e->getMessage()];
        }
    }

    /**
     * Create staff user (admin or dispatcher)
     */
    public function create_staff(array $data): array
    {
        $db = $this->getDb();

        if ($this->find_by_email($data['email'])) {
            return ['success' => false, 'error' => 'Email address is already in use.'];
        }

        $passwordHash = password_hash($data['password'], PASSWORD_BCRYPT);

        try {
            $insert = [
                'role_id' => (int)$data['role_id'],
                'name' => trim($data['name']),
                'email' => strtolower(trim($data['email'])),
                'phone' => trim($data['phone'] ?? ''),
                'password' => $passwordHash,
                'status' => $data['status'] ?? 'active',
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ];

            $db->table('users')->insert($insert);
            $id = (int)$db->last_id();
            return ['success' => true, 'user_id' => $id];
        } catch (\Throwable $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Update user details
     */
    public function update_user(int $id, array $data): bool
    {
        $db = $this->getDb();
        $fields = [];
        if (isset($data['name'])) $fields['name'] = trim($data['name']);
        if (isset($data['phone'])) $fields['phone'] = trim($data['phone']);
        if (isset($data['status'])) $fields['status'] = $data['status'];
        if (isset($data['role_id'])) $fields['role_id'] = (int)$data['role_id'];
        if (!empty($data['password'])) {
            $fields['password'] = password_hash($data['password'], PASSWORD_BCRYPT);
        }
        $fields['updated_at'] = date('Y-m-d H:i:s');

        return (bool)$db->table('users')->where('id', $id)->update($fields);
    }

    /**
     * Save FCM push token
     */
    public function save_fcm_token(int $userId, string $token): bool
    {
        $db = $this->getDb();
        return (bool)$db->table('users')->where('id', $userId)->update([
            'fcm_token' => $token,
            'updated_at' => date('Y-m-d H:i:s')
        ]);
    }

    /**
     * Get list of users with roles for admin
     */
    public function get_users(int $limit = 50, int $offset = 0, string $search = '', ?int $roleId = null): array
    {
        $db = $this->getDb();
        $params = [];
        $where = "WHERE 1=1";

        if (!empty($search)) {
            $where .= " AND (u.name LIKE ? OR u.email LIKE ? OR u.phone LIKE ?)";
            $searchTerm = "%{$search}%";
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }

        if ($roleId !== null) {
            $where .= " AND u.role_id = ?";
            $params[] = $roleId;
        }

        $sql = "SELECT u.id, u.name, u.email, u.phone, u.status, u.created_at, u.role_id, r.name AS role_name, c.company_name
                FROM users u
                INNER JOIN roles r ON u.role_id = r.id
                LEFT JOIN customers c ON c.user_id = u.id
                {$where}
                ORDER BY u.id DESC
                LIMIT {$limit} OFFSET {$offset}";

        $stmt = $db->raw($sql, $params);
        return $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
    }

    /**
     * Count users for pagination
     */
    public function count_users(string $search = '', ?int $roleId = null): int
    {
        $db = $this->getDb();
        $params = [];
        $where = "WHERE 1=1";

        if (!empty($search)) {
            $where .= " AND (u.name LIKE ? OR u.email LIKE ? OR u.phone LIKE ?)";
            $searchTerm = "%{$search}%";
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }

        if ($roleId !== null) {
            $where .= " AND u.role_id = ?";
            $params[] = $roleId;
        }

        $sql = "SELECT COUNT(*) as total FROM users u {$where}";
        $stmt = $db->raw($sql, $params);
        $row = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : false;
        return $row ? (int)$row['total'] : 0;
    }

    /**
     * Get all roles
     */
    public function get_roles(): array
    {
        $db = $this->getDb();
        $stmt = $db->raw("SELECT * FROM roles ORDER BY id ASC");
        return $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
    }
}
