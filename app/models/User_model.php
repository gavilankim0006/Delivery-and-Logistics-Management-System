<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

class User_model extends Model
{
    protected $table = 'users';
    protected $primary_key = 'id';
    protected $fillable = ['role_id', 'name', 'email', 'phone', 'password', 'fcm_token', 'push_opt_in', 'status'];

    protected function getDb()
    {
        return $this->db ?? lava_instance()->db;
    }

    public function find_by_email(string $email): ?array
    {
        $row = $this->getDb()->table('users')
            ->select('users.id, users.role_id, users.name, users.email, users.phone, users.password, users.status, roles.name AS role_name, customers.id AS customer_id, customers.company_name')
            ->inner_join('roles', 'users.role_id = roles.id')
            ->left_join('customers', 'customers.user_id = users.id')
            ->where('users.email', $email)
            ->where_null('users.deleted_at')
            ->get();
        return $row ?: null;
    }

    public function find_by_id(int $id): ?array
    {
        $row = $this->getDb()->table('users')
            ->select('users.id, users.role_id, users.name, users.email, users.phone, users.password, users.status, users.created_at, roles.name AS role_name, customers.id AS customer_id, customers.company_name')
            ->inner_join('roles', 'users.role_id = roles.id')
            ->left_join('customers', 'customers.user_id = users.id')
            ->where('users.id', $id)
            ->where_null('users.deleted_at')
            ->get();
        return $row ?: null;
    }

    public function authenticate(string $email, string $password): ?array
    {
        $user = $this->find_by_email($email);
        if (!$user || $user['status'] !== 'active') {
            return null;
        }

        return password_verify($password, $user['password']) ? $user : null;
    }

    public function register_customer(array $userData, array $customerData = []): array
    {
        $db = $this->getDb();
        $email = strtolower(trim((string)($userData['email'] ?? '')));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'error' => 'Enter a valid email address.'];
        }
        if ($this->email_in_use($email)) {
            return ['success' => false, 'error' => 'Email address is already registered.'];
        }

        $db->transaction();
        try {
            $db->table('users')->insert([
                'role_id' => 3,
                'name' => trim($userData['name']),
                'email' => $email,
                'phone' => trim($userData['phone'] ?? ''),
                'password' => password_hash($userData['password'], PASSWORD_BCRYPT),
                'status' => 'active',
                'deleted_at' => null,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ]);
            $userId = (int)$db->last_id();

            $db->table('customers')->insert([
                'user_id' => $userId,
                'company_name' => trim($customerData['company_name'] ?? '') ?: null,
                'default_contact_phone' => trim($userData['phone'] ?? ''),
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ]);
            $customerId = (int)$db->last_id();
            $db->commit();

            return [
                'success' => true,
                'user' => $this->find_by_id($userId),
                'user_id' => $userId,
                'customer_id' => $customerId
            ];
        } catch (Throwable $e) {
            $db->roll_back();
            return ['success' => false, 'error' => 'Failed to create customer account: ' . $e->getMessage()];
        }
    }

    public function create_staff(array $data): array
    {
        $email = strtolower(trim((string)($data['email'] ?? '')));
        if ($this->email_in_use($email)) {
            return ['success' => false, 'error' => 'Email address is already in use.'];
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'error' => 'Enter a valid email address.'];
        }
        $roleId = (int)($data['role_id'] ?? 0);
        if (!in_array($roleId, [1, 2, 4], true)) {
            return ['success' => false, 'error' => 'Choose a valid staff role.'];
        }
        if (mb_strlen(trim((string)($data['name'] ?? ''))) < 2 || mb_strlen((string)($data['name'] ?? '')) > 100) {
            return ['success' => false, 'error' => 'Name must be between 2 and 100 characters.'];
        }
        if (strlen((string)($data['password'] ?? '')) < 8) {
            return ['success' => false, 'error' => 'Password must be at least 8 characters.'];
        }
        if (!in_array($data['status'] ?? 'active', ['active', 'inactive'], true)) {
            return ['success' => false, 'error' => 'Choose a valid account status.'];
        }

        if ($roleId === 4) {
            return $this->create_rider_account($data);
        }

        try {
            $this->getDb()->table('users')->insert([
                'role_id' => $roleId,
                'name' => trim($data['name']),
                'email' => $email,
                'phone' => trim($data['phone'] ?? ''),
                'password' => password_hash($data['password'], PASSWORD_BCRYPT),
                'status' => $data['status'] ?? 'active',
                'deleted_at' => null,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ]);
            return ['success' => true, 'user_id' => (int)$this->getDb()->last_id()];
        } catch (Throwable $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    private function create_rider_account(array $data): array
    {
        $name = trim((string)($data['name'] ?? ''));
        $phone = trim((string)($data['phone'] ?? ''));
        $password = (string)($data['password'] ?? '');
        if ($name === '' || mb_strlen($name) > 100 || strlen($phone) > 30) {
            return ['success' => false, 'error' => 'Enter a valid rider name and phone number.'];
        }

        $db = $this->getDb();
        $db->transaction();
        try {
            $db->table('users')->insert([
                'role_id' => 4, 'name' => $name, 'email' => strtolower(trim((string)$data['email'])),
                'phone' => $phone, 'password' => password_hash($password, PASSWORD_BCRYPT),
                'status' => $data['status'] ?? 'active', 'deleted_at' => null,
                'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')
            ]);
            $userId = (int)$db->last_id();
            $db->table('riders')->insert([
                'user_id' => $userId, 'name' => $name, 'phone' => $phone,
                'vehicle_type' => 'Motorcycle', 'plate_number' => 'PENDING-' . $userId,
                'status' => ($data['status'] ?? 'active') === 'active' ? 'available' : 'offline', 'deleted_at' => null,
                'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')
            ]);
            $riderId = (int)$db->last_id();
            $db->commit();
            return ['success' => true, 'user_id' => $userId, 'rider_id' => $riderId];
        } catch (Throwable $e) {
            $db->roll_back();
            return ['success' => false, 'error' => 'Failed to create rider account: ' . $e->getMessage()];
        }
    }

    public function update_user(int $id, array $data): bool
    {
        $fields = [];
        foreach (['name', 'phone', 'status'] as $key) {
            if (array_key_exists($key, $data)) {
                $fields[$key] = trim((string)$data[$key]);
            }
        }
        if (array_key_exists('sms_opt_in', $data)) {
            $fields['sms_opt_in'] = !empty($data['sms_opt_in']) ? 1 : 0;
        }
        if (array_key_exists('delivery_status_sms_opt_in', $data)) {
            $fields['delivery_status_sms_opt_in'] = !empty($data['delivery_status_sms_opt_in']) ? 1 : 0;
        }
        if (array_key_exists('push_opt_in', $data)) {
            $fields['push_opt_in'] = !empty($data['push_opt_in']) ? 1 : 0;
        }
        if (array_key_exists('email_notifications_opt_in', $data)) {
            $fields['email_notifications_opt_in'] = !empty($data['email_notifications_opt_in']) ? 1 : 0;
        }
        if (isset($data['email'])) {
            $email = strtolower(trim((string)$data['email']));
            if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $this->email_in_use($email, $id)) {
                return false;
            }
            $fields['email'] = $email;
        }
        if (isset($data['role_id'])) {
            $roleId = (int)$data['role_id'];
            if (!in_array($roleId, [1, 2, 3, 4], true)) {
                return false;
            }
            $fields['role_id'] = $roleId;
        }
        if (isset($fields['status']) && !in_array($fields['status'], ['active', 'inactive'], true)) {
            return false;
        }
        if (isset($fields['name']) && (mb_strlen($fields['name']) < 2 || mb_strlen($fields['name']) > 100)) {
            return false;
        }
        if (array_key_exists('password', $data) && $data['password'] !== '') {
            if (strlen((string)$data['password']) < 8) {
                return false;
            }
            $fields['password'] = password_hash($data['password'], PASSWORD_BCRYPT);
        }
        $roleChangingToRider = isset($fields['role_id']) && $fields['role_id'] === 4;
        if ($roleChangingToRider && ($fields['status'] ?? 'active') !== 'active') {
            return false;
        }
        if (!$fields) {
            return false;
        }

        $fields['updated_at'] = date('Y-m-d H:i:s');
        $db = $this->getDb();
        $db->transaction();
        try {
            $linkedRider = $db->raw('SELECT id, status FROM riders WHERE user_id = ? AND deleted_at IS NULL LIMIT 1 FOR UPDATE', [$id])->fetch(PDO::FETCH_ASSOC);
            if ($roleChangingToRider && !$linkedRider) {
                throw new DomainException('Create a rider account from the staff form or link a rider record before changing this role.');
            }
            if ($linkedRider && isset($fields['role_id']) && $fields['role_id'] !== 4) {
                throw new DomainException('Unlink the rider record before changing this account to another role.');
            }
            if ($linkedRider && isset($fields['status']) && $fields['status'] === 'inactive') {
                $active = $db->raw('SELECT id FROM rider_assignments WHERE rider_id = ? AND released_at IS NULL LIMIT 1 FOR UPDATE', [(int)$linkedRider['id']])->fetch(PDO::FETCH_ASSOC);
                if ($active) {
                    throw new DomainException('A rider account with an active delivery cannot be deactivated.');
                }
                $db->raw("UPDATE riders SET status = 'offline', updated_at = ? WHERE id = ? AND status <> 'offline'", [date('Y-m-d H:i:s'), (int)$linkedRider['id']]);
            } elseif ($linkedRider && isset($fields['status']) && $fields['status'] === 'active' && $linkedRider['status'] === 'offline') {
                $db->raw("UPDATE riders SET status = 'available', updated_at = ? WHERE id = ? AND status = 'offline'", [date('Y-m-d H:i:s'), (int)$linkedRider['id']]);
            }
            $updated = $db->table('users')->where('id', $id)->where_null('deleted_at')->update($fields) === 1;
            $db->commit();
            return $updated;
        } catch (Throwable $e) {
            $db->roll_back();
            error_log('User update failed: ' . $e->getMessage());
            return false;
        }
    }

    public function soft_delete_user(int $id): bool
    {
        $now = date('Y-m-d H:i:s');
        $db = $this->getDb();
        $db->transaction();
        try {
            $rider = $db->raw('SELECT id FROM riders WHERE user_id = ? AND deleted_at IS NULL LIMIT 1 FOR UPDATE', [$id])->fetch(PDO::FETCH_ASSOC);
            if ($rider) {
                $active = $db->raw('SELECT id FROM rider_assignments WHERE rider_id = ? AND released_at IS NULL LIMIT 1 FOR UPDATE', [(int)$rider['id']])->fetch(PDO::FETCH_ASSOC);
                if ($active) throw new DomainException('A rider account with an active delivery cannot be removed.');
                $db->raw("UPDATE riders SET status = 'offline', updated_at = ? WHERE id = ?", [$now, (int)$rider['id']]);
            }
            $updated = $db->table('users')->where('id', $id)->where_null('deleted_at')->update([
                'deleted_at' => $now,
                'updated_at' => $now,
                'status' => 'inactive'
            ]) === 1;
            $db->commit();
            return $updated;
        } catch (Throwable $e) {
            $db->roll_back();
            return false;
        }
    }

    public function email_in_use(string $email, ?int $exceptUserId = null): bool
    {
        // Retain email ownership after soft deletion because the schema has a global unique index.
        $sql = 'SELECT id FROM users WHERE email = ?';
        $params = [strtolower(trim($email))];
        if ($exceptUserId !== null) {
            $sql .= ' AND id <> ?';
            $params[] = $exceptUserId;
        }
        $stmt = $this->getDb()->raw($sql . ' LIMIT 1', $params);
        return $stmt !== false && (bool)$stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function save_fcm_token(int $userId, string $token): bool
    {
        return $this->getDb()->table('users')->where('id', $userId)->where_null('deleted_at')->update([
            'fcm_token' => $token,
            'updated_at' => date('Y-m-d H:i:s')
        ]) === 1;
    }

    public function get_fcm_token(int $userId): ?string
    {
        $row = $this->getDb()->table('users')
            ->select('fcm_token')
            ->where('id', $userId)
            ->where_null('deleted_at')
            ->get();

        $token = is_array($row) ? ($row['fcm_token'] ?? null) : null;
        return is_string($token) && trim($token) !== '' ? $token : null;
    }

    public function get_users(int $limit = 50, int $offset = 0, string $search = '', ?int $roleId = null): array
    {
        [$where, $params] = $this->user_conditions($search, $roleId);
        $limit = max(1, min(100, $limit));
        $offset = max(0, $offset);
        $stmt = $this->getDb()->raw(
            "SELECT u.id, u.name, u.email, u.phone, u.status, u.created_at, u.role_id, r.name AS role_name, c.company_name
             FROM users u INNER JOIN roles r ON u.role_id = r.id LEFT JOIN customers c ON c.user_id = u.id
             {$where} ORDER BY u.id DESC LIMIT {$limit} OFFSET {$offset}",
            $params
        );
        return $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
    }

    public function count_users(string $search = '', ?int $roleId = null): int
    {
        [$where, $params] = $this->user_conditions($search, $roleId);
        $row = $this->getDb()->raw("SELECT COUNT(*) AS total FROM users u {$where}", $params)->fetch(PDO::FETCH_ASSOC);
        return (int)($row['total'] ?? 0);
    }

    private function user_conditions(string $search, ?int $roleId): array
    {
        $where = 'WHERE u.deleted_at IS NULL';
        $params = [];
        if ($search !== '') {
            $where .= ' AND (u.name LIKE ? OR u.email LIKE ? OR u.phone LIKE ?)';
            $term = '%' . $search . '%';
            array_push($params, $term, $term, $term);
        }
        if ($roleId !== null) {
            $where .= ' AND u.role_id = ?';
            $params[] = $roleId;
        }
        return [$where, $params];
    }

    public function get_roles(): array
    {
        $rows = $this->getDb()->table('roles')->order_by('id', 'ASC')->get_all();
        return $rows ?: [];
    }
}
