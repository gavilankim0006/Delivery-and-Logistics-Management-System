<?php

class Create_customer_members
{
    private $_lava;

    public function __construct()
    {
        $this->_lava = lava_instance();
        $this->_lava->call->dbforge();
    }

    public function up(): void
    {
        if ($this->_lava->dbforge->table_exists('customer_members')) {
            return;
        }
        $this->_lava->db->raw("CREATE TABLE `customer_members` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `customer_id` BIGINT UNSIGNED NOT NULL,
            `user_id` BIGINT UNSIGNED NOT NULL,
            `created_by` BIGINT UNSIGNED NULL,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE INDEX `uq_customer_members_user` (`user_id`),
            INDEX `idx_customer_members_customer` (`customer_id`),
            CONSTRAINT `fk_customer_members_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE,
            CONSTRAINT `fk_customer_members_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
            CONSTRAINT `fk_customer_members_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }

    public function down(): void
    {
        $this->_lava->db->raw('DROP TABLE IF EXISTS `customer_members`');
    }
}
