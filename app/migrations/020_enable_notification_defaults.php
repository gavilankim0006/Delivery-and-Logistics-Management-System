<?php

class Enable_notification_defaults
{
    private $_lava;

    public function __construct()
    {
        $this->_lava = lava_instance();
        $this->_lava->call->dbforge();
    }

    public function up(): void
    {
        $db = $this->_lava->db;

        // New accounts start with every customer notification channel enabled.
        foreach ([
            'sms_opt_in',
            'delivery_status_sms_opt_in',
            'push_opt_in',
            'email_notifications_opt_in',
        ] as $column) {
            if ($this->_lava->dbforge->column_exists('users', $column)) {
                $db->raw("ALTER TABLE users MODIFY {$column} TINYINT(1) NOT NULL DEFAULT 1");
            }
        }
    }

    public function down(): void
    {
        $db = $this->_lava->db;

        foreach ([
            'sms_opt_in',
            'delivery_status_sms_opt_in',
            'push_opt_in',
        ] as $column) {
            if ($this->_lava->dbforge->column_exists('users', $column)) {
                $db->raw("ALTER TABLE users MODIFY {$column} TINYINT(1) NOT NULL DEFAULT 0");
            }
        }

        if ($this->_lava->dbforge->column_exists('users', 'email_notifications_opt_in')) {
            $db->raw('ALTER TABLE users MODIFY email_notifications_opt_in TINYINT(1) NOT NULL DEFAULT 1');
        }
    }
}
