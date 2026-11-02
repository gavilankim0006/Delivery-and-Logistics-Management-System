<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

class Job_application_model extends Model
{
    protected $table = 'job_applications';
    protected $primary_key = 'id';
    private bool $hasFileDataCol = false;

    public function __construct()
    {
        parent::__construct();
        $this->ensure_schema();
    }

    private function ensure_schema(): void
    {
        try {
            $cols = $this->db->raw("SHOW COLUMNS FROM job_applications LIKE 'id_file_data'")->fetch(PDO::FETCH_ASSOC);
            if ($cols) {
                $this->hasFileDataCol = true;
            } else {
                $this->db->raw("ALTER TABLE job_applications ADD COLUMN id_file_data LONGBLOB NULL AFTER id_original_name");
                $this->hasFileDataCol = true;
            }

            $userCol = $this->db->raw("SHOW COLUMNS FROM job_applications LIKE 'user_id'")->fetch(PDO::FETCH_ASSOC);
            if (!$userCol) {
                $this->db->raw("ALTER TABLE job_applications ADD COLUMN user_id BIGINT UNSIGNED NULL AFTER reviewed_by");
            }
        } catch (Throwable $e) {
            // Gracefully ignore if table doesn't exist yet or user lacks ALTER permissions
        }
    }

    public function list_all(?string $status = null): array
    {
        $fileExpr = $this->hasFileDataCol ? '(ja.id_file_data IS NOT NULL) AS has_file_data' : '0 AS has_file_data';
        $sql = "SELECT ja.id, ja.full_name, ja.email, ja.phone, ja.role_applied, ja.location, ja.experience,
                       ja.availability, ja.id_type, ja.id_number, ja.id_storage_key, ja.id_mime_type,
                       ja.id_original_name, {$fileExpr}, ja.status, ja.admin_note, ja.reviewed_by,
                       ja.reviewed_at, ja.created_at, ja.user_id,
                       u.name AS account_name, u.email AS account_email, u.status AS account_status
                FROM job_applications ja
                LEFT JOIN users u ON u.id = ja.user_id";

        $params = [];
        if ($status !== null && in_array($status, ['pending', 'approved', 'rejected'], true)) {
            $sql .= ' WHERE ja.status = ?';
            $params[] = $status;
        }
        $sql .= ' ORDER BY ja.created_at DESC, ja.id DESC';
        return $this->db->raw($sql, $params)->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function find(int $id): ?array
    {
        $row = $this->db->raw(
            'SELECT ja.*, u.name AS account_name, u.email AS account_email, u.status AS account_status
             FROM job_applications ja
             LEFT JOIN users u ON u.id = ja.user_id
             WHERE ja.id = ? LIMIT 1',
            [$id]
        )->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function create(array $application): int
    {
        if ($this->hasFileDataCol && isset($application['id_file_data']) && $application['id_file_data'] !== null) {
            $this->db->raw(
                'INSERT INTO job_applications (full_name, email, phone, role_applied, location, experience, availability, id_type, id_number, id_storage_key, id_mime_type, id_original_name, id_file_data, status, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, \'pending\', CURRENT_TIMESTAMP)',
                [
                    $application['full_name'], $application['email'], $application['phone'],
                    $application['role_applied'], $application['location'], $application['experience'],
                    $application['availability'], $application['id_type'], $application['id_number'],
                    $application['id_storage_key'], $application['id_mime_type'], $application['id_original_name'],
                    $application['id_file_data'],
                ]
            );
        } else {
            $this->db->raw(
                'INSERT INTO job_applications (full_name, email, phone, role_applied, location, experience, availability, id_type, id_number, id_storage_key, id_mime_type, id_original_name, status, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, \'pending\', CURRENT_TIMESTAMP)',
                [
                    $application['full_name'], $application['email'], $application['phone'],
                    $application['role_applied'], $application['location'], $application['experience'],
                    $application['availability'], $application['id_type'], $application['id_number'],
                    $application['id_storage_key'], $application['id_mime_type'], $application['id_original_name'],
                ]
            );
        }
        return (int)$this->db->last_id();
    }

    public function update_status(int $id, string $status, int $reviewedBy, string $note, ?int $userId = null): void
    {
        if ($userId !== null && $userId > 0) {
            $this->db->raw(
                'UPDATE job_applications SET status = ?, admin_note = ?, reviewed_by = ?, reviewed_at = CURRENT_TIMESTAMP, user_id = ? WHERE id = ?',
                [$status, $note, $reviewedBy, $userId, $id]
            );
        } else {
            $this->db->raw(
                'UPDATE job_applications SET status = ?, admin_note = ?, reviewed_by = ?, reviewed_at = CURRENT_TIMESTAMP WHERE id = ?',
                [$status, $note, $reviewedBy, $id]
            );
        }
    }

    public function link_user(int $id, int $userId): void
    {
        $this->db->raw('UPDATE job_applications SET user_id = ? WHERE id = ?', [$userId, $id]);
    }
}
