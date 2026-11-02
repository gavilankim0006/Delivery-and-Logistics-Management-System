-- Delivery & Logistics Management System Database Schema
-- MySQL 8.0+ / MariaDB 10.4+ Compatible

SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS `dispatch_recovery_locks`;
DROP TABLE IF EXISTS `delivery_notification_outbox`;
DROP TABLE IF EXISTS `rider_order_locations`;
DROP TABLE IF EXISTS `delivery_proofs`;
DROP TABLE IF EXISTS `notifications`;
DROP TABLE IF EXISTS `rider_assignments`;
DROP TABLE IF EXISTS `delivery_change_requests`;
DROP TABLE IF EXISTS `delivery_control_passes`;
DROP TABLE IF EXISTS `service_areas`;
DROP TABLE IF EXISTS `notifications`;
DROP TABLE IF EXISTS `deliveries`;
DROP TABLE IF EXISTS `payments`;
DROP TABLE IF EXISTS `parcels`;
DROP TABLE IF EXISTS `orders`;
DROP TABLE IF EXISTS `addresses`;
DROP TABLE IF EXISTS `riders`;
DROP TABLE IF EXISTS `customers`;
DROP TABLE IF EXISTS `users`;
DROP TABLE IF EXISTS `roles`;
DROP TABLE IF EXISTS `api_logs`;
DROP TABLE IF EXISTS `webhook_events`;
DROP TABLE IF EXISTS `settings`;

SET FOREIGN_KEY_CHECKS = 1;

-- 1. Roles Table
CREATE TABLE `roles` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(50) NOT NULL UNIQUE,
    `description` VARCHAR(255) NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `roles` (`id`, `name`, `description`) VALUES
(1, 'admin', 'System Administrator'),
(2, 'dispatcher', 'Dispatcher managing live orders and assignments'),
(3, 'customer', 'Customer placing and tracking deliveries'),
(4, 'rider', 'Internal delivery rider with assigned-order access');

-- 2. Users Table
CREATE TABLE `users` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `role_id` INT UNSIGNED NOT NULL,
    `name` VARCHAR(100) NOT NULL,
    `email` VARCHAR(150) NOT NULL UNIQUE,
    `phone` VARCHAR(30) NULL,
    `password` VARCHAR(255) NOT NULL,
    `fcm_token` TEXT NULL,
    `sms_opt_in` TINYINT(1) NOT NULL DEFAULT 0,
    `delivery_status_sms_opt_in` TINYINT(1) NOT NULL DEFAULT 0,
    `push_opt_in` TINYINT(1) NOT NULL DEFAULT 0,
    `status` ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    `deleted_at` DATETIME NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_users_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Customers Table (one-to-one with users)
CREATE TABLE `customers` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` BIGINT UNSIGNED NOT NULL UNIQUE,
    `company_name` VARCHAR(150) NULL,
    `default_contact_phone` VARCHAR(30) NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_customers_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Riders Table (representing assigned delivery drivers)
CREATE TABLE `riders` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` BIGINT UNSIGNED NULL,
    `name` VARCHAR(100) NOT NULL,
    `phone` VARCHAR(30) NOT NULL,
    `vehicle_type` VARCHAR(50) NOT NULL DEFAULT 'Motorcycle',
    `plate_number` VARCHAR(30) NOT NULL,
    `status` ENUM('available', 'busy', 'offline') NOT NULL DEFAULT 'available',
    `deleted_at` DATETIME NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE INDEX `uq_riders_user_id` (`user_id`),
    CONSTRAINT `fk_riders_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Addresses Table
CREATE TABLE `addresses` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `customer_id` BIGINT UNSIGNED NULL,
    `label` VARCHAR(100) NULL,
    `address_line` TEXT NOT NULL,
    `latitude` DECIMAL(10, 8) NULL,
    `longitude` DECIMAL(11, 8) NULL,
    `contact_name` VARCHAR(100) NOT NULL,
    `contact_phone` VARCHAR(30) NOT NULL,
    `notes` TEXT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_addresses_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. Orders Table
CREATE TABLE `orders` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `order_no` VARCHAR(50) NOT NULL UNIQUE,
    `customer_id` BIGINT UNSIGNED NOT NULL,
    `rider_id` BIGINT UNSIGNED NULL,
    `fulfillment_mode` ENUM('internal', 'lalamove') NULL,
    `pickup_address_id` BIGINT UNSIGNED NOT NULL,
    `dropoff_address_id` BIGINT UNSIGNED NOT NULL,
    `status` ENUM('draft', 'pending_payment', 'paid', 'booking', 'rider_assigned', 'picked_up', 'delivered', 'cancelled', 'failed') NOT NULL DEFAULT 'draft',
    `distance_km` DECIMAL(8, 2) NOT NULL DEFAULT 0.00,
    `estimated_duration_min` INT NOT NULL DEFAULT 0,
    `delivery_fee` DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    `lalamove_quotation_id` VARCHAR(100) NULL,
    `lalamove_order_id` VARCHAR(100) NULL,
    `courier_driver_id` VARCHAR(100) NULL,
    `courier_driver_name` VARCHAR(100) NULL,
    `courier_driver_phone` VARCHAR(30) NULL,
    `courier_driver_vehicle` VARCHAR(50) NULL,
    `share_link` TEXT NULL,
    `cancel_reason` TEXT NULL,
    `deleted_at` DATETIME NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_orders_status` (`status`),
    INDEX `idx_orders_customer_id` (`customer_id`),
    CONSTRAINT `fk_orders_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_orders_rider` FOREIGN KEY (`rider_id`) REFERENCES `riders` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_orders_pickup` FOREIGN KEY (`pickup_address_id`) REFERENCES `addresses` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_orders_dropoff` FOREIGN KEY (`dropoff_address_id`) REFERENCES `addresses` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. Parcels Table
CREATE TABLE `parcels` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `order_id` BIGINT UNSIGNED NOT NULL,
    `description` VARCHAR(255) NOT NULL,
    `weight_kg` DECIMAL(6, 2) NOT NULL DEFAULT 1.00,
    `size` ENUM('S', 'M', 'L') NOT NULL DEFAULT 'M',
    `is_fragile` TINYINT(1) NOT NULL DEFAULT 0,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_parcels_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 8. Payments Table
CREATE TABLE `payments` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `order_id` BIGINT UNSIGNED NOT NULL,
    `paymongo_session_id` VARCHAR(150) NULL,
    `checkout_url` VARCHAR(1000) NULL,
    `paymongo_payment_id` VARCHAR(150) NULL,
    `amount` DECIMAL(10, 2) NOT NULL,
    `fee` DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    `payment_method` VARCHAR(50) NULL,
    `status` ENUM('pending', 'paid', 'failed', 'refunded', 'cancelled') NOT NULL DEFAULT 'pending',
    `paid_at` DATETIME NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_payments_session` (`paymongo_session_id`),
    INDEX `idx_payments_order` (`order_id`),
    CONSTRAINT `fk_payments_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 9. Deliveries (State Transitions / History Log) Table
CREATE TABLE `deliveries` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `order_id` BIGINT UNSIGNED NOT NULL,
    `from_status` VARCHAR(50) NULL,
    `to_status` VARCHAR(50) NOT NULL,
    `notes` TEXT NULL,
    `actor_type` VARCHAR(50) NOT NULL DEFAULT 'system',
    `actor_id` BIGINT UNSIGNED NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_deliveries_order_id` (`order_id`),
    CONSTRAINT `fk_deliveries_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 10. Notifications Table
CREATE TABLE `notifications` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` BIGINT UNSIGNED NOT NULL,
    `order_id` BIGINT UNSIGNED NULL,
    `channel` ENUM('sms', 'push') NOT NULL,
    `recipient` VARCHAR(255) NOT NULL,
    `message` TEXT NOT NULL,
    `status` ENUM('pending', 'sent', 'failed') NOT NULL DEFAULT 'pending',
    `provider_response` TEXT NULL,
    `sent_at` DATETIME NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_notifications_user_id` (`user_id`),
    INDEX `idx_notifications_order_id` (`order_id`),
    CONSTRAINT `fk_notifications_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_notifications_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 10a. Latest opt-in rider location, private delivery evidence and idempotent status notification outbox
CREATE TABLE `rider_order_locations` (
    `order_id` BIGINT UNSIGNED PRIMARY KEY,
    `latitude` DECIMAL(10, 8) NULL,
    `longitude` DECIMAL(11, 8) NULL,
    `accuracy_m` DECIMAL(8, 2) NULL,
    `received_at` DATETIME NULL,
    `sharing_enabled` TINYINT(1) NOT NULL DEFAULT 0,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_rider_order_locations_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 10b. Private delivery evidence and idempotent delivery-status notification outbox
CREATE TABLE `delivery_proofs` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `order_id` BIGINT UNSIGNED NOT NULL,
    `uploaded_by` BIGINT UNSIGNED NOT NULL,
    `recipient_name` VARCHAR(100) NOT NULL,
    `note` VARCHAR(500) NULL,
    `storage_key` VARCHAR(80) NOT NULL,
    `mime_type` VARCHAR(30) NOT NULL,
    `file_size` INT UNSIGNED NOT NULL,
    `sha256` CHAR(64) NOT NULL,
    `captured_at` DATETIME NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE INDEX `uq_delivery_proofs_order` (`order_id`),
    CONSTRAINT `fk_delivery_proofs_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_delivery_proofs_uploader` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `delivery_notification_outbox` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `order_id` BIGINT UNSIGNED NOT NULL,
    `user_id` BIGINT UNSIGNED NOT NULL,
    `status_code` VARCHAR(30) NOT NULL,
    `channel` ENUM('sms', 'push') NOT NULL DEFAULT 'sms',
    `recipient` VARCHAR(255) NOT NULL,
    `message` VARCHAR(500) NOT NULL,
    `status` ENUM('pending', 'sending', 'sent', 'failed') NOT NULL DEFAULT 'pending',
    `provider_response` TEXT NULL,
    `claimed_at` DATETIME NULL,
    `sent_at` DATETIME NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE INDEX `uq_delivery_notification_event` (`order_id`, `status_code`, `channel`),
    INDEX `idx_delivery_notification_pending` (`status`, `created_at`),
    INDEX `idx_delivery_notification_claimed` (`status`, `claimed_at`),
    CONSTRAINT `fk_delivery_notification_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_delivery_notification_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 11. Polygon delivery service areas and zone-specific fee rates
CREATE TABLE `service_areas` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL,
    `polygon_json` LONGTEXT NOT NULL,
    `base_delivery_fee` DECIMAL(10,2) NOT NULL DEFAULT 60.00,
    `free_distance_km` DECIMAL(8,2) NOT NULL DEFAULT 2.00,
    `per_km_rate` DECIMAL(10,2) NOT NULL DEFAULT 12.50,
    `free_weight_kg` DECIMAL(8,2) NOT NULL DEFAULT 1.00,
    `per_kg_rate` DECIMAL(10,2) NOT NULL DEFAULT 5.00,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_service_areas_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 12. Expiring customer delivery-control bearer tokens (hash only)
CREATE TABLE `delivery_control_passes` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `order_id` BIGINT UNSIGNED NOT NULL,
    `token_hash` CHAR(64) NOT NULL UNIQUE,
    `expires_at` DATETIME NOT NULL,
    `revoked_at` DATETIME NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_control_pass_order` (`order_id`),
    CONSTRAINT `fk_control_pass_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 13. Customer instructions and hold requests submitted through the control pass
CREATE TABLE `delivery_change_requests` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `order_id` BIGINT UNSIGNED NOT NULL,
    `request_type` ENUM('instruction','hold') NOT NULL,
    `request_text` TEXT NULL,
    `status` ENUM('pending','applied','accepted','rejected') NOT NULL DEFAULT 'pending',
    `source` VARCHAR(30) NOT NULL DEFAULT 'control_pass',
    `reviewed_by` BIGINT UNSIGNED NULL,
    `reviewed_at` DATETIME NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_change_requests_order_status` (`order_id`, `status`),
    CONSTRAINT `fk_change_request_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_change_request_reviewer` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 14. Internal rider assignment history
CREATE TABLE `rider_assignments` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `order_id` BIGINT UNSIGNED NOT NULL,
    `rider_id` BIGINT UNSIGNED NOT NULL,
    `assigned_by` BIGINT UNSIGNED NOT NULL,
    `assigned_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `released_at` DATETIME NULL,
    `release_reason` VARCHAR(255) NULL,
    INDEX `idx_rider_assignments_active_rider` (`rider_id`, `released_at`),
    INDEX `idx_rider_assignments_active_order` (`order_id`, `released_at`),
    CONSTRAINT `fk_rider_assignments_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_rider_assignments_rider` FOREIGN KEY (`rider_id`) REFERENCES `riders` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_rider_assignments_assigner` FOREIGN KEY (`assigned_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 15. Dispatcher recovery idempotency lock (short-lived during provider booking)
CREATE TABLE `dispatch_recovery_locks` (
    `order_id` BIGINT UNSIGNED PRIMARY KEY,
    `started_at` DATETIME NOT NULL,
    CONSTRAINT `fk_recovery_lock_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 15. API Logs Table
CREATE TABLE `api_logs` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `service` VARCHAR(50) NOT NULL,
    `method` VARCHAR(10) NOT NULL,
    `endpoint` VARCHAR(500) NOT NULL,
    `request_headers` TEXT NULL,
    `request_body` LONGTEXT NULL,
    `response_status` INT NULL,
    `response_headers` TEXT NULL,
    `response_body` LONGTEXT NULL,
    `execution_time_ms` INT UNSIGNED NULL,
    `error_message` TEXT NULL,
    `attempt` TINYINT UNSIGNED NOT NULL DEFAULT 1,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_api_logs_service` (`service`),
    INDEX `idx_api_logs_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 12. Webhook Events Table (Idempotency storage)
CREATE TABLE `webhook_events` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `provider` VARCHAR(50) NOT NULL,
    `event_id` VARCHAR(150) NOT NULL,
    `event_type` VARCHAR(100) NULL,
    `payload` LONGTEXT NOT NULL,
    `status` ENUM('received', 'processed', 'ignored', 'failed') NOT NULL DEFAULT 'received',
    `processed_at` DATETIME NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE INDEX `idx_webhook_provider_event` (`provider`, `event_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 13. System Settings Table (Fee rates & config)
CREATE TABLE `settings` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `setting_key` VARCHAR(100) NOT NULL UNIQUE,
    `setting_value` TEXT NOT NULL,
    `description` VARCHAR(255) NULL,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 16. AI Assistant Conversations & Message History
CREATE TABLE `ai_conversations` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `session_id` VARCHAR(64) NOT NULL,
    `user_id` BIGINT UNSIGNED NULL,
    `role` VARCHAR(32) NOT NULL DEFAULT 'guest',
    `title` VARCHAR(255) NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_ai_conversations_session` (`session_id`),
    INDEX `idx_ai_conversations_user` (`user_id`),
    CONSTRAINT `fk_ai_conversations_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `ai_messages` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `conversation_id` BIGINT UNSIGNED NOT NULL,
    `sender` ENUM('user', 'assistant', 'system', 'tool') NOT NULL,
    `content` MEDIUMTEXT NOT NULL,
    `tool_calls` JSON NULL,
    `tool_call_id` VARCHAR(64) NULL,
    `tool_name` VARCHAR(64) NULL,
    `tokens` INT UNSIGNED NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_ai_messages_conversation` (`conversation_id`),
    CONSTRAINT `fk_ai_messages_conversation` FOREIGN KEY (`conversation_id`) REFERENCES `ai_conversations` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 17. Careers & Job Applications with resilient document storage
CREATE TABLE IF NOT EXISTS `job_applications` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `full_name` VARCHAR(150) NOT NULL,
    `email` VARCHAR(254) NOT NULL,
    `phone` VARCHAR(40) NOT NULL,
    `role_applied` ENUM('rider', 'dispatcher') NOT NULL,
    `location` VARCHAR(180) NOT NULL,
    `experience` TEXT NOT NULL,
    `availability` VARCHAR(180) NOT NULL,
    `id_type` VARCHAR(100) NOT NULL,
    `id_number` VARCHAR(100) NOT NULL,
    `id_storage_key` VARCHAR(80) NOT NULL,
    `id_mime_type` VARCHAR(100) NOT NULL,
    `id_original_name` VARCHAR(255) NOT NULL,
    `id_file_data` LONGBLOB NULL,
    `status` ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending',
    `admin_note` TEXT NULL,
    `reviewed_by` INT NULL,
    `reviewed_at` DATETIME NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_job_applications_status_created` (`status`, `created_at`),
    CONSTRAINT `fk_job_applications_reviewer` FOREIGN KEY (`reviewed_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


