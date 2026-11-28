-- ============================================================================
-- Fresh Produce Ordering System - Production MySQL Install Script (cPanel)
-- Import this file into your cPanel MySQL database via phpMyAdmin.
-- ============================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- 1. phinxlog (tracks applied migrations if Phinx CLI is ever used later)
CREATE TABLE IF NOT EXISTS `phinxlog` (
  `version` bigint NOT NULL,
  `migration_name` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `start_time` timestamp NULL DEFAULT NULL,
  `end_time` timestamp NULL DEFAULT NULL,
  `breakpoint` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`version`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. users
CREATE TABLE IF NOT EXISTS `users` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `role` enum('customer','company','supplier') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'customer',
  `company_name` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `contact_name` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `phone` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `location` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `password_hash` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email_verified_at` datetime DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_users_email` (`email`),
  KEY `idx_users_role_active` (`role`,`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. email_verifications
CREATE TABLE IF NOT EXISTS `email_verifications` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int unsigned NOT NULL,
  `token_hash` char(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `expires_at` datetime NOT NULL,
  `used_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_email_verifications_token_hash` (`token_hash`),
  KEY `idx_email_verifications_user_id` (`user_id`),
  CONSTRAINT `fk_email_verifications_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. password_resets
CREATE TABLE IF NOT EXISTS `password_resets` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int unsigned NOT NULL,
  `hashed_token` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `expiry_date` datetime NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_token` (`hashed_token`),
  KEY `idx_password_resets_user_id` (`user_id`),
  KEY `idx_password_resets_expiry_date` (`expiry_date`),
  CONSTRAINT `fk_password_resets_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. categories
CREATE TABLE IF NOT EXISTS `categories` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `sort_order` int NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_categories_name` (`name`),
  UNIQUE KEY `uq_categories_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. units
CREATE TABLE IF NOT EXISTS `units` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `allow_decimal` tinyint(1) NOT NULL DEFAULT 0,
  `step_size` decimal(4,2) NOT NULL DEFAULT 1.00,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_units_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. products
CREATE TABLE IF NOT EXISTS `products` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `category_id` int unsigned NOT NULL,
  `unit_id` int unsigned NOT NULL,
  `sku` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `image_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `unit_price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `stock_quantity` decimal(10,2) NOT NULL DEFAULT 100.00,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_products_sku` (`sku`),
  UNIQUE KEY `uq_products_slug` (`slug`),
  KEY `idx_products_category_active` (`category_id`,`is_active`),
  KEY `idx_products_active` (`is_active`),
  KEY `fk_products_unit` (`unit_id`),
  CONSTRAINT `fk_products_category` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE RESTRICT ON UPDATE NO ACTION,
  CONSTRAINT `fk_products_unit` FOREIGN KEY (`unit_id`) REFERENCES `units` (`id`) ON DELETE RESTRICT ON UPDATE NO ACTION
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 8. orders
CREATE TABLE IF NOT EXISTS `orders` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `order_number` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` int unsigned NOT NULL,
  `status` enum('pending','received','in_progress','delivered','declined','cancelled') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `total_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `ordered_at` datetime NOT NULL,
  `received_at` datetime DEFAULT NULL,
  `delivered_at` datetime DEFAULT NULL,
  `cancelled_at` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_orders_order_number` (`order_number`),
  KEY `idx_orders_user_ordered_at` (`user_id`,`ordered_at`),
  KEY `idx_orders_status_ordered_at` (`status`,`ordered_at`),
  CONSTRAINT `fk_orders_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE NO ACTION
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 9. order_items
CREATE TABLE IF NOT EXISTS `order_items` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `order_id` int unsigned NOT NULL,
  `product_id` int unsigned NOT NULL,
  `product_name_snapshot` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `unit_name_snapshot` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `unit_price_snapshot` decimal(10,2) NOT NULL,
  `quantity` decimal(10,2) NOT NULL,
  `line_total` decimal(12,2) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_order_items_order_id` (`order_id`),
  KEY `idx_order_items_product_id` (`product_id`),
  CONSTRAINT `fk_order_items_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION,
  CONSTRAINT `fk_order_items_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE RESTRICT ON UPDATE NO ACTION
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 10. order_status_history
CREATE TABLE IF NOT EXISTS `order_status_history` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `order_id` int unsigned NOT NULL,
  `old_status` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `new_status` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL,
  `changed_by_user_id` int unsigned NOT NULL,
  `note` text COLLATE utf8mb4_unicode_ci,
  `changed_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_order_status_history_order_changed` (`order_id`,`changed_at`),
  KEY `fk_order_status_history_user` (`changed_by_user_id`),
  CONSTRAINT `fk_order_status_history_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION,
  CONSTRAINT `fk_order_status_history_user` FOREIGN KEY (`changed_by_user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE NO ACTION
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 11. email_logs
CREATE TABLE IF NOT EXISTS `email_logs` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `order_id` int unsigned DEFAULT NULL,
  `user_id` int unsigned NOT NULL,
  `recipient_email` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email_type` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `subject` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` enum('sent','failed') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'sent',
  `error_message` text COLLATE utf8mb4_unicode_ci,
  `sent_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_email_logs_order_id` (`order_id`),
  KEY `idx_email_logs_user_sent_at` (`user_id`,`sent_at`),
  CONSTRAINT `fk_email_logs_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE SET NULL ON UPDATE NO ACTION,
  CONSTRAINT `fk_email_logs_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Record migrations in phinxlog
INSERT IGNORE INTO `phinxlog` (`version`, `migration_name`, `start_time`, `end_time`, `breakpoint`) VALUES
  (20261126000100, 'CreateInitialSchema', NOW(), NOW(), 0),
  (20261126000200, 'CreatePasswordResetsTable', NOW(), NOW(), 0),
  (20261128000300, 'AddCancelledOrderStatus', NOW(), NOW(), 0);

-- Seed categories
INSERT IGNORE INTO `categories` (`id`, `name`, `slug`, `sort_order`) VALUES
  (1, 'Fruit', 'fruit', 1),
  (2, 'Vegetable', 'vegetable', 2),
  (3, 'Herb', 'herb', 3),
  (4, 'Mushrooms', 'mushrooms', 4),
  (5, 'Eggs', 'eggs', 5);

-- Seed units
INSERT IGNORE INTO `units` (`id`, `name`, `allow_decimal`, `step_size`) VALUES
  (1, 'kg', 1, 0.10),
  (2, 'Unit', 0, 1.00),
  (3, 'Punnet', 0, 1.00);

-- Seed supplier accounts (change default passwords after first login!)
INSERT IGNORE INTO `users` (`role`, `company_name`, `contact_name`, `email`, `phone`, `location`, `password_hash`, `email_verified_at`, `is_active`, `created_at`, `updated_at`) VALUES
  ('supplier', 'Fresh Produce Wholesale SA', 'Primary Supplier Admin', 'supplier@freshproduce.co.za', '+27 11 555 0100', 'Johannesburg Fresh Produce Market, City Deep', '$2y$12$DosR/qttb/v5OoqDcHvSvO.IFzyw5vekKy2aOwz01MCO9JLli7KUS', NOW(), 1, NOW(), NOW()),
  ('supplier', 'Asamaritan Fresh Produce', 'Supplier Admin', 'no-reply@asamaritan-order-management-sys.co.za', NULL, 'South Africa', '$2y$12$upfHFfA5DemC.LLJfXcKOOEfKAYDmoGzZCbSIri2J6/DgkQu3evrW', NOW(), 1, NOW(), NOW());

-- Seed 98 catalog products
INSERT IGNORE INTO `products` (`category_id`, `unit_id`, `sku`, `slug`, `description`, `image_path`, `unit_price`, `stock_quantity`, `is_active`, `created_at`, `updated_at`) VALUES
  (1, 1, 'FRT-001', 'apple-granny', 'Apple Granny', 'assets/images/products/apple-granny.png', 28.50, 100.00, 1, NOW(), NOW()),
  (1, 1, 'FRT-002', 'apple-red', 'Apple Red', 'assets/images/products/apple-red.png', 29.90, 100.00, 1, NOW(), NOW()),
  (1, 1, 'FRT-003', 'apple-standard', 'Apple Standard', 'assets/images/products/apple-standard.png', 25.00, 100.00, 1, NOW(), NOW()),
  (1, 1, 'FRT-004', 'avocado', 'Avocado', 'assets/images/products/avocado.png', 65.00, 100.00, 1, NOW(), NOW()),
  (1, 1, 'FRT-005', 'bananas', 'Bananas', 'assets/images/products/bananas.png', 24.50, 100.00, 1, NOW(), NOW()),
  (1, 3, 'FRT-006', 'blue-berry-punnet-125g', 'Blue Berry Punnet 125g', 'assets/images/products/blue-berry-punnet-125g.png', 38.00, 100.00, 1, NOW(), NOW()),
  (1, 2, 'FRT-007', 'fruit-pack-4-variety', 'Fruit pack (4 variety)', 'assets/images/products/fruit-pack-4-variety.png', 55.00, 100.00, 1, NOW(), NOW()),
  (1, 1, 'FRT-008', 'gooseberries', 'Gooseberries', 'assets/images/products/gooseberries.png', 95.00, 100.00, 1, NOW(), NOW()),
  (1, 1, 'FRT-009', 'granadilla', 'Granadilla', 'assets/images/products/granadilla.png', 85.00, 100.00, 1, NOW(), NOW()),
  (1, 1, 'FRT-010', 'grapefruit-kg', 'Grapefruit kg', 'assets/images/products/grapefruit-kg.png', 24.00, 100.00, 1, NOW(), NOW()),
  (1, 3, 'FRT-011', 'grapes-green-punnet', 'Grapes Green Punnet', 'assets/images/products/grapes-green-punnet.png', 36.00, 100.00, 1, NOW(), NOW()),
  (1, 3, 'FRT-012', 'grapes-red-punnet', 'Grapes Red Punnet', 'assets/images/products/grapes-red-punnet.png', 36.00, 100.00, 1, NOW(), NOW()),
  (1, 1, 'FRT-013', 'kiwi-fruit', 'Kiwi Fruit', 'assets/images/products/kiwi-fruit.png', 78.00, 100.00, 1, NOW(), NOW()),
  (1, 1, 'FRT-014', 'lemons', 'Lemons', 'assets/images/products/lemons.png', 26.00, 100.00, 1, NOW(), NOW()),
  (1, 1, 'FRT-015', 'limes', 'Limes', 'assets/images/products/limes.png', 48.00, 100.00, 1, NOW(), NOW()),
  (1, 1, 'FRT-016', 'litchi', 'Litchi', 'assets/images/products/litchi.png', 68.00, 100.00, 1, NOW(), NOW()),
  (1, 2, 'FRT-017', 'melon', 'Melon', 'assets/images/products/melon.png', 34.00, 100.00, 1, NOW(), NOW()),
  (1, 1, 'FRT-018', 'naartjie', 'Naartjie', 'assets/images/products/naartjie.png', 29.00, 100.00, 1, NOW(), NOW()),
  (1, 1, 'FRT-019', 'nectarines', 'Nectarines', 'assets/images/products/nectarines.png', 46.00, 100.00, 1, NOW(), NOW()),
  (1, 1, 'FRT-020', 'oranges', 'Oranges', 'assets/images/products/oranges.png', 22.00, 100.00, 1, NOW(), NOW()),
  (1, 1, 'FRT-021', 'pawpaw', 'PawPaw', 'assets/images/products/pawpaw.png', 32.00, 100.00, 1, NOW(), NOW()),
  (1, 1, 'FRT-022', 'pears', 'Pears', 'assets/images/products/pears.png', 31.00, 100.00, 1, NOW(), NOW()),
  (1, 2, 'FRT-023', 'pines', 'Pines', 'assets/images/products/pines.png', 28.00, 100.00, 1, NOW(), NOW()),
  (1, 1, 'FRT-024', 'plums', 'Plums', 'assets/images/products/plums.png', 44.00, 100.00, 1, NOW(), NOW()),
  (1, 1, 'FRT-025', 'pomegranate', 'Pomegranate', 'assets/images/products/pomegranate.png', 72.00, 100.00, 1, NOW(), NOW()),
  (1, 2, 'FRT-026', 'sponspek', 'Sponspek', 'assets/images/products/sponspek.png', 35.00, 100.00, 1, NOW(), NOW()),
  (1, 3, 'FRT-027', 'strawberry-punnet', 'Strawberry Punnet', 'assets/images/products/strawberry-punnet.png', 34.00, 100.00, 1, NOW(), NOW()),
  (1, 1, 'FRT-028', 'tomato-cocktail-200g', 'Tomato Cocktail 200g', 'assets/images/products/tomato-cocktail-200g.png', 54.00, 100.00, 1, NOW(), NOW()),
  (1, 1, 'FRT-029', 'tomatoes', 'Tomatoes', 'assets/images/products/tomatoes.png', 26.50, 100.00, 1, NOW(), NOW()),
  (1, 2, 'FRT-030', 'watermelon', 'Watermelon', 'assets/images/products/watermelon.png', 65.00, 100.00, 1, NOW(), NOW()),
  (1, 1, 'FRT-031', 'watermelon-sugar-babies', 'Watermelon Sugar Babies', 'assets/images/products/watermelon-sugar-babies.png', 18.50, 100.00, 1, NOW(), NOW()),
  (2, 1, 'VEG-001', 'amadumbe', 'Amadumbe', 'assets/images/products/amadumbe.png', 36.00, 100.00, 1, NOW(), NOW()),
  (2, 3, 'VEG-002', 'asparagus-mini-100g', 'Asparagus Mini 100g', 'assets/images/products/asparagus-mini-100g.png', 42.00, 100.00, 1, NOW(), NOW()),
  (2, 2, 'VEG-003', 'asparagus-spears', 'Asparagus Spears', 'assets/images/products/asparagus-spears.png', 45.00, 100.00, 1, NOW(), NOW()),
  (2, 1, 'VEG-004', 'baby-corn', 'Baby Corn', 'assets/images/products/baby-corn.png', 64.00, 100.00, 1, NOW(), NOW()),
  (2, 1, 'VEG-005', 'baby-gemsquash', 'Baby Gemsquash', 'assets/images/products/baby-gemsquash.png', 34.00, 100.00, 1, NOW(), NOW()),
  (2, 1, 'VEG-006', 'baby-marrow', 'Baby Marrow', 'assets/images/products/baby-marrow.png', 38.00, 100.00, 1, NOW(), NOW()),
  (2, 1, 'VEG-007', 'baby-spinach', 'Baby Spinach', 'assets/images/products/baby-spinach.png', 58.00, 100.00, 1, NOW(), NOW()),
  (2, 1, 'VEG-008', 'beetroot', 'Beetroot', 'assets/images/products/beetroot.png', 19.50, 100.00, 1, NOW(), NOW()),
  (2, 1, 'VEG-009', 'brinjal', 'Brinjal', 'assets/images/products/brinjal.png', 32.00, 100.00, 1, NOW(), NOW()),
  (2, 2, 'VEG-010', 'broccoli', 'Broccoli', 'assets/images/products/broccoli.png', 26.00, 100.00, 1, NOW(), NOW()),
  (2, 1, 'VEG-011', 'broccoli-long-stem', 'Broccoli Long Stem', 'assets/images/products/broccoli-long-stem.png', 68.00, 100.00, 1, NOW(), NOW()),
  (2, 1, 'VEG-012', 'brussel', 'Brussel', 'assets/images/products/brussel.png', 62.00, 100.00, 1, NOW(), NOW()),
  (2, 1, 'VEG-013', 'butternut', 'Butternut', 'assets/images/products/butternut.png', 16.50, 100.00, 1, NOW(), NOW()),
  (2, 2, 'VEG-014', 'cabbage-green', 'Cabbage Green', 'assets/images/products/cabbage-green.png', 22.00, 100.00, 1, NOW(), NOW()),
  (2, 2, 'VEG-015', 'cabbage-red', 'Cabbage Red', 'assets/images/products/cabbage-red.png', 28.00, 100.00, 1, NOW(), NOW()),
  (2, 1, 'VEG-016', 'carrot', 'Carrot', 'assets/images/products/carrot.png', 18.00, 100.00, 1, NOW(), NOW()),
  (2, 1, 'VEG-017', 'carrot-baby', 'Carrot Baby', 'assets/images/products/carrot-baby.png', 36.00, 100.00, 1, NOW(), NOW()),
  (2, 2, 'VEG-018', 'cauliflower', 'Cauliflower', 'assets/images/products/cauliflower.png', 29.00, 100.00, 1, NOW(), NOW()),
  (2, 2, 'VEG-019', 'celery', 'Celery', 'assets/images/products/celery.png', 24.00, 100.00, 1, NOW(), NOW()),
  (2, 2, 'VEG-020', 'cucumber', 'Cucumber', 'assets/images/products/cucumber.png', 16.00, 100.00, 1, NOW(), NOW()),
  (2, 1, 'VEG-021', 'gemsquash', 'Gemsquash', 'assets/images/products/gemsquash.png', 22.00, 100.00, 1, NOW(), NOW()),
  (2, 1, 'VEG-022', 'green-bean', 'Green Bean', 'assets/images/products/green-bean.png', 44.00, 100.00, 1, NOW(), NOW()),
  (2, 1, 'VEG-023', 'leeks', 'Leeks', 'assets/images/products/leeks.png', 38.00, 100.00, 1, NOW(), NOW()),
  (2, 1, 'VEG-024', 'lettuce-iceberg', 'Lettuce Iceberg', 'assets/images/products/lettuce-iceberg.png', 28.00, 100.00, 1, NOW(), NOW()),
  (2, 1, 'VEG-025', 'onion-sliced', 'Onion Sliced', 'assets/images/products/onion-sliced.png', 34.00, 100.00, 1, NOW(), NOW()),
  (2, 1, 'VEG-026', 'onions', 'Onions', 'assets/images/products/onions.png', 19.00, 100.00, 1, NOW(), NOW()),
  (2, 1, 'VEG-027', 'onions-red', 'Onions Red', 'assets/images/products/onions-red.png', 26.00, 100.00, 1, NOW(), NOW()),
  (2, 3, 'VEG-028', 'pattypan-green', 'Pattypan Green', 'assets/images/products/pattypan-green.png', 26.00, 100.00, 1, NOW(), NOW()),
  (2, 3, 'VEG-029', 'pattypan-yellow', 'Pattypan Yellow', 'assets/images/products/pattypan-yellow.png', 26.00, 100.00, 1, NOW(), NOW()),
  (2, 1, 'VEG-030', 'pepper-green', 'Pepper Green', 'assets/images/products/pepper-green.png', 36.00, 100.00, 1, NOW(), NOW()),
  (2, 1, 'VEG-031', 'pepper-red', 'Pepper Red', 'assets/images/products/pepper-red.png', 48.00, 100.00, 1, NOW(), NOW()),
  (2, 2, 'VEG-032', 'pepper-robot-pack-each', 'Pepper Robot Pack each', 'assets/images/products/pepper-robot-pack-each.png', 34.00, 100.00, 1, NOW(), NOW()),
  (2, 1, 'VEG-033', 'pepper-yellow', 'Pepper Yellow', 'assets/images/products/pepper-yellow.png', 48.00, 100.00, 1, NOW(), NOW()),
  (2, 1, 'VEG-034', 'potato', 'Potato', 'assets/images/products/potato.png', 18.50, 100.00, 1, NOW(), NOW()),
  (2, 1, 'VEG-035', 'potato-baby', 'Potato Baby', 'assets/images/products/potato-baby.png', 29.00, 100.00, 1, NOW(), NOW()),
  (2, 1, 'VEG-036', 'pumpkin', 'Pumpkin', 'assets/images/products/pumpkin.png', 15.50, 100.00, 1, NOW(), NOW()),
  (2, 2, 'VEG-037', 'salad-pack-lettuce-green', 'Salad Pack Lettuce Green', 'assets/images/products/salad-pack-lettuce-green.png', 25.00, 100.00, 1, NOW(), NOW()),
  (2, 2, 'VEG-038', 'salad-pack-lettuce-red', 'Salad Pack Lettuce Red', 'assets/images/products/salad-pack-lettuce-red.png', 27.00, 100.00, 1, NOW(), NOW()),
  (2, 2, 'VEG-039', 'spinach-bunch', 'Spinach Bunch', 'assets/images/products/spinach-bunch.png', 18.00, 100.00, 1, NOW(), NOW()),
  (2, 3, 'VEG-040', 'sugar-snap-pea-85g', 'Sugar Snap Pea 85g', 'assets/images/products/sugar-snap-pea-85g.png', 32.00, 100.00, 1, NOW(), NOW()),
  (2, 1, 'VEG-041', 'sweet-corn', 'Sweet Corn', 'assets/images/products/sweet-corn.png', 35.00, 100.00, 1, NOW(), NOW()),
  (2, 1, 'VEG-042', 'sweet-potato', 'Sweet Potato', 'assets/images/products/sweet-potato.png', 24.00, 100.00, 1, NOW(), NOW()),
  (3, 1, 'HRB-001', 'basil', 'Basil', 'assets/images/products/basil.png', 140.00, 100.00, 1, NOW(), NOW()),
  (3, 1, 'HRB-002', 'chilli-green', 'Chilli Green', 'assets/images/products/chilli-green.png', 65.00, 100.00, 1, NOW(), NOW()),
  (3, 1, 'HRB-003', 'chilli-red', 'Chilli Red', 'assets/images/products/chilli-red.png', 68.00, 100.00, 1, NOW(), NOW()),
  (3, 1, 'HRB-004', 'chives', 'Chives', 'assets/images/products/chives.png', 135.00, 100.00, 1, NOW(), NOW()),
  (3, 1, 'HRB-005', 'coriander', 'Coriander', 'assets/images/products/coriander.png', 110.00, 100.00, 1, NOW(), NOW()),
  (3, 1, 'HRB-006', 'curry-leaves', 'Curry Leaves', 'assets/images/products/curry-leaves.png', 150.00, 100.00, 1, NOW(), NOW()),
  (3, 1, 'HRB-007', 'dill', 'Dill', 'assets/images/products/dill.png', 135.00, 100.00, 1, NOW(), NOW()),
  (3, 1, 'HRB-008', 'flower-edible', 'Flower Edible', 'assets/images/products/flower-edible.png', 220.00, 100.00, 1, NOW(), NOW()),
  (3, 1, 'HRB-009', 'garlic', 'Garlic', 'assets/images/products/garlic.png', 95.00, 100.00, 1, NOW(), NOW()),
  (3, 1, 'HRB-010', 'garlic-crushed', 'Garlic Crushed', 'assets/images/products/garlic-crushed.png', 105.00, 100.00, 1, NOW(), NOW()),
  (3, 1, 'HRB-011', 'ginger', 'Ginger', 'assets/images/products/ginger.png', 98.00, 100.00, 1, NOW(), NOW()),
  (3, 1, 'HRB-012', 'lemon-grass', 'Lemon Grass', 'assets/images/products/lemon-grass.png', 120.00, 100.00, 1, NOW(), NOW()),
  (3, 1, 'HRB-013', 'micro-herb', 'Micro Herb', 'assets/images/products/micro-herb.png', 240.00, 100.00, 1, NOW(), NOW()),
  (3, 1, 'HRB-014', 'mint', 'Mint', 'assets/images/products/mint.png', 125.00, 100.00, 1, NOW(), NOW()),
  (3, 1, 'HRB-015', 'parsley', 'Parsley', 'assets/images/products/parsley.png', 95.00, 100.00, 1, NOW(), NOW()),
  (3, 1, 'HRB-016', 'parsley-italian', 'Parsley Italian', 'assets/images/products/parsley-italian.png', 105.00, 100.00, 1, NOW(), NOW()),
  (3, 3, 'HRB-017', 'petite-leaf-crimson', 'Petite leaf Crimson', 'assets/images/products/petite-leaf-crimson.png', 34.00, 100.00, 1, NOW(), NOW()),
  (3, 1, 'HRB-018', 'radish', 'Radish', 'assets/images/products/radish.png', 42.00, 100.00, 1, NOW(), NOW()),
  (3, 1, 'HRB-019', 'rocket', 'Rocket', 'assets/images/products/rocket.png', 130.00, 100.00, 1, NOW(), NOW()),
  (3, 1, 'HRB-020', 'rosemary', 'Rosemary', 'assets/images/products/rosemary.png', 130.00, 100.00, 1, NOW(), NOW()),
  (3, 1, 'HRB-021', 'spring-onion', 'Spring Onion', 'assets/images/products/spring-onion.png', 52.00, 100.00, 1, NOW(), NOW()),
  (3, 1, 'HRB-022', 'thyme', 'Thyme', 'assets/images/products/thyme.png', 135.00, 100.00, 1, NOW(), NOW()),
  (4, 1, 'MSH-001', 'mushroom', 'Mushroom', 'assets/images/products/mushroom.png', 88.00, 100.00, 1, NOW(), NOW()),
  (5, 2, 'EGG-001', 'eggs-large-30-free-range', 'Eggs Large (30) - Free Range', 'assets/images/products/eggs-large-30-free-range.png', 115.00, 100.00, 1, NOW(), NOW()),
  (5, 2, 'EGG-002', 'eggs-large-30-plain', 'Eggs Large (30) - Plain', 'assets/images/products/eggs-large-30-plain.png', 89.00, 100.00, 1, NOW(), NOW());

SET FOREIGN_KEY_CHECKS = 1;
