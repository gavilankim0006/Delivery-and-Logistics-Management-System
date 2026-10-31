<?php

class Add_delivery_proofs_and_notifications
{
    private $_lava;

    public function __construct()
    {
        $this->_lava = lava_instance();
    }

    public function up()
    {
        $this->_lava->call->dbforge();
        $db = $this->_lava->db;

        if (!$this->_lava->dbforge->column_exists('users', 'sms_opt_in')) {
            $db->raw('ALTER TABLE users ADD sms_opt_in TINYINT(1) NOT NULL DEFAULT 0 AFTER fcm_token');
        }
        if (!$this->_lava->dbforge->column_exists('users', 'delivery_status_sms_opt_in')) {
            $db->raw('ALTER TABLE users ADD delivery_status_sms_opt_in TINYINT(1) NOT NULL DEFAULT 0 AFTER sms_opt_in');
        }
        $db->raw("CREATE TABLE IF NOT EXISTS delivery_proofs (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            order_id BIGINT UNSIGNED NOT NULL,
            uploaded_by BIGINT UNSIGNED NOT NULL,
            recipient_name VARCHAR(100) NOT NULL,
            note VARCHAR(500) NULL,
            storage_key VARCHAR(80) NOT NULL,
            mime_type VARCHAR(30) NOT NULL,
            file_size INT UNSIGNED NOT NULL,
            sha256 CHAR(64) NOT NULL,
            captured_at DATETIME NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE INDEX uq_delivery_proofs_order (order_id),
            CONSTRAINT fk_delivery_proofs_order FOREIGN KEY (order_id) REFERENCES orders (id) ON DELETE CASCADE,
            CONSTRAINT fk_delivery_proofs_uploader FOREIGN KEY (uploaded_by) REFERENCES users (id) ON DELETE RESTRICT
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $db->raw("CREATE TABLE IF NOT EXISTS delivery_notification_outbox (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            order_id BIGINT UNSIGNED NOT NULL,
            user_id BIGINT UNSIGNED NOT NULL,
            status_code VARCHAR(30) NOT NULL,
            channel ENUM('sms') NOT NULL DEFAULT 'sms',
            recipient VARCHAR(30) NOT NULL,
            message VARCHAR(500) NOT NULL,
            status ENUM('pending', 'sending', 'sent', 'failed') NOT NULL DEFAULT 'pending',
            provider_response TEXT NULL,
            claimed_at DATETIME NULL,
            sent_at DATETIME NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE INDEX uq_delivery_notification_event (order_id, status_code, channel),
            INDEX idx_delivery_notification_pending (status, created_at),
            INDEX idx_delivery_notification_claimed (status, claimed_at),
            CONSTRAINT fk_delivery_notification_order FOREIGN KEY (order_id) REFERENCES orders (id) ON DELETE CASCADE,
            CONSTRAINT fk_delivery_notification_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }

    public function down()
    {
        $this->_lava->call->dbforge();
        $db = $this->_lava->db;
        $db->raw('DROP TABLE IF EXISTS delivery_notification_outbox');
        $db->raw('DROP TABLE IF EXISTS delivery_proofs');
        if ($this->_lava->dbforge->column_exists('users', 'delivery_status_sms_opt_in')) {
            $db->raw('ALTER TABLE users DROP COLUMN delivery_status_sms_opt_in');
        }
    }
}
