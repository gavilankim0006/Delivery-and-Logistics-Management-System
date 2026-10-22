<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

class Customer_model extends Model
{
    protected $table = 'customers';
    protected $primary_key = 'id';
    protected $fillable = ['user_id', 'company_name', 'default_contact_phone'];

    protected function getDb()
    {
        return $this->db ?? lava_instance()->db;
    }

    private function customer_select(): string
    {
        return 'SELECT c.*, u.name, u.email, u.phone, u.status AS account_status
                FROM customers c INNER JOIN users u ON u.id = c.user_id';
    }

    public function get_all(): array
    {
        $stmt = $this->getDb()->raw(
            $this->customer_select() . ' WHERE u.deleted_at IS NULL ORDER BY c.id DESC'
        );
        return $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
    }

    public function get_by_id(int $id): ?array
    {
        $stmt = $this->getDb()->raw(
            $this->customer_select() . ' WHERE c.id = ? AND u.deleted_at IS NULL LIMIT 1',
            [$id]
        );
        $row = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : false;
        return $row ?: null;
    }

    public function get_filtered(array $filters = []): array
    {
        $where = ['u.deleted_at IS NULL'];
        $params = [];
        if (isset($filters['user_id'])) {
            $where[] = 'c.user_id = ?';
            $params[] = (int)$filters['user_id'];
        }
        if (isset($filters['search']) && trim((string)$filters['search']) !== '') {
            $where[] = '(u.name LIKE ? OR u.email LIKE ? OR c.company_name LIKE ?)';
            $term = '%' . trim((string)$filters['search']) . '%';
            array_push($params, $term, $term, $term);
        }
        $limit = max(1, min(100, (int)($filters['limit'] ?? 100)));
        $offset = max(0, (int)($filters['offset'] ?? 0));
        $sql = $this->customer_select() . ' WHERE ' . implode(' AND ', $where)
            . " ORDER BY c.id DESC LIMIT {$limit} OFFSET {$offset}";
        $stmt = $this->getDb()->raw($sql, $params);
        return $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
    }

    public function create(array $data): int
    {
        $db = $this->getDb();
        $db->table('customers')->insert([
            'user_id' => (int)$data['user_id'],
            'company_name' => trim((string)($data['company_name'] ?? '')) ?: null,
            'default_contact_phone' => trim((string)($data['default_contact_phone'] ?? '')) ?: null,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ]);
        return (int)$db->last_id();
    }

    public function update_customer(int $id, array $data): bool
    {
        $fields = [];
        if (array_key_exists('company_name', $data)) {
            $fields['company_name'] = trim((string)$data['company_name']) ?: null;
        }
        if (array_key_exists('default_contact_phone', $data)) {
            $fields['default_contact_phone'] = trim((string)$data['default_contact_phone']) ?: null;
        }
        if (!$fields) {
            return false;
        }
        $fields['updated_at'] = date('Y-m-d H:i:s');
        return (bool)$this->getDb()->table('customers')->where('id', $id)->update($fields);
    }

    /** Customer records own order history and must not be deleted directly. */
    public function delete(int $id): bool
    {
        return false;
    }
}
