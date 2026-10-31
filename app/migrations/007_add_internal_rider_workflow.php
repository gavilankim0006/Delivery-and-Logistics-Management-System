<?php

class Add_internal_rider_workflow
{
    private $_lava;

    public function __construct()
    {
        $this->_lava = lava_instance();
        $this->_lava->call->dbforge();
    }

    public function up()
    {
        $db = $this->_lava->db;

        $db->raw("INSERT INTO roles (name, description)
            SELECT 'rider', 'Internal delivery rider with assigned-order access'
            WHERE NOT EXISTS (SELECT 1 FROM roles WHERE name = 'rider')");

        if (!$this->_lava->dbforge->column_exists('orders', 'fulfillment_mode')) {
            $db->raw("ALTER TABLE orders ADD fulfillment_mode ENUM('internal', 'lalamove') NULL AFTER rider_id");
        }
        if (!$this->_lava->dbforge->column_exists('orders', 'fulfillment_mode')) {
            throw new RuntimeException('Could not add orders.fulfillment_mode.');
        }
        $db->raw("UPDATE orders SET fulfillment_mode = 'lalamove'
            WHERE fulfillment_mode IS NULL AND lalamove_order_id IS NOT NULL");
        // Historical orders.rider_id is ambiguous in this schema; leave it for review.
        $riderLinks = $db->raw('SELECT user_id FROM riders WHERE user_id IS NOT NULL GROUP BY user_id HAVING COUNT(*) > 1')->fetchAll(PDO::FETCH_COLUMN);
        if ($riderLinks) {
            throw new RuntimeException('Resolve duplicate rider account links before applying the rider workflow migration.');
        }
        $indexes = $db->raw("SHOW INDEX FROM riders WHERE Key_name = 'uq_riders_user_id'")->fetchAll(PDO::FETCH_ASSOC);
        if (!$indexes) {
            $db->raw('ALTER TABLE riders ADD UNIQUE INDEX uq_riders_user_id (user_id)');
        }

        $db->raw("CREATE TABLE IF NOT EXISTS rider_assignments (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            order_id BIGINT UNSIGNED NOT NULL,
            rider_id BIGINT UNSIGNED NOT NULL,
            assigned_by BIGINT UNSIGNED NOT NULL,
            assigned_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            released_at DATETIME NULL,
            release_reason VARCHAR(255) NULL,
            INDEX idx_rider_assignments_active_rider (rider_id, released_at),
            INDEX idx_rider_assignments_active_order (order_id, released_at),
            CONSTRAINT fk_rider_assignments_order FOREIGN KEY (order_id) REFERENCES orders (id) ON DELETE CASCADE,
            CONSTRAINT fk_rider_assignments_rider FOREIGN KEY (rider_id) REFERENCES riders (id) ON DELETE RESTRICT,
            CONSTRAINT fk_rider_assignments_assigner FOREIGN KEY (assigned_by) REFERENCES users (id) ON DELETE RESTRICT
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }

    public function down()
    {
        $this->_lava->call->dbforge();
        $db = $this->_lava->db;
        $db->raw('DROP TABLE IF EXISTS rider_assignments');
        $indexes = $db->raw("SHOW INDEX FROM riders WHERE Key_name = 'uq_riders_user_id'")->fetchAll(PDO::FETCH_ASSOC);
        if ($indexes) {
            $db->raw('ALTER TABLE riders DROP INDEX uq_riders_user_id');
        }
        if ($this->_lava->dbforge->column_exists('orders', 'fulfillment_mode')) {
            $db->raw('ALTER TABLE orders DROP COLUMN fulfillment_mode');
        }
        $db->raw("DELETE FROM users WHERE role_id = (SELECT id FROM roles WHERE name = 'rider') AND deleted_at IS NULL");
        $db->raw("DELETE FROM roles WHERE name = 'rider'");
    }
}
