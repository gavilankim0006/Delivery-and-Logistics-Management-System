<?php

class Create_order_tracking_links
{
    private $_lava;

    public function __construct()
    {
        $this->_lava = lava_instance();
        $this->_lava->call->dbforge();
    }

    public function up(): void
    {
        if ($this->_lava->dbforge->table_exists('order_tracking_links')) {
            return;
        }
        $this->_lava->db->raw("CREATE TABLE `order_tracking_links` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `order_id` BIGINT UNSIGNED NOT NULL,
            `token_hash` CHAR(64) NOT NULL,
            `token_cipher` TEXT NULL,
            `expires_at` DATETIME NOT NULL,
            `revoked_at` DATETIME NULL,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE INDEX `uq_order_tracking_links_token` (`token_hash`),
            INDEX `idx_order_tracking_links_order` (`order_id`, `revoked_at`),
            CONSTRAINT `fk_order_tracking_links_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }

    public function down(): void
    {
        $this->_lava->db->raw('DROP TABLE IF EXISTS `order_tracking_links`');
    }
}
