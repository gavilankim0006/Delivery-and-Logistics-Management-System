<?php

class Create_order_templates
{
    private $_lava;

    public function __construct()
    {
        $this->_lava = lava_instance();
        $this->_lava->call->dbforge();
    }

    public function up(): void
    {
        if ($this->_lava->dbforge->table_exists('order_templates')) {
            return;
        }
        $this->_lava->db->raw("CREATE TABLE `order_templates` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `customer_id` BIGINT UNSIGNED NOT NULL,
            `name` VARCHAR(100) NOT NULL,
            `pickup_address` VARCHAR(500) NOT NULL,
            `pickup_unit_number` VARCHAR(100) NULL,
            `pickup_street` VARCHAR(255) NULL,
            `pickup_barangay` VARCHAR(255) NULL,
            `pickup_city` VARCHAR(255) NULL,
            `pickup_province` VARCHAR(255) NULL,
            `pickup_latitude` DECIMAL(10,7) NULL,
            `pickup_longitude` DECIMAL(10,7) NULL,
            `pickup_contact_name` VARCHAR(100) NOT NULL,
            `pickup_contact_phone` VARCHAR(30) NOT NULL,
            `pickup_notes` VARCHAR(300) NULL,
            `dropoff_address` VARCHAR(500) NOT NULL,
            `dropoff_unit_number` VARCHAR(100) NULL,
            `dropoff_street` VARCHAR(255) NULL,
            `dropoff_barangay` VARCHAR(255) NULL,
            `dropoff_city` VARCHAR(255) NULL,
            `dropoff_province` VARCHAR(255) NULL,
            `dropoff_latitude` DECIMAL(10,7) NULL,
            `dropoff_longitude` DECIMAL(10,7) NULL,
            `dropoff_contact_name` VARCHAR(100) NOT NULL,
            `dropoff_contact_phone` VARCHAR(30) NOT NULL,
            `dropoff_notes` VARCHAR(300) NULL,
            `parcel_description` VARCHAR(150) NOT NULL,
            `parcel_weight_kg` DECIMAL(8,2) NOT NULL DEFAULT 1.00,
            `parcel_size` ENUM('S','M','L') NOT NULL DEFAULT 'M',
            `is_fragile` TINYINT(1) NOT NULL DEFAULT 0,
            `distance_km` DECIMAL(8,2) NOT NULL DEFAULT 0.00,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            INDEX `idx_order_templates_customer` (`customer_id`, `id`),
            CONSTRAINT `fk_order_templates_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }

    public function down(): void
    {
        $this->_lava->db->raw('DROP TABLE IF EXISTS `order_templates`');
    }
}
