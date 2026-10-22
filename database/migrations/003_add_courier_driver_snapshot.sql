-- Apply once to an existing database before running courier event processing.
-- This migration is idempotent for MySQL/MariaDB versions supporting ADD COLUMN IF NOT EXISTS.
ALTER TABLE `orders`
    ADD COLUMN IF NOT EXISTS `courier_driver_id` VARCHAR(100) NULL AFTER `lalamove_order_id`,
    ADD COLUMN IF NOT EXISTS `courier_driver_name` VARCHAR(100) NULL AFTER `courier_driver_id`,
    ADD COLUMN IF NOT EXISTS `courier_driver_phone` VARCHAR(30) NULL AFTER `courier_driver_name`,
    ADD COLUMN IF NOT EXISTS `courier_driver_vehicle` VARCHAR(50) NULL AFTER `courier_driver_phone`;
