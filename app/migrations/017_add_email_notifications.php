<?php

class Add_email_notifications
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

        if (!$this->_lava->dbforge->column_exists('users', 'email_notifications_opt_in')) {
            $db->raw('ALTER TABLE users ADD email_notifications_opt_in TINYINT(1) NOT NULL DEFAULT 1 AFTER push_opt_in');
        }

        if ($this->_lava->dbforge->table_exists('delivery_notification_outbox')) {
            $db->raw("ALTER TABLE delivery_notification_outbox MODIFY channel ENUM('sms', 'push', 'email') NOT NULL DEFAULT 'sms'");
            $db->raw("ALTER TABLE delivery_notification_outbox MODIFY recipient VARCHAR(255) NOT NULL");
        }

        if ($this->_lava->dbforge->table_exists('notifications')) {
            $db->raw("ALTER TABLE notifications MODIFY channel ENUM('sms', 'push', 'email') NOT NULL");
        }
    }

    public function down()
    {
        $this->_lava->call->dbforge();
        $db = $this->_lava->db;

        if ($this->_lava->dbforge->column_exists('users', 'email_notifications_opt_in')) {
            $db->raw('ALTER TABLE users DROP COLUMN email_notifications_opt_in');
        }

        if ($this->_lava->dbforge->table_exists('delivery_notification_outbox')) {
            $db->raw("DELETE FROM delivery_notification_outbox WHERE channel = 'email'");
            $db->raw("ALTER TABLE delivery_notification_outbox MODIFY channel ENUM('sms', 'push') NOT NULL DEFAULT 'sms'");
        }

        if ($this->_lava->dbforge->table_exists('notifications')) {
            $db->raw("DELETE FROM notifications WHERE channel = 'email'");
            $db->raw("ALTER TABLE notifications MODIFY channel ENUM('sms', 'push') NOT NULL");
        }
    }
}
