-- ============================================================================
-- Alex Movie Theatre — Database Schema
-- ----------------------------------------------------------------------------
-- Engine: InnoDB
-- Charset: utf8mb4 / utf8mb4_unicode_ci
--
-- Import via phpMyAdmin or:
--   mysql -u <user> -p <database> < database/schema.sql
--   mysql -u <user> -p <database> < database/seed.sql
-- ============================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ----------------------------------------------------------------------------
-- movies
-- ----------------------------------------------------------------------------
DROP TABLE IF EXISTS `movies`;
CREATE TABLE `movies` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `title` VARCHAR(255) NOT NULL,
    `rating` VARCHAR(10) NOT NULL DEFAULT '',
    `screen` ENUM('large', 'small', 'either', 'both') NOT NULL DEFAULT 'large',
    `duration_minutes` INT UNSIGNED NULL,
    `poster_path` VARCHAR(500) NOT NULL DEFAULT '',
    `description` TEXT NULL,
    `status` ENUM('now_showing', 'coming_soon', 'archived') NOT NULL DEFAULT 'now_showing',
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `online_only` TINYINT(1) NOT NULL DEFAULT 0,
    `sort_order` INT NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_movies_status` (`status`),
    KEY `idx_movies_sort_order` (`sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- showtimes
-- ----------------------------------------------------------------------------
DROP TABLE IF EXISTS `showtimes`;
CREATE TABLE `showtimes` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `movie_id` INT UNSIGNED NOT NULL,
    `label` VARCHAR(100) NOT NULL DEFAULT '',
    `times` VARCHAR(255) NOT NULL DEFAULT '',
    `showtime_date` DATE NULL,
    `showtime_time` TIME NULL,
    `available_tickets` INT NOT NULL DEFAULT 50,
    `tickets_sold` INT NOT NULL DEFAULT 0,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `sort_order` INT NOT NULL DEFAULT 0,
    `screen` ENUM('large', 'small', 'both') NOT NULL DEFAULT 'both',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_showtimes_movie_id` (`movie_id`),
    KEY `idx_showtimes_showtime_date` (`showtime_date`),
    CONSTRAINT `fk_showtimes_movie`
        FOREIGN KEY (`movie_id`) REFERENCES `movies` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- events
-- ----------------------------------------------------------------------------
DROP TABLE IF EXISTS `events`;
CREATE TABLE `events` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `title` VARCHAR(255) NOT NULL,
    `description` TEXT NULL,
    `event_date` DATE NULL,
    `event_time` TIME NULL,
    `badge` VARCHAR(50) NOT NULL DEFAULT 'Upcoming',
    `image_path` VARCHAR(500) NULL,
    `status` ENUM('upcoming', 'past', 'tba') NOT NULL DEFAULT 'upcoming',
    `sort_order` INT NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_events_status` (`status`),
    KEY `idx_events_sort_order` (`sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- senior_showings
-- ----------------------------------------------------------------------------
DROP TABLE IF EXISTS `senior_showings`;
CREATE TABLE `senior_showings` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `movie_title` VARCHAR(255) NOT NULL,
    `showing_date` DATE NULL,
    `showing_time` VARCHAR(50) NULL,
    `notes` TEXT NULL,
    `status` ENUM('upcoming', 'past', 'tba') NOT NULL DEFAULT 'tba',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_senior_status` (`status`),
    KEY `idx_senior_showing_date` (`showing_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- admin_users
-- ----------------------------------------------------------------------------
DROP TABLE IF EXISTS `admin_users`;
CREATE TABLE `admin_users` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `username` VARCHAR(50) NOT NULL,
    `password_hash` VARCHAR(255) NOT NULL,
    `email` VARCHAR(255) NULL,
    `role` ENUM('admin', 'editor', 'viewer') NOT NULL DEFAULT 'admin',
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `last_login` TIMESTAMP NULL DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_admin_users_username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- employees — PIN-authenticated POS (staff register) operators
-- ----------------------------------------------------------------------------
DROP TABLE IF EXISTS `employees`;
CREATE TABLE `employees` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(100) NOT NULL,
    `pin_hash` VARCHAR(255) NOT NULL,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `failed_attempts` INT NOT NULL DEFAULT 0,
    `locked_until` DATETIME NULL,
    `last_login` DATETIME NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- admin_login_attempts — DB-backed admin login lockout (by IP)
-- ----------------------------------------------------------------------------
DROP TABLE IF EXISTS `admin_login_attempts`;
CREATE TABLE `admin_login_attempts` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `ip_address` VARCHAR(45) NOT NULL,
    `attempted_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_admin_login_attempts_ip_time` (`ip_address`, `attempted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- concessions
-- ----------------------------------------------------------------------------
DROP TABLE IF EXISTS `concessions`;
CREATE TABLE `concessions` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `category` VARCHAR(80) NOT NULL DEFAULT 'Other',
    `name` VARCHAR(255) NOT NULL,
    `description` TEXT NULL,
    `price` DECIMAL(6,2) NOT NULL DEFAULT 0.00,
    `cost` DECIMAL(6,2) NULL,
    `reorder_point` INT NULL,
    `stock_quantity` INT NOT NULL DEFAULT 0,
    `image_path` VARCHAR(500) NOT NULL DEFAULT '',
    `is_available` TINYINT(1) NOT NULL DEFAULT 1,
    `sort_order` INT NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_concessions_category` (`category`),
    KEY `idx_concessions_sort` (`sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- concession_options
-- ----------------------------------------------------------------------------
DROP TABLE IF EXISTS `concession_options`;
CREATE TABLE `concession_options` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `concession_id` INT UNSIGNED NOT NULL,
    `option_label` VARCHAR(100) NOT NULL,
    `is_available` TINYINT(1) NOT NULL DEFAULT 1,
    `sort_order` INT NOT NULL DEFAULT 0,
    PRIMARY KEY (`id`),
    KEY `idx_options_concession` (`concession_id`),
    CONSTRAINT `fk_options_concession`
        FOREIGN KEY (`concession_id`) REFERENCES `concessions` (`id`)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- concession_orders  (legacy — kept for backward compat)
-- ----------------------------------------------------------------------------
DROP TABLE IF EXISTS `concession_orders`;
CREATE TABLE `concession_orders` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `order_number` VARCHAR(20) NOT NULL,
    `customer_name` VARCHAR(255) NOT NULL,
    `customer_email` VARCHAR(255) NOT NULL,
    `customer_phone` VARCHAR(30) NOT NULL DEFAULT '',
    `show_info` VARCHAR(255) NOT NULL DEFAULT '',
    `items_json` TEXT NOT NULL,
    `total_amount` DECIMAL(8,2) NOT NULL DEFAULT 0.00,
    `status` ENUM('pending','ready','picked_up','cancelled') NOT NULL DEFAULT 'pending',
    `notes` TEXT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_order_number` (`order_number`),
    KEY `idx_orders_status` (`status`),
    KEY `idx_orders_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- transactions
-- ----------------------------------------------------------------------------
DROP TABLE IF EXISTS `transactions`;
CREATE TABLE `transactions` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `transaction_ref` VARCHAR(20) NOT NULL,
    `daily_order_number` SMALLINT UNSIGNED NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `type` ENUM('ticket','concession','combo') NOT NULL,
    `source_channel` ENUM('website','kiosk','staff','staff_register') NOT NULL DEFAULT 'website',
    `total_amount` DECIMAL(8,2) NOT NULL,
    `payment_status` ENUM('paid','pending','failed','voided') NOT NULL DEFAULT 'pending',
    `fulfillment_status` ENUM('pending','fulfilled','voided') NOT NULL DEFAULT 'pending',
    `payment_method` VARCHAR(50) NOT NULL DEFAULT 'mock',
    `stripe_payment_intent_id` VARCHAR(255) NULL,
    `gateway_ref` VARCHAR(255) NULL,
    `customer_name` VARCHAR(100) NULL,
    `customer_email` VARCHAR(150) NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_transaction_ref` (`transaction_ref`),
    KEY `idx_transactions_created` (`created_at`),
    KEY `idx_transactions_status` (`payment_status`),
    KEY `idx_txn_stripe_pi` (`stripe_payment_intent_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- transaction_items
-- ----------------------------------------------------------------------------
DROP TABLE IF EXISTS `transaction_items`;
CREATE TABLE `transaction_items` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `transaction_id` INT UNSIGNED NOT NULL,
    `item_type` ENUM('ticket','concession') NOT NULL,
    `item_id` INT NOT NULL,
    `item_name` VARCHAR(200) NOT NULL,
    `quantity` INT NOT NULL,
    `unit_price` DECIMAL(6,2) NOT NULL,
    `selected_option` VARCHAR(100) NULL,
    `subtotal` DECIMAL(8,2) NOT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_items_transaction` (`transaction_id`),
    CONSTRAINT `fk_items_transaction`
        FOREIGN KEY (`transaction_id`) REFERENCES `transactions` (`id`)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- ticket_tokens — QR check-in, one row per individual ticket
-- ----------------------------------------------------------------------------
DROP TABLE IF EXISTS `ticket_tokens`;
CREATE TABLE `ticket_tokens` (
    `token_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `transaction_id` INT UNSIGNED NOT NULL,
    `transaction_item_id` INT UNSIGNED NOT NULL,
    `movie_id` INT UNSIGNED NOT NULL,
    `showtime_id` INT UNSIGNED NOT NULL,
    `ticket_token` VARCHAR(128) NOT NULL,
    `token_status` ENUM('valid','used','voided') NOT NULL DEFAULT 'valid',
    `checked_in_at` DATETIME NULL,
    `checked_in_terminal` VARCHAR(100) NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`token_id`),
    UNIQUE KEY `uq_ticket_token` (`ticket_token`),
    KEY `idx_tt_transaction` (`transaction_id`),
    KEY `idx_tt_showtime` (`showtime_id`),
    KEY `idx_tt_status` (`token_status`),
    CONSTRAINT `fk_tt_transaction`
        FOREIGN KEY (`transaction_id`) REFERENCES `transactions` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_tt_item`
        FOREIGN KEY (`transaction_item_id`) REFERENCES `transaction_items` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_tt_movie`
        FOREIGN KEY (`movie_id`) REFERENCES `movies` (`id`)
        ON UPDATE CASCADE,
    CONSTRAINT `fk_tt_showtime`
        FOREIGN KEY (`showtime_id`) REFERENCES `showtimes` (`id`)
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- webhook_events — idempotency ledger for incoming Stripe webhooks
-- ----------------------------------------------------------------------------
DROP TABLE IF EXISTS `webhook_events`;
CREATE TABLE `webhook_events` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `event_id` VARCHAR(255) NOT NULL,
    `type` VARCHAR(100) NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_webhook_event_id` (`event_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- daily_order_counters — atomic per-day sequence source for kiosk/POS/website
-- ----------------------------------------------------------------------------
DROP TABLE IF EXISTS `daily_order_counters`;
CREATE TABLE `daily_order_counters` (
    `order_date` DATE NOT NULL,
    `next_number` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (`order_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- inventory_log
-- ----------------------------------------------------------------------------
DROP TABLE IF EXISTS `inventory_log`;
CREATE TABLE `inventory_log` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `concession_id` INT UNSIGNED NOT NULL,
    `change_type` ENUM('sale','restock','adjustment') NOT NULL,
    `qty_change` INT NOT NULL,
    `new_quantity` INT NOT NULL,
    `source` ENUM('website','admin','kiosk','staff','staff_register') NOT NULL DEFAULT 'website',
    `note` VARCHAR(200) NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_invlog_concession` (`concession_id`),
    KEY `idx_invlog_created` (`created_at`),
    CONSTRAINT `fk_invlog_concession`
        FOREIGN KEY (`concession_id`) REFERENCES `concessions` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
