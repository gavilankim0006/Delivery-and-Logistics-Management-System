<?php

class Create_user_notifications
{
    private $_lava;

    public function __construct()
    {
        $this->_lava = lava_instance();
        $this->_lava->call->dbforge();
    }

    public function up(): void
    {
        if ($this->_lava->dbforge->table_exists('user_notifications')) {
            return;
        }
        $this->_lava->db->raw("CREATE TABLE `user_notifications` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `user_id` BIGINT UNSIGNED NOT NULL,
            `order_id` BIGINT UNSIGNED NULL,
            `type` VARCHAR(40) NOT NULL,
            `title` VARCHAR(150) NOT NULL,
            `body` VARCHAR(500) NOT NULL,
            `read_at` DATETIME NULL,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            INDEX `idx_user_notifications_feed` (`user_id`, `id`),
            INDEX `idx_user_notifications_unread` (`user_id`, `read_at`),
            CONSTRAINT `fk_user_notifications_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
            CONSTRAINT `fk_user_notifications_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }

    public function down(): void
    {
        $this->_lava->db->raw('DROP TABLE IF EXISTS `user_notifications`');
    }
}
