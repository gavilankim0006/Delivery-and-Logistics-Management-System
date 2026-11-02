<?php

class Create_package_reports_and_handoffs
{
    private $_lava;
    public function __construct() { $this->_lava = lava_instance(); }

    public function up(): void
    {
        $this->_lava->db->raw("CREATE TABLE IF NOT EXISTS missing_package_reports (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            order_id BIGINT UNSIGNED NOT NULL,
            customer_id BIGINT UNSIGNED NOT NULL,
            description TEXT NOT NULL,
            status ENUM('open','investigating','resolved','rejected') NOT NULL DEFAULT 'open',
            admin_note TEXT NULL,
            reviewed_by INT NULL,
            reviewed_at DATETIME NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id), KEY idx_missing_reports_status_created (status, created_at),
            KEY idx_missing_reports_customer_order (customer_id, order_id),
            CONSTRAINT fk_missing_reports_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
            CONSTRAINT fk_missing_reports_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
            CONSTRAINT fk_missing_reports_reviewer FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $this->_lava->db->raw("CREATE TABLE IF NOT EXISTS package_handoffs (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            order_id INT NOT NULL,
            sender_customer_id INT NOT NULL,
            recipient_customer_id INT NOT NULL,
            token_hash CHAR(64) NOT NULL,
            status ENUM('pending','confirmed','revoked','expired') NOT NULL DEFAULT 'pending',
            expires_at DATETIME NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            confirmed_at DATETIME NULL,
            PRIMARY KEY (id), UNIQUE KEY uq_package_handoffs_token (token_hash),
            KEY idx_package_handoffs_recipient (recipient_customer_id, status),
            KEY idx_package_handoffs_order (order_id, status),
            CONSTRAINT fk_package_handoffs_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
            CONSTRAINT fk_package_handoffs_sender FOREIGN KEY (sender_customer_id) REFERENCES customers(id) ON DELETE CASCADE,
            CONSTRAINT fk_package_handoffs_recipient FOREIGN KEY (recipient_customer_id) REFERENCES customers(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }
    public function down(): void
    {
        $this->_lava->db->raw('DROP TABLE IF EXISTS package_handoffs');
        $this->_lava->db->raw('DROP TABLE IF EXISTS missing_package_reports');
    }
}
