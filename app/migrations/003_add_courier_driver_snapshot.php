<?php

class Add_courier_driver_snapshot
{
    private $_lava;

    public function __construct()
    {
        $this->_lava = lava_instance();
        $this->_lava->call->dbforge();
    }

    public function up()
    {
        $columns = [
            'courier_driver_id' => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => TRUE],
            'courier_driver_name' => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => TRUE],
            'courier_driver_phone' => ['type' => 'VARCHAR', 'constraint' => 30, 'null' => TRUE],
            'courier_driver_vehicle' => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => TRUE]
        ];
        foreach ($columns as $name => $definition) {
            if (!$this->_lava->dbforge->column_exists('orders', $name)) {
                $this->_lava->dbforge->add_column('orders', [$name => $definition]);
            }
        }
    }

    public function down()
    {
        foreach (['courier_driver_vehicle', 'courier_driver_phone', 'courier_driver_name', 'courier_driver_id'] as $name) {
            if ($this->_lava->dbforge->column_exists('orders', $name)) {
                $this->_lava->dbforge->drop_column('orders', $name);
            }
        }
    }
}
