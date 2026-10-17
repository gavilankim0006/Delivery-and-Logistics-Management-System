-- Delivery & Logistics Management System Seed Data
-- 1 Admin, 1 Dispatcher, 3 Customers, 3 Riders, 10 Orders, Settings

SET FOREIGN_KEY_CHECKS = 0;

TRUNCATE TABLE `notifications`;
TRUNCATE TABLE `deliveries`;
TRUNCATE TABLE `payments`;
TRUNCATE TABLE `parcels`;
TRUNCATE TABLE `orders`;
TRUNCATE TABLE `addresses`;
TRUNCATE TABLE `riders`;
TRUNCATE TABLE `customers`;
TRUNCATE TABLE `users`;
TRUNCATE TABLE `roles`;
TRUNCATE TABLE `settings`;

SET FOREIGN_KEY_CHECKS = 1;

-- 1. Roles
INSERT INTO `roles` (`id`, `name`, `description`) VALUES
(1, 'admin', 'System Administrator with full access to settings, reports, and management'),
(2, 'dispatcher', 'Dispatcher managing live orders, assignments, and rebooking'),
(3, 'customer', 'Registered customer placing and tracking deliveries');

-- 2. Users (Default password for all seeded users is: password123)
-- Hash: $2y$10$9tw14F8Ux6khgDBY28OuWOPEb.H9FjlgcXuBUbKrpvDLdWb809k4q
INSERT INTO `users` (`id`, `role_id`, `name`, `email`, `phone`, `password`, `status`, `created_at`) VALUES
(1, 1, 'System Administrator', 'admin@delivery.local', '+639171234501', '$2y$10$9tw14F8Ux6khgDBY28OuWOPEb.H9FjlgcXuBUbKrpvDLdWb809k4q', 'active', '2026-10-01 08:00:00'),
(2, 2, 'Lead Dispatcher', 'dispatcher@delivery.local', '+639171234502', '$2y$10$9tw14F8Ux6khgDBY28OuWOPEb.H9FjlgcXuBUbKrpvDLdWb809k4q', 'active', '2026-10-01 08:30:00'),
(3, 3, 'Maria Santos', 'maria@example.com', '+639171234503', '$2y$10$9tw14F8Ux6khgDBY28OuWOPEb.H9FjlgcXuBUbKrpvDLdWb809k4q', 'active', '2026-10-02 09:00:00'),
(4, 3, 'Juan Dela Cruz', 'juan@example.com', '+639171234504', '$2y$10$9tw14F8Ux6khgDBY28OuWOPEb.H9FjlgcXuBUbKrpvDLdWb809k4q', 'active', '2026-10-02 10:15:00'),
(5, 3, 'Ana Reyes', 'ana@example.com', '+639171234505', '$2y$10$9tw14F8Ux6khgDBY28OuWOPEb.H9FjlgcXuBUbKrpvDLdWb809k4q', 'active', '2026-10-03 11:20:00');

-- 3. Customers
INSERT INTO `customers` (`id`, `user_id`, `company_name`, `default_contact_phone`, `created_at`) VALUES
(1, 3, 'Santos General Merchandise', '+639171234503', '2026-10-02 09:00:00'),
(2, 4, 'Dela Cruz Tech Supplies', '+639171234504', '2026-10-02 10:15:00'),
(3, 5, 'Reyes Bakery & Cafe', '+639171234505', '2026-10-03 11:20:00');

-- 4. Riders
INSERT INTO `riders` (`id`, `user_id`, `name`, `phone`, `vehicle_type`, `plate_number`, `status`, `created_at`) VALUES
(1, NULL, 'Carlos Mendoza', '+639181112201', 'Motorcycle', 'MC-1001', 'busy', '2026-10-01 09:00:00'),
(2, NULL, 'Danilo Ramos', '+639181112202', 'Motorcycle', 'MC-2002', 'available', '2026-10-01 09:15:00'),
(3, NULL, 'Eduardo Cruz', '+639181112203', 'Sedan', 'CAR-3003', 'available', '2026-10-01 09:30:00');

-- 5. Addresses
INSERT INTO `addresses` (`id`, `customer_id`, `label`, `address_line`, `latitude`, `longitude`, `contact_name`, `contact_phone`, `notes`) VALUES
(1, 1, 'Office HQ', 'Makati CBD Hub, Ayala Avenue, Makati City', 14.55470000, 121.02440000, 'Maria Santos', '+639171234503', 'Ground floor reception'),
(2, 1, 'Branch Store', 'BGC High Street, 5th Avenue, Taguig City', 14.51760000, 121.05090000, 'Pedro Santos', '+639175550101', 'Near parking entrance'),
(3, 2, 'Main Warehouse', 'Ortigas Center, San Miguel Ave, Pasig City', 14.58690000, 121.06140000, 'Juan Dela Cruz', '+639171234504', 'Loading bay 2'),
(4, 2, 'Retail Partner', 'Eastwood City Cyberpark, Libis, Quezon City', 14.61050000, 121.08050000, 'Lito Dela Cruz', '+639175550202', '3rd floor electronics dept'),
(5, 3, 'Bakery Kitchen', 'Greenhills Shopping Center, San Juan City', 14.60250000, 121.05030000, 'Ana Reyes', '+639171234505', 'Service alley gate 4'),
(6, 3, 'Alabang Outlet', 'Alabang Town Center, Muntinlupa City', 14.42530000, 121.03150000, 'Grace Reyes', '+639175550303', 'Food hall stall 12'),
(7, NULL, 'Distribution Point', 'SM Mall of Asia Complex, Pasay City', 14.53520000, 120.98260000, 'Central Logistics', '+639179998801', 'North parking bay'),
(8, NULL, 'North Depot', 'Quezon Memorial Circle, Diliman, Quezon City', 14.65170000, 121.04940000, 'QC Dispatch Hub', '+639179998802', 'Admin booth');

-- 6. Orders (10 orders across various lifecycle statuses)
INSERT INTO `orders` (`id`, `order_no`, `customer_id`, `rider_id`, `pickup_address_id`, `dropoff_address_id`, `status`, `distance_km`, `estimated_duration_min`, `delivery_fee`, `lalamove_quotation_id`, `lalamove_order_id`, `share_link`, `cancel_reason`, `created_at`, `updated_at`) VALUES
-- 1. draft
(1, 'DLV-20261017-0001', 1, NULL, 1, 2, 'draft', 5.20, 18, 120.00, NULL, NULL, NULL, NULL, '2026-10-17 08:15:00', '2026-10-17 08:15:00'),
-- 2. pending_payment
(2, 'DLV-20261017-0002', 1, NULL, 2, 3, 'pending_payment', 8.10, 26, 165.00, NULL, NULL, NULL, NULL, '2026-10-17 08:30:00', '2026-10-17 08:35:00'),
-- 3. paid
(3, 'DLV-20261017-0003', 2, NULL, 3, 4, 'paid', 6.50, 22, 140.00, NULL, NULL, NULL, NULL, '2026-10-17 08:45:00', '2026-10-17 08:50:00'),
-- 4. booking
(4, 'DLV-20261017-0004', 2, NULL, 4, 5, 'booking', 4.20, 15, 110.00, 'QUO-20261017-001', NULL, NULL, NULL, '2026-10-17 09:00:00', '2026-10-17 09:05:00'),
-- 5. rider_assigned
(5, 'DLV-20261017-0005', 3, 1, 1, 3, 'rider_assigned', 11.40, 35, 195.00, 'QUO-20261017-002', 'LLM-20261017-001', 'https://share.sandbox.lalamove.com/track/LLM-001', NULL, '2026-10-17 09:10:00', '2026-10-17 09:20:00'),
-- 6. picked_up
(6, 'DLV-20261017-0006', 3, 1, 5, 6, 'picked_up', 18.20, 52, 280.00, 'QUO-20261017-003', 'LLM-20261017-002', 'https://share.sandbox.lalamove.com/track/LLM-002', NULL, '2026-10-17 09:15:00', '2026-10-17 09:40:00'),
-- 7. delivered
(7, 'DLV-20261017-0007', 1, 2, 7, 1, 'delivered', 7.30, 24, 150.00, 'QUO-20261017-004', 'LLM-20261017-003', 'https://share.sandbox.lalamove.com/track/LLM-003', NULL, '2026-10-16 10:00:00', '2026-10-16 11:10:00'),
-- 8. delivered
(8, 'DLV-20261017-0008', 2, 3, 8, 3, 'delivered', 9.50, 30, 175.00, 'QUO-20261017-005', 'LLM-20261017-004', 'https://share.sandbox.lalamove.com/track/LLM-004', NULL, '2026-10-16 13:00:00', '2026-10-16 14:15:00'),
-- 9. cancelled
(9, 'DLV-20261017-0009', 3, NULL, 2, 7, 'cancelled', 12.00, 38, 210.00, NULL, NULL, NULL, 'Customer requested cancellation prior to pickup', '2026-10-17 07:00:00', '2026-10-17 07:20:00'),
-- 10. failed
(10, 'DLV-20261017-0010', 1, NULL, 4, 6, 'failed', 22.50, 65, 320.00, 'QUO-20261017-006', NULL, NULL, 'Courier dispatch timed out in sandbox', '2026-10-17 07:30:00', '2026-10-17 07:45:00');

-- 7. Parcels
INSERT INTO `parcels` (`id`, `order_id`, `description`, `weight_kg`, `size`, `is_fragile`, `created_at`) VALUES
(1, 1, 'Document pouches and office supplies', 1.20, 'S', 0, '2026-10-17 08:15:00'),
(2, 2, 'Assorted retail merchandise', 3.50, 'M', 0, '2026-10-17 08:30:00'),
(3, 3, 'Replacement computer motherboard', 2.10, 'M', 1, '2026-10-17 08:45:00'),
(4, 4, 'Cat6 Network cabling roll', 5.00, 'L', 0, '2026-10-17 09:00:00'),
(5, 5, 'Specialty coffee beans (4 packs)', 2.00, 'S', 0, '2026-10-17 09:10:00'),
(6, 6, 'Fresh artisanal pastries in box', 4.00, 'L', 1, '2026-10-17 09:15:00'),
(7, 7, 'Retail fashion accessories', 1.50, 'S', 0, '2026-10-16 10:00:00'),
(8, 8, 'Laser printer toner cartridges', 3.20, 'M', 0, '2026-10-16 13:00:00'),
(9, 9, 'Ceramic mugs in gift boxes', 2.80, 'M', 1, '2026-10-17 07:00:00'),
(10, 10, 'Heavy hardware components', 8.50, 'L', 0, '2026-10-17 07:30:00');

-- 8. Payments
INSERT INTO `payments` (`id`, `order_id`, `paymongo_session_id`, `paymongo_payment_id`, `amount`, `fee`, `payment_method`, `status`, `paid_at`, `created_at`, `updated_at`) VALUES
(1, 2, 'cs_test_session_002', NULL, 165.00, 5.00, 'gcash', 'pending', NULL, '2026-10-17 08:35:00', '2026-10-17 08:35:00'),
(2, 3, 'cs_test_session_003', 'pay_test_003', 140.00, 4.20, 'gcash', 'paid', '2026-10-17 08:50:00', '2026-10-17 08:45:00', '2026-10-17 08:50:00'),
(3, 4, 'cs_test_session_004', 'pay_test_004', 110.00, 3.50, 'paymaya', 'paid', '2026-10-17 09:02:00', '2026-10-17 09:00:00', '2026-10-17 09:02:00'),
(4, 5, 'cs_test_session_005', 'pay_test_005', 195.00, 6.00, 'card', 'paid', '2026-10-17 09:12:00', '2026-10-17 09:10:00', '2026-10-17 09:12:00'),
(5, 6, 'cs_test_session_006', 'pay_test_006', 280.00, 8.50, 'gcash', 'paid', '2026-10-17 09:20:00', '2026-10-17 09:15:00', '2026-10-17 09:20:00'),
(6, 7, 'cs_test_session_007', 'pay_test_007', 150.00, 4.50, 'gcash', 'paid', '2026-10-16 10:05:00', '2026-10-16 10:00:00', '2026-10-16 10:05:00'),
(7, 8, 'cs_test_session_008', 'pay_test_008', 175.00, 5.25, 'card', 'paid', '2026-10-16 13:08:00', '2026-10-16 13:00:00', '2026-10-16 13:08:00'),
(8, 10, 'cs_test_session_010', 'pay_test_010', 320.00, 9.60, 'gcash', 'paid', '2026-10-17 07:35:00', '2026-10-17 07:30:00', '2026-10-17 07:35:00');

-- 9. Deliveries (State Transitions)
INSERT INTO `deliveries` (`order_id`, `from_status`, `to_status`, `notes`, `actor_type`, `created_at`) VALUES
(1, NULL, 'draft', 'Order created by customer', 'customer', '2026-10-17 08:15:00'),
(2, 'draft', 'pending_payment', 'Awaiting payment from customer via PayMongo', 'customer', '2026-10-17 08:35:00'),
(3, 'pending_payment', 'paid', 'Payment received via GCash', 'paymongo_webhook', '2026-10-17 08:50:00'),
(4, 'paid', 'booking', 'Lalamove quotation generated; placing courier booking', 'system', '2026-10-17 09:05:00'),
(5, 'paid', 'booking', 'Quotation received from Lalamove', 'system', '2026-10-17 09:15:00'),
(5, 'booking', 'rider_assigned', 'Carlos Mendoza assigned (Plate: MC-1001)', 'lalamove_webhook', '2026-10-17 09:20:00'),
(6, 'booking', 'rider_assigned', 'Carlos Mendoza assigned to order', 'lalamove_webhook', '2026-10-17 09:25:00'),
(6, 'rider_assigned', 'picked_up', 'Rider arrived at pickup and received parcel', 'lalamove_webhook', '2026-10-17 09:40:00'),
(7, 'rider_assigned', 'picked_up', 'Parcel collected from MOA', 'lalamove_webhook', '2026-10-16 10:25:00'),
(7, 'picked_up', 'delivered', 'Parcel successfully handed to Maria Santos', 'lalamove_webhook', '2026-10-16 11:10:00'),
(8, 'rider_assigned', 'picked_up', 'Picked up from Quezon Memorial depot', 'lalamove_webhook', '2026-10-16 13:30:00'),
(8, 'picked_up', 'delivered', 'Delivered to Ortigas Center', 'lalamove_webhook', '2026-10-16 14:15:00'),
(9, 'draft', 'cancelled', 'Customer cancelled prior to checkout', 'customer', '2026-10-17 07:20:00'),
(10, 'booking', 'failed', 'Lalamove driver assignment timeout', 'system', '2026-10-17 07:45:00');

-- 10. Settings (Fee rates & system config)
INSERT INTO `settings` (`setting_key`, `setting_value`, `description`) VALUES
('base_delivery_fee', '60.00', 'Base delivery fee charged up to free distance limit in PHP'),
('per_km_rate', '12.50', 'Rate charged per kilometer beyond free distance in PHP'),
('per_kg_rate', '5.00', 'Rate charged per kilogram beyond free weight in PHP'),
('free_distance_km', '2.0', 'Distance in km covered by base delivery fee'),
('free_weight_kg', '1.0', 'Weight in kg covered by base delivery fee'),
('support_email', 'support@delivery.local', 'Public support contact email displayed in customer notifications'),
('support_phone', '+63 2 8123 4567', 'Dispatcher hotline phone number');
