<?php

class Create_delivery_ratings
{
    private $_lava;

    public function __construct()
    {
        $this->_lava = lava_instance();
        $this->_lava->call->dbforge();
    }

    public function up(): void
    {
        if ($this->_lava->dbforge->table_exists('delivery_ratings')) {
            return;
        }
        $this->_lava->db->raw("CREATE TABLE `delivery_ratings` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `order_id` BIGINT UNSIGNED NOT NULL,
            `customer_id` BIGINT UNSIGNED NOT NULL,
            `rider_id` BIGINT UNSIGNED NULL,
            `rating` TINYINT UNSIGNED NOT NULL,
            `comment` VARCHAR(500) NULL,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE INDEX `uq_delivery_ratings_order` (`order_id`),
            INDEX `idx_delivery_ratings_rider` (`rider_id`),
            INDEX `idx_delivery_ratings_customer` (`customer_id`),
            CONSTRAINT `fk_delivery_ratings_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
            CONSTRAINT `fk_delivery_ratings_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE,
            CONSTRAINT `fk_delivery_ratings_rider` FOREIGN KEY (`rider_id`) REFERENCES `riders` (`id`) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }

    public function down(): void
    {
        $this->_lava->db->raw('DROP TABLE IF EXISTS `delivery_ratings`');
    }
}
