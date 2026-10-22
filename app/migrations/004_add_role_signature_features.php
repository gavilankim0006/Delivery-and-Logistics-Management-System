<?php

class Add_role_signature_features
{
    private $_lava;

    public function __construct()
    {
        $this->_lava = lava_instance();
        $this->_lava->call->dbforge();
    }

    public function up()
    {
        $db = $this->_lava->db;
        $db->raw("CREATE TABLE IF NOT EXISTS service_areas (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL,
            polygon_json LONGTEXT NOT NULL,
            base_delivery_fee DECIMAL(10,2) NOT NULL DEFAULT 60.00,
            free_distance_km DECIMAL(8,2) NOT NULL DEFAULT 2.00,
            per_km_rate DECIMAL(10,2) NOT NULL DEFAULT 12.50,
            free_weight_kg DECIMAL(8,2) NOT NULL DEFAULT 1.00,
            per_kg_rate DECIMAL(10,2) NOT NULL DEFAULT 5.00,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_service_areas_active (is_active)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $db->raw("CREATE TABLE IF NOT EXISTS delivery_control_passes (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            order_id BIGINT UNSIGNED NOT NULL,
            token_hash CHAR(64) NOT NULL UNIQUE,
            expires_at DATETIME NOT NULL,
            revoked_at DATETIME NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_control_pass_order (order_id),
            CONSTRAINT fk_control_pass_order FOREIGN KEY (order_id) REFERENCES orders (id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $db->raw("CREATE TABLE IF NOT EXISTS delivery_change_requests (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            order_id BIGINT UNSIGNED NOT NULL,
            request_type ENUM('instruction','hold') NOT NULL,
            request_text TEXT NULL,
            status ENUM('pending','applied','accepted','rejected') NOT NULL DEFAULT 'pending',
            source VARCHAR(30) NOT NULL DEFAULT 'control_pass',
            reviewed_by BIGINT UNSIGNED NULL,
            reviewed_at DATETIME NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_change_requests_order_status (order_id, status),
            CONSTRAINT fk_change_request_order FOREIGN KEY (order_id) REFERENCES orders (id) ON DELETE CASCADE,
            CONSTRAINT fk_change_request_reviewer FOREIGN KEY (reviewed_by) REFERENCES users (id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $db->raw("CREATE TABLE IF NOT EXISTS dispatch_recovery_locks (
            order_id BIGINT UNSIGNED PRIMARY KEY,
            started_at DATETIME NOT NULL,
            CONSTRAINT fk_recovery_lock_order FOREIGN KEY (order_id) REFERENCES orders (id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }

    public function down()
    {
        $db = $this->_lava->db;
        $db->raw('DROP TABLE IF EXISTS dispatch_recovery_locks');
        $db->raw('DROP TABLE IF EXISTS delivery_change_requests');
        $db->raw('DROP TABLE IF EXISTS delivery_control_passes');
        $db->raw('DROP TABLE IF EXISTS service_areas');
    }
}
