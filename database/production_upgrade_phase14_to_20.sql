-- ============================================================================
-- Fresh Produce Ordering System - Live Production Upgrade Script
-- Covers: Phases 13, 14, 19, and 20 (Schema delta: Phase 14 cancelled status)
-- Safe to run on an EXISTING live cPanel MySQL/MariaDB database via phpMyAdmin
-- without dropping tables or losing any existing orders/users/products.
-- ============================================================================

SET NAMES utf8mb4;

-- 1. Extend orders.status ENUM with 'cancelled' (idempotent)
ALTER TABLE `orders`
  MODIFY COLUMN `status` enum('pending','received','in_progress','delivered','declined','cancelled')
  COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending';

-- 2. Add orders.cancelled_at column only if it does not already exist
SET @has_cancelled_at := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'orders'
    AND COLUMN_NAME = 'cancelled_at'
);

SET @add_cancelled_at_sql := IF(
  @has_cancelled_at = 0,
  'ALTER TABLE `orders` ADD COLUMN `cancelled_at` datetime DEFAULT NULL AFTER `delivered_at`',
  'SELECT ''orders.cancelled_at already exists'' AS info'
);

PREPARE stmt_add_cancelled_at FROM @add_cancelled_at_sql;
EXECUTE stmt_add_cancelled_at;
DEALLOCATE PREPARE stmt_add_cancelled_at;

-- 3. Record Phase 14 migration in phinxlog (idempotent)
INSERT IGNORE INTO `phinxlog` (`version`, `migration_name`, `start_time`, `end_time`, `breakpoint`)
VALUES (20261128000300, 'AddCancelledOrderStatus', NOW(), NOW(), 0);
