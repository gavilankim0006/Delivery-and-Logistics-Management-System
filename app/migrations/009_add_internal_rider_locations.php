<?php

class Add_internal_rider_locations
{
    private $_lava;

    public function __construct()
    {
        $this->_lava = lava_instance();
    }

    public function up()
    {
        $db = $this->_lava->db;
        $db->raw("CREATE TABLE IF NOT EXISTS rider_order_locations (
            order_id BIGINT UNSIGNED PRIMARY KEY,
            latitude DECIMAL(10, 8) NULL,
            longitude DECIMAL(11, 8) NULL,
            accuracy_m DECIMAL(8, 2) NULL,
            received_at DATETIME NULL,
            sharing_enabled TINYINT(1) NOT NULL DEFAULT 0,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            CONSTRAINT fk_rider_order_locations_order FOREIGN KEY (order_id) REFERENCES orders (id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }

    public function down()
    {
        $this->_lava->db->raw('DROP TABLE IF EXISTS rider_order_locations');
    }
}
