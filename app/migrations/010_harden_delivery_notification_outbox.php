<?php

class Harden_delivery_notification_outbox
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
        if (!$this->_lava->dbforge->table_exists('delivery_notification_outbox')) {
            return;
        }

        $columns = $db->raw("SHOW COLUMNS FROM delivery_notification_outbox LIKE 'claimed_at'")->fetchAll(PDO::FETCH_ASSOC);
        if (!$columns) {
            $db->raw('ALTER TABLE delivery_notification_outbox ADD claimed_at DATETIME NULL AFTER provider_response');
        }
        $db->raw("ALTER TABLE delivery_notification_outbox MODIFY status ENUM('pending', 'sending', 'sent', 'failed') NOT NULL DEFAULT 'pending'");
        $db->raw("UPDATE delivery_notification_outbox
            SET status = 'failed', provider_response = 'Expired claim requires manual review.', claimed_at = NULL
            WHERE status = 'sending' AND claimed_at IS NULL");
        $indexes = $db->raw("SHOW INDEX FROM delivery_notification_outbox WHERE Key_name = 'idx_delivery_notification_claimed'")->fetchAll(PDO::FETCH_ASSOC);
        if (!$indexes) {
            $db->raw('ALTER TABLE delivery_notification_outbox ADD INDEX idx_delivery_notification_claimed (status, claimed_at)');
        }
    }

    public function down()
    {
        $this->_lava->call->dbforge();
        $db = $this->_lava->db;
        if (!$this->_lava->dbforge->table_exists('delivery_notification_outbox')) {
            return;
        }

        $db->raw("UPDATE delivery_notification_outbox SET status = 'failed', claimed_at = NULL WHERE status = 'sending'");
        $indexes = $db->raw("SHOW INDEX FROM delivery_notification_outbox WHERE Key_name = 'idx_delivery_notification_claimed'")->fetchAll(PDO::FETCH_ASSOC);
        if ($indexes) {
            $db->raw('ALTER TABLE delivery_notification_outbox DROP INDEX idx_delivery_notification_claimed');
        }
        $db->raw("ALTER TABLE delivery_notification_outbox MODIFY status ENUM('pending', 'sent', 'failed') NOT NULL DEFAULT 'pending'");
        $columns = $db->raw("SHOW COLUMNS FROM delivery_notification_outbox LIKE 'claimed_at'")->fetchAll(PDO::FETCH_ASSOC);
        if ($columns) {
            $db->raw('ALTER TABLE delivery_notification_outbox DROP COLUMN claimed_at');
        }
    }
}
