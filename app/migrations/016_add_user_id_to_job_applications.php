<?php

class Add_user_id_to_job_applications
{
    private $_lava;

    public function __construct()
    {
        $this->_lava = lava_instance();
    }

    public function up(): void
    {
        try {
            $cols = $this->_lava->db->raw("SHOW COLUMNS FROM job_applications LIKE 'user_id'")->fetch(PDO::FETCH_ASSOC);
            if (!$cols) {
                $this->_lava->db->raw("ALTER TABLE job_applications ADD COLUMN user_id BIGINT UNSIGNED NULL AFTER reviewed_by");
            }
        } catch (Throwable $e) {
            // Ignore if column already exists
        }
    }

    public function down(): void
    {
        try {
            $this->_lava->db->raw("ALTER TABLE job_applications DROP COLUMN user_id");
        } catch (Throwable $e) {
            // Ignore if column doesn't exist
        }
    }
}
