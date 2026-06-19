-- ============================================================================
-- Alex Movie Theatre — Task 2 Migration
-- ----------------------------------------------------------------------------
-- Additive only — adds columns and tables, never drops existing data.
-- Run via scripts/db-migrate.php which checks existence before each statement.
-- ============================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ----------------------------------------------------------------------------
-- movies — add is_active toggle
-- ----------------------------------------------------------------------------
ALTER TABLE `movies`
    ADD COLUMN `is_active` TINYINT(1) NOT NULL DEFAULT 1 AFTER `status`;

-- ----------------------------------------------------------------------------
-- showtimes — add transactional columns
-- ----------------------------------------------------------------------------
ALTER TABLE `showtimes`
    ADD COLUMN `showtime_time` TIME NULL AFTER `showtime_date`,
    ADD COLUMN `available_tickets` INT NOT NULL DEFAULT 50 AFTER `showtime_time`,
    ADD COLUMN `tickets_sold` INT NOT NULL DEFAULT 0 AFTER `available_tickets`,
    ADD COLUMN `is_active` TINYINT(1) NOT NULL DEFAULT 1 AFTER `tickets_sold`;

-- ----------------------------------------------------------------------------
-- concessions — add cost / reorder / stock columns
-- ----------------------------------------------------------------------------
ALTER TABLE `concessions`
    ADD COLUMN `cost` DECIMAL(6,2) NULL AFTER `price`,
    ADD COLUMN `reorder_point` INT NULL AFTER `cost`,
    ADD COLUMN `stock_quantity` INT NOT NULL DEFAULT 0 AFTER `reorder_point`;

-- ----------------------------------------------------------------------------
-- concession_options — flavor/variant choices per product
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `concession_options` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `concession_id` INT UNSIGNED NOT NULL,
    `option_label` VARCHAR(100) NOT NULL,
    `is_available` TINYINT(1) NOT NULL DEFAULT 1,
    `sort_order` INT NOT NULL DEFAULT 0,
    PRIMARY KEY (`id`),
    KEY `idx_coptions_concession` (`concession_id`),
    CONSTRAINT `fk_coptions_concession`
        FOREIGN KEY (`concession_id`) REFERENCES `concessions` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- transactions — unified purchase records (replaces concession_orders)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `transactions` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `transaction_ref` VARCHAR(20) NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `type` ENUM('ticket','concession','combo') NOT NULL,
    `source_channel` ENUM('website','kiosk','staff') NOT NULL DEFAULT 'website',
    `total_amount` DECIMAL(8,2) NOT NULL,
    `payment_status` ENUM('paid','pending','failed') NOT NULL DEFAULT 'pending',
    `payment_method` VARCHAR(50) NOT NULL DEFAULT 'mock',
    `customer_name` VARCHAR(100) NULL,
    `customer_email` VARCHAR(150) NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_transaction_ref` (`transaction_ref`),
    KEY `idx_txn_created` (`created_at`),
    KEY `idx_txn_status` (`payment_status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- transaction_items — line items for each transaction
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `transaction_items` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `transaction_id` INT UNSIGNED NOT NULL,
    `item_type` ENUM('ticket','concession') NOT NULL,
    `item_id` INT UNSIGNED NOT NULL,
    `item_name` VARCHAR(200) NOT NULL,
    `quantity` INT NOT NULL,
    `unit_price` DECIMAL(6,2) NOT NULL,
    `selected_option` VARCHAR(100) NULL,
    `subtotal` DECIMAL(8,2) NOT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_txn_items_txn` (`transaction_id`),
    CONSTRAINT `fk_txn_items_txn`
        FOREIGN KEY (`transaction_id`) REFERENCES `transactions` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- inventory_log — stock change history
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `inventory_log` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `concession_id` INT UNSIGNED NOT NULL,
    `change_type` ENUM('sale','restock','adjustment') NOT NULL,
    `qty_change` INT NOT NULL,
    `new_quantity` INT NOT NULL,
    `source` ENUM('website','admin','kiosk','staff') NOT NULL DEFAULT 'website',
    `note` VARCHAR(200) NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_inv_log_concession` (`concession_id`),
    KEY `idx_inv_log_created` (`created_at`),
    CONSTRAINT `fk_inv_log_concession`
        FOREIGN KEY (`concession_id`) REFERENCES `concessions` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
