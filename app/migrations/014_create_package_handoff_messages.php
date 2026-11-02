<?php

class Create_package_handoff_messages
{
    private $_lava;
    public function __construct() { $this->_lava = lava_instance(); }

    public function up(): void
    {
        $this->_lava->db->raw("ALTER TABLE package_handoffs
            ADD COLUMN sender_read_message_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            ADD COLUMN recipient_read_message_id BIGINT UNSIGNED NOT NULL DEFAULT 0");
        $this->_lava->db->raw("CREATE TABLE IF NOT EXISTS package_handoff_messages (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            handoff_id BIGINT UNSIGNED NOT NULL,
            sender_customer_id INT NOT NULL,
            body TEXT NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_handoff_messages_handoff_id (handoff_id, id),
            CONSTRAINT fk_handoff_messages_handoff FOREIGN KEY (handoff_id) REFERENCES package_handoffs(id) ON DELETE CASCADE,
            CONSTRAINT fk_handoff_messages_sender FOREIGN KEY (sender_customer_id) REFERENCES customers(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }

    public function down(): void
    {
        $this->_lava->db->raw('DROP TABLE IF EXISTS package_handoff_messages');
        $this->_lava->db->raw('ALTER TABLE package_handoffs DROP COLUMN sender_read_message_id, DROP COLUMN recipient_read_message_id');
    }
}
