<?php

class Add_soft_delete_columns
{
    private $_lava;

    public function __construct()
    {
        $this->_lava = lava_instance();
        $this->_lava->call->dbforge();
    }

    public function up()
    {
        // Login and the staff/rider/order models all expect these soft-delete markers.
        foreach (['orders', 'users', 'riders'] as $table) {
            if (!$this->_lava->dbforge->column_exists($table, 'deleted_at')) {
                $this->_lava->dbforge->add_column($table, [
                    'deleted_at' => ['type' => 'DATETIME', 'null' => TRUE]
                ]);
            }
        }
    }

    public function down()
    {
        foreach (['riders', 'users', 'orders'] as $table) {
            if ($this->_lava->dbforge->column_exists($table, 'deleted_at')) {
                $this->_lava->dbforge->drop_column($table, 'deleted_at');
            }
        }
    }
}
