<?php

class Create_job_applications
{
    private $_lava;

    public function __construct()
    {
        $this->_lava = lava_instance();
    }

    public function up(): void
    {
        $this->_lava->db->raw("CREATE TABLE IF NOT EXISTS job_applications (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            full_name VARCHAR(150) NOT NULL,
            email VARCHAR(254) NOT NULL,
            phone VARCHAR(40) NOT NULL,
            role_applied ENUM('rider', 'dispatcher') NOT NULL,
            location VARCHAR(180) NOT NULL,
            experience TEXT NOT NULL,
            availability VARCHAR(180) NOT NULL,
            id_type VARCHAR(100) NOT NULL,
            id_number VARCHAR(100) NOT NULL,
            id_storage_key VARCHAR(80) NOT NULL,
            id_mime_type VARCHAR(100) NOT NULL,
            id_original_name VARCHAR(255) NOT NULL,
            status ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending',
            admin_note TEXT NULL,
            reviewed_by INT NULL,
            reviewed_at DATETIME NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_job_applications_status_created (status, created_at),
            CONSTRAINT fk_job_applications_reviewer FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }

    public function down(): void
    {
        $this->_lava->db->raw('DROP TABLE IF EXISTS job_applications');
    }
}
