<?php

class Add_firebase_push_notifications
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

        if (!$this->_lava->dbforge->column_exists('users', 'push_opt_in')) {
            $db->raw('ALTER TABLE users ADD push_opt_in TINYINT(1) NOT NULL DEFAULT 0 AFTER delivery_status_sms_opt_in');
        }

        if ($this->_lava->dbforge->table_exists('delivery_notification_outbox')) {
            $db->raw("ALTER TABLE delivery_notification_outbox MODIFY channel ENUM('sms', 'push') NOT NULL DEFAULT 'sms'");
            $db->raw("ALTER TABLE delivery_notification_outbox MODIFY recipient VARCHAR(255) NOT NULL");

            $indexes = $db->raw("SHOW INDEX FROM delivery_notification_outbox WHERE Key_name = 'uq_delivery_notification_event'")->fetchAll(PDO::FETCH_ASSOC);
            if (!$indexes) {
                $db->raw('ALTER TABLE delivery_notification_outbox ADD UNIQUE INDEX uq_delivery_notification_event (order_id, status_code, channel)');
            }
        }
    }

    public function down()
    {
        $this->_lava->call->dbforge();
        $db = $this->_lava->db;

        if ($this->_lava->dbforge->column_exists('users', 'push_opt_in')) {
            $db->raw('ALTER TABLE users DROP COLUMN push_opt_in');
        }

        if ($this->_lava->dbforge->table_exists('delivery_notification_outbox')) {
            $db->raw("DELETE FROM delivery_notification_outbox WHERE channel = 'push'");
            $db->raw("ALTER TABLE delivery_notification_outbox MODIFY channel ENUM('sms') NOT NULL DEFAULT 'sms'");
            $db->raw("ALTER TABLE delivery_notification_outbox MODIFY recipient VARCHAR(30) NOT NULL");
        }
    }
}
