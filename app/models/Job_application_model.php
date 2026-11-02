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
        } catch (Throwable $e) {
            // Gracefully ignore if table doesn't exist yet or user lacks ALTER permissions
        }
    }

    public function list_all(?string $status = null): array
    {
        if ($this->hasFileDataCol) {
            $sql = 'SELECT id, full_name, email, phone, role_applied, location, experience, availability, id_type, id_number, id_storage_key, id_mime_type, id_original_name, (id_file_data IS NOT NULL) AS has_file_data, status, admin_note, reviewed_by, reviewed_at, created_at FROM job_applications';
        } else {
            $sql = 'SELECT *, 0 AS has_file_data FROM job_applications';
        }
        $params = [];
        if ($status !== null && in_array($status, ['pending', 'approved', 'rejected'], true)) {
            $sql .= ' WHERE status = ?';
            $params[] = $status;
        }
        $sql .= ' ORDER BY created_at DESC, id DESC';
        return $this->db->raw($sql, $params)->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function find(int $id): ?array
    {
        $row = $this->db->raw('SELECT * FROM job_applications WHERE id = ? LIMIT 1', [$id])->fetch(PDO::FETCH_ASSOC);
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

    public function update_status(int $id, string $status, int $reviewedBy, string $note): void
    {
        $this->db->raw(
            'UPDATE job_applications SET status = ?, admin_note = ?, reviewed_by = ?, reviewed_at = CURRENT_TIMESTAMP WHERE id = ?',
            [$status, $note, $reviewedBy, $id]
        );
    }
}
