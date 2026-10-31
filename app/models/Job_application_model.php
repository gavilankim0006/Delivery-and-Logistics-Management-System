<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

class Job_application_model extends Model
{
    protected $table = 'job_applications';
    protected $primary_key = 'id';

    public function list_all(?string $status = null): array
    {
        $sql = 'SELECT * FROM job_applications';
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
