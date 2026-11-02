<?php

class Add_id_file_data_to_job_applications
{
    private $_lava;

    public function __construct()
    {
        $this->_lava = lava_instance();
    }

    public function up(): void
    {
        try {
            $cols = $this->_lava->db->raw("SHOW COLUMNS FROM job_applications LIKE 'id_file_data'")->fetch(PDO::FETCH_ASSOC);
            if (!$cols) {
                $this->_lava->db->raw("ALTER TABLE job_applications ADD COLUMN id_file_data LONGBLOB NULL AFTER id_original_name");
            }
        } catch (Throwable $e) {
            // Ignore if column already exists
        }
    }

    public function down(): void
    {
        try {
            $this->_lava->db->raw("ALTER TABLE job_applications DROP COLUMN id_file_data");
        } catch (Throwable $e) {
            // Ignore if column doesn't exist
        }
    }
}
