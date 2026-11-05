<?php

class Add_structured_address_columns
{
    private $_lava;

    public function __construct()
    {
        $this->_lava = lava_instance();
        $this->_lava->call->dbforge();
    }

    public function up(): void
    {
        if (!$this->_lava->dbforge->table_exists('addresses')) {
            return;
        }

        $db = $this->_lava->db;
        $columns = [
            'unit_number' => 'VARCHAR(100) NULL',
            'street' => 'VARCHAR(255) NULL',
            'barangay' => 'VARCHAR(255) NULL',
            'city' => 'VARCHAR(255) NULL',
            'province' => 'VARCHAR(255) NULL',
        ];

        foreach ($columns as $column => $definition) {
            if (!$this->_lava->dbforge->column_exists('addresses', $column)) {
                $db->raw("ALTER TABLE addresses ADD COLUMN `{$column}` {$definition}");
            }
        }
    }

    public function down(): void
    {
        if (!$this->_lava->dbforge->table_exists('addresses')) {
            return;
        }

        $db = $this->_lava->db;
        foreach (['province', 'city', 'barangay', 'street', 'unit_number'] as $column) {
            if ($this->_lava->dbforge->column_exists('addresses', $column)) {
                $db->raw("ALTER TABLE addresses DROP COLUMN `{$column}`");
            }
        }
    }
}
