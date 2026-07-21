<?php
// Task 2 migration — idempotent. Checks column/table existence before every ALTER/CREATE.
declare(strict_types=1);

$configPath = __DIR__ . '/config/config.php';
if (!is_file($configPath)) {
    $configPath = __DIR__ . '/../public/config/config.php';
}

require_once $configPath;

header('Content-Type: application/json');

if (!defined('DB_CONFIG_PATH') || !is_file(DB_CONFIG_PATH)) {
    http_response_code(503);
    echo json_encode(['status' => 'error', 'msg' => 'database.php missing']);
    exit;
}

$cfg = require DB_CONFIG_PATH;

function makeConn(array $cfg): mysqli
{
    $c = new mysqli(
        (string)($cfg['host']     ?? 'localhost'),
        (string)($cfg['username'] ?? ''),
        (string)($cfg['password'] ?? ''),
        (string)($cfg['database'] ?? '')
    );
    if ($c->connect_error) {
        http_response_code(503);
        echo json_encode(['status' => 'error', 'msg' => 'connect: ' . $c->connect_error]);
        exit;
    }
    $c->set_charset('utf8mb4');
    return $c;
}

function columnExists(mysqli $c, string $table, string $col): bool
{
    $db = $c->query('SELECT DATABASE()')->fetch_row()[0];
    $r  = $c->query(
        "SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = '$db' AND TABLE_NAME = '$table' AND COLUMN_NAME = '$col'"
    );
    return (int)$r->fetch_row()[0] > 0;
}

function tableExists(mysqli $c, string $table): bool
{
    $db = $c->query('SELECT DATABASE()')->fetch_row()[0];
    $r  = $c->query(
        "SELECT COUNT(*) FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = '$db' AND TABLE_NAME = '$table'"
    );
    return (int)$r->fetch_row()[0] > 0;
}

function enumHasValue(mysqli $c, string $table, string $col, string $val): bool
{
    $db  = $c->query('SELECT DATABASE()')->fetch_row()[0];
    $r   = $c->query(
        "SELECT COLUMN_TYPE FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = '$db' AND TABLE_NAME = '$table' AND COLUMN_NAME = '$col'"
    );
    $row = $r ? $r->fetch_row() : null;
    return $row !== null && strpos((string)$row[0], "'$val'") !== false;
}

function runQ(mysqli $c, string $sql, string $label): void
{
    if (!$c->query($sql)) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'msg' => "$label: {$c->error}"]);
        exit;
    }
}

$conn = makeConn($cfg);
$log  = [];

// ── movies.is_active ─────────────────────────────────────────────────────────
if (!columnExists($conn, 'movies', 'is_active')) {
    runQ($conn, "ALTER TABLE `movies` ADD COLUMN `is_active` TINYINT(1) NOT NULL DEFAULT 1 AFTER `status`", 'movies.is_active');
    $log[] = 'added movies.is_active';
} else {
    $log[] = 'skip movies.is_active (exists)';
}

// ── showtimes.showtime_time ───────────────────────────────────────────────────
if (!columnExists($conn, 'showtimes', 'showtime_time')) {
    runQ($conn, "ALTER TABLE `showtimes` ADD COLUMN `showtime_time` TIME NULL AFTER `showtime_date`", 'showtimes.showtime_time');
    $log[] = 'added showtimes.showtime_time';
} else {
    $log[] = 'skip showtimes.showtime_time (exists)';
}

// ── events.event_time ───────────────────────────────────────────────────────
if (!columnExists($conn, 'events', 'event_time')) {
    runQ($conn, "ALTER TABLE `events` ADD COLUMN `event_time` TIME NULL AFTER `event_date`", 'events.event_time');
    $log[] = 'added events.event_time';
} else {
    $log[] = 'skip events.event_time (exists)';
}

if (!columnExists($conn, 'showtimes', 'available_tickets')) {
    runQ($conn, "ALTER TABLE `showtimes` ADD COLUMN `available_tickets` INT NOT NULL DEFAULT 50 AFTER `showtime_time`", 'showtimes.available_tickets');
    $log[] = 'added showtimes.available_tickets';
} else {
    $log[] = 'skip showtimes.available_tickets (exists)';
}

if (!columnExists($conn, 'showtimes', 'tickets_sold')) {
    runQ($conn, "ALTER TABLE `showtimes` ADD COLUMN `tickets_sold` INT NOT NULL DEFAULT 0 AFTER `available_tickets`", 'showtimes.tickets_sold');
    $log[] = 'added showtimes.tickets_sold';
} else {
    $log[] = 'skip showtimes.tickets_sold (exists)';
}

if (!columnExists($conn, 'showtimes', 'is_active')) {
    runQ($conn, "ALTER TABLE `showtimes` ADD COLUMN `is_active` TINYINT(1) NOT NULL DEFAULT 1 AFTER `tickets_sold`", 'showtimes.is_active');
    $log[] = 'added showtimes.is_active';
} else {
    $log[] = 'skip showtimes.is_active (exists)';
}

// ── concessions.cost / reorder_point / stock_quantity ────────────────────────
if (!columnExists($conn, 'concessions', 'cost')) {
    runQ($conn, "ALTER TABLE `concessions` ADD COLUMN `cost` DECIMAL(6,2) NULL AFTER `price`", 'concessions.cost');
    $log[] = 'added concessions.cost';
} else {
    $log[] = 'skip concessions.cost (exists)';
}

if (!columnExists($conn, 'concessions', 'reorder_point')) {
    runQ($conn, "ALTER TABLE `concessions` ADD COLUMN `reorder_point` INT NULL AFTER `cost`", 'concessions.reorder_point');
    $log[] = 'added concessions.reorder_point';
} else {
    $log[] = 'skip concessions.reorder_point (exists)';
}

if (!columnExists($conn, 'concessions', 'stock_quantity')) {
    runQ($conn, "ALTER TABLE `concessions` ADD COLUMN `stock_quantity` INT NOT NULL DEFAULT 0 AFTER `reorder_point`", 'concessions.stock_quantity');
    $log[] = 'added concessions.stock_quantity';
} else {
    $log[] = 'skip concessions.stock_quantity (exists)';
}

// ── new tables ────────────────────────────────────────────────────────────────
if (!tableExists($conn, 'concession_options')) {
    runQ($conn, "
        CREATE TABLE `concession_options` (
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ", 'create concession_options');
    $log[] = 'created concession_options';
} else {
    $log[] = 'skip concession_options (exists)';
}

if (!tableExists($conn, 'transactions')) {
    runQ($conn, "
        CREATE TABLE `transactions` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `transaction_ref` VARCHAR(20) NOT NULL,
                `daily_order_number` SMALLINT UNSIGNED NULL,
                `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `type` ENUM('ticket','concession','combo') NOT NULL,
                `source_channel` ENUM('website','kiosk','staff','staff_register') NOT NULL DEFAULT 'website',
                `total_amount` DECIMAL(8,2) NOT NULL,
                `payment_status` ENUM('paid','pending','failed','voided') NOT NULL DEFAULT 'pending',
                `payment_method` VARCHAR(50) NOT NULL DEFAULT 'mock',
                `stripe_payment_intent_id` VARCHAR(255) NULL,
                `gateway_ref` VARCHAR(255) NULL,
                `customer_name` VARCHAR(100) NULL,
                `customer_email` VARCHAR(150) NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uq_transaction_ref` (`transaction_ref`),
            KEY `idx_txn_created` (`created_at`),
            KEY `idx_txn_status` (`payment_status`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ", 'create transactions');
    $log[] = 'created transactions';
} else {
    $log[] = 'skip transactions (exists)';
}

// ── transactions.payment_status: add 'voided' (for admin transaction void) ────
if (tableExists($conn, 'transactions') && !enumHasValue($conn, 'transactions', 'payment_status', 'voided')) {
    runQ($conn,
        "ALTER TABLE `transactions`
         MODIFY `payment_status` ENUM('paid','pending','failed','voided') NOT NULL DEFAULT 'pending'",
        'transactions.payment_status +voided');
    $log[] = "added 'voided' to payment_status enum";
} else {
    $log[] = "skip payment_status 'voided' (exists)";
}

// ── transactions.stripe_payment_intent_id (Stripe integration) ────────────────
if (tableExists($conn, 'transactions') && !columnExists($conn, 'transactions', 'stripe_payment_intent_id')) {
    runQ($conn,
        "ALTER TABLE `transactions`
         ADD COLUMN `stripe_payment_intent_id` VARCHAR(255) NULL AFTER `payment_method`,
         ADD KEY `idx_txn_stripe_pi` (`stripe_payment_intent_id`)",
        'transactions.stripe_payment_intent_id');
    $log[] = 'added transactions.stripe_payment_intent_id';
} else {
    $log[] = 'skip transactions.stripe_payment_intent_id (exists)';
}

if (tableExists($conn, 'transactions') && !columnExists($conn, 'transactions', 'gateway_ref')) {
    runQ($conn,
        "ALTER TABLE `transactions`
         ADD COLUMN `gateway_ref` VARCHAR(255) NULL AFTER `stripe_payment_intent_id`",
        'transactions.gateway_ref');
    $log[] = 'added transactions.gateway_ref';
} else {
    $log[] = 'skip transactions.gateway_ref (exists)';
}

if (tableExists($conn, 'transactions') && !columnExists($conn, 'transactions', 'daily_order_number')) {
    runQ($conn,
        "ALTER TABLE `transactions`
         ADD COLUMN `daily_order_number` SMALLINT UNSIGNED NULL AFTER `transaction_ref`",
        'transactions.daily_order_number');
    $log[] = 'added transactions.daily_order_number';
} else {
    $log[] = 'skip transactions.daily_order_number (exists)';
}

if (tableExists($conn, 'transactions') && enumHasValue($conn, 'transactions', 'source_channel', 'staff_register') === false) {
    runQ($conn,
        "ALTER TABLE `transactions`
         MODIFY `source_channel` ENUM('website','kiosk','staff','staff_register') NOT NULL DEFAULT 'website'",
        'transactions.source_channel');
    $log[] = 'updated transactions.source_channel enum';
} else {
    $log[] = 'skip transactions.source_channel update (exists)';
}

if (!tableExists($conn, 'daily_order_counters')) {
    runQ($conn, "
        CREATE TABLE `daily_order_counters` (
            `order_date` DATE NOT NULL,
            `next_number` SMALLINT UNSIGNED NOT NULL DEFAULT 1,
            PRIMARY KEY (`order_date`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ", 'create daily_order_counters');
    $log[] = 'created daily_order_counters';
} else {
    $log[] = 'skip daily_order_counters (exists)';
}

// ── webhook_events: idempotency ledger for Stripe webhooks ────────────────────
if (!tableExists($conn, 'webhook_events')) {
    runQ($conn, "
        CREATE TABLE `webhook_events` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `event_id` VARCHAR(255) NOT NULL,
            `type` VARCHAR(100) NOT NULL,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uq_webhook_event_id` (`event_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ", 'create webhook_events');
    $log[] = 'created webhook_events';
} else {
    $log[] = 'skip webhook_events (exists)';
}

if (!tableExists($conn, 'transaction_items')) {
    runQ($conn, "
        CREATE TABLE `transaction_items` (
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ", 'create transaction_items');
    $log[] = 'created transaction_items';
} else {
    $log[] = 'skip transaction_items (exists)';
}

if (!tableExists($conn, 'inventory_log')) {
    runQ($conn, "
        CREATE TABLE `inventory_log` (
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ", 'create inventory_log');
    $log[] = 'created inventory_log';
} else {
    $log[] = 'skip inventory_log (exists)';
}

// ── seed concession_options if empty ─────────────────────────────────────────
$r = $conn->query("SELECT COUNT(*) FROM concession_options");
if ((int)$r->fetch_row()[0] === 0) {
    // Get fountain drink IDs
    $r2 = $conn->query("SELECT id FROM concessions WHERE category = 'Drinks' AND name LIKE '%Fountain%'");
    $drinkIds = [];
    while ($row = $r2->fetch_row()) {
        $drinkIds[] = (int)$row[0];
    }
    $flavors = ['Pepsi', 'Mtn Dew', 'Dr Pepper', 'Diet Mtn Dew', 'Tropicana', 'Crush', 'Sierra Mist'];
    foreach ($drinkIds as $did) {
        foreach ($flavors as $i => $flavor) {
            $fl = $conn->real_escape_string($flavor);
            $conn->query("INSERT INTO concession_options (concession_id, option_label, is_available, sort_order) VALUES ($did, '$fl', 1, $i)");
        }
    }
    // Box candy options
    $r3 = $conn->query("SELECT id FROM concessions WHERE name = 'Box Candy' LIMIT 1");
    $row = $r3->fetch_row();
    if ($row) {
        $candyId = (int)$row[0];
        $candies = ["Reese's Pieces", 'Skittles', "M&M's", 'Mike & Ike', 'Sour Patch', 'Whoppers', 'Junior Mints', 'Cookie Dough Bites', 'Milk Duds', 'Buncha Crunch'];
        foreach ($candies as $i => $candy) {
            $c2 = $conn->real_escape_string($candy);
            $conn->query("INSERT INTO concession_options (concession_id, option_label, is_available, sort_order) VALUES ($candyId, '$c2', 1, $i)");
        }
    }
    $log[] = 'seeded concession_options';
} else {
    $log[] = 'skip concession_options seed (has data)';
}

// ── fix concession image_path prefix ─────────────────────────────────────────
$r = $conn->query("UPDATE concessions SET image_path = CONCAT('assets/', image_path) WHERE image_path LIKE 'images/%'");
$fixed = $conn->affected_rows;
$log[] = $fixed > 0 ? "fixed $fixed concession image paths" : 'skip image path fix (already correct)';

// ── convert concession image paths from .png to .webp ────────────────────────
$r = $conn->query("UPDATE concessions SET image_path = REPLACE(image_path, '.png', '.webp') WHERE image_path LIKE '%.png'");
$fixed = $conn->affected_rows;
$log[] = $fixed > 0 ? "converted $fixed concession image paths to .webp" : 'skip concession .webp conversion (already done)';

// ── convert movie poster_path from .jpg to .webp (skip sheep) ────────────────
$r = $conn->query("UPDATE movies SET poster_path = REPLACE(poster_path, '.jpg', '.webp') WHERE poster_path LIKE '%.jpg'");
$fixed = $conn->affected_rows;
$log[] = $fixed > 0 ? "converted $fixed movie poster paths to .webp" : 'skip movie .webp conversion (already done or no .jpg paths)';

// ═══════════════════════════════════════════════════════════════════════════
// Task 4 — Employee POS (staff register)
// ═══════════════════════════════════════════════════════════════════════════

// ── employees table (PIN-authenticated POS operators) ─────────────────────────
if (!tableExists($conn, 'employees')) {
    runQ($conn, "
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ", 'create employees');
    $log[] = 'created employees';
} else {
    $log[] = 'skip employees (exists)';
}

// ── seed one default employee (PIN 7090) if the table is empty ────────────────
$r = $conn->query("SELECT COUNT(*) FROM employees");
if ((int)$r->fetch_row()[0] === 0) {
    // bcrypt cost 12 — matches admin_users hashing.
    $pinHash = password_hash('7090', PASSWORD_BCRYPT, ['cost' => 12]);
    $hashEsc = $conn->real_escape_string($pinHash);
    $conn->query(
        "INSERT INTO employees (name, pin_hash, is_active) VALUES ('Front Desk', '$hashEsc', 1)"
    );
    $log[] = 'seeded default employee (PIN 7090)';
} else {
    $log[] = 'skip employee seed (has data)';
}

// ── transactions.source_channel: add 'staff_register' (POS walk-up sales) ─────
if (tableExists($conn, 'transactions') && !enumHasValue($conn, 'transactions', 'source_channel', 'staff_register')) {
    runQ($conn,
        "ALTER TABLE `transactions`
         MODIFY `source_channel` ENUM('website','kiosk','staff','staff_register') NOT NULL DEFAULT 'website'",
        'transactions.source_channel +staff_register');
    $log[] = "added 'staff_register' to source_channel enum";
} else {
    $log[] = "skip source_channel 'staff_register' (exists)";
}

// ── inventory_log.source: add 'staff_register' (POS stock decrements) ─────────
if (tableExists($conn, 'inventory_log') && !enumHasValue($conn, 'inventory_log', 'source', 'staff_register')) {
    runQ($conn,
        "ALTER TABLE `inventory_log`
         MODIFY `source` ENUM('website','admin','kiosk','staff','staff_register') NOT NULL DEFAULT 'website'",
        'inventory_log.source +staff_register');
    $log[] = "added 'staff_register' to inventory_log.source enum";
} else {
    $log[] = "skip inventory_log.source 'staff_register' (exists)";
}

// ═══════════════════════════════════════════════════════════════════════════
// Task 5 — duration/end-time, order fulfillment screen, QR ticket check-in
// ═══════════════════════════════════════════════════════════════════════════

// ── movies.duration_minutes (for auto end-time calc) ──────────────────────────
if (!columnExists($conn, 'movies', 'duration_minutes')) {
    runQ($conn, "ALTER TABLE `movies` ADD COLUMN `duration_minutes` INT UNSIGNED NULL AFTER `screen`", 'movies.duration_minutes');
    $log[] = 'added movies.duration_minutes';
} else {
    $log[] = 'skip movies.duration_minutes (exists)';
}

// ── transactions.fulfillment_status (order fulfillment screen) ────────────────
if (tableExists($conn, 'transactions') && !columnExists($conn, 'transactions', 'fulfillment_status')) {
    runQ($conn,
        "ALTER TABLE `transactions`
         ADD COLUMN `fulfillment_status` ENUM('pending','fulfilled','voided') NOT NULL DEFAULT 'pending'",
        'transactions.fulfillment_status');
    $log[] = 'added transactions.fulfillment_status';

    $conn->query("UPDATE `transactions` SET `fulfillment_status` = 'fulfilled' WHERE `payment_status` = 'paid'");
    $log[] = 'backfilled fulfillment_status=fulfilled on ' . $conn->affected_rows . ' existing paid transaction(s)';
} else {
    $log[] = 'skip transactions.fulfillment_status (exists, no re-backfill)';
}

// ── ticket_tokens (QR check-in) ────────────────────────────────────────────────
// References transaction_items.id (the real PK — NOT line_item_id; this table
// has no separate "line item" id column in this schema).
if (!tableExists($conn, 'ticket_tokens')) {
    runQ($conn, "
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ", 'create ticket_tokens');
    $log[] = 'created ticket_tokens';
} else {
    $log[] = 'skip ticket_tokens (exists)';
}

// ═══════════════════════════════════════════════════════════════════════════
// Priority fix session — DB-backed admin login lockout (was session-based,
// bypassable via a private browser window)
// ═══════════════════════════════════════════════════════════════════════════

if (!tableExists($conn, 'admin_login_attempts')) {
    runQ($conn, "
        CREATE TABLE `admin_login_attempts` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `ip_address` VARCHAR(45) NOT NULL,
            `attempted_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `idx_admin_login_attempts_ip_time` (`ip_address`, `attempted_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ", 'create admin_login_attempts');
    $log[] = 'created admin_login_attempts';
} else {
    $log[] = 'skip admin_login_attempts (exists)';
}

// ═══════════════════════════════════════════════════════════════════════════
// Kiosk + Fulfillment shared order numbering
// ═══════════════════════════════════════════════════════════════════════════

if (tableExists($conn, 'transactions') && !columnExists($conn, 'transactions', 'daily_order_number')) {
    runQ($conn,
        "ALTER TABLE `transactions`
         ADD COLUMN `daily_order_number` SMALLINT UNSIGNED NULL AFTER `transaction_ref`",
        'transactions.daily_order_number'
    );
    $log[] = 'added transactions.daily_order_number';
} else {
    $log[] = 'skip transactions.daily_order_number (exists)';
}

if (!tableExists($conn, 'daily_order_counters')) {
    runQ($conn, "
        CREATE TABLE `daily_order_counters` (
            `order_date` DATE NOT NULL,
            `next_number` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
            PRIMARY KEY (`order_date`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ", 'create daily_order_counters');
    $log[] = 'created daily_order_counters';
} else {
    $log[] = 'skip daily_order_counters (exists)';
}

// ═══════════════════════════════════════════════════════════════════════════
// Per-showtime screen assignment — a movie can run on both screens with
// different showtime blocks; each showtime now records which screen it's on
// ═══════════════════════════════════════════════════════════════════════════

if (tableExists($conn, 'showtimes') && !columnExists($conn, 'showtimes', 'screen')) {
    runQ($conn,
        "ALTER TABLE `showtimes`
         ADD COLUMN `screen` ENUM('large','small','both') NOT NULL DEFAULT 'both' AFTER `sort_order`",
        'showtimes.screen'
    );
    $log[] = 'added showtimes.screen';
} else {
    $log[] = 'skip showtimes.screen (exists)';
}

$conn->close();
echo json_encode(['status' => 'success', 'log' => $log]);
