<?php

class Add_payment_checkout_and_sms_opt_in
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
        if (!$this->_lava->dbforge->column_exists('payments', 'checkout_url')) {
            $db->raw('ALTER TABLE payments ADD checkout_url VARCHAR(1000) NULL AFTER paymongo_session_id');
        }
        if (!$this->_lava->dbforge->column_exists('users', 'sms_opt_in')) {
            $db->raw('ALTER TABLE users ADD sms_opt_in TINYINT(1) NOT NULL DEFAULT 0 AFTER fcm_token');
        }
    }

    public function down()
    {
        $this->_lava->call->dbforge();
        $db = $this->_lava->db;
        if ($this->_lava->dbforge->column_exists('users', 'sms_opt_in')) {
            $db->raw('ALTER TABLE users DROP COLUMN sms_opt_in');
        }
        if ($this->_lava->dbforge->column_exists('payments', 'checkout_url')) {
            $db->raw('ALTER TABLE payments DROP COLUMN checkout_url');
        }
    }
}
