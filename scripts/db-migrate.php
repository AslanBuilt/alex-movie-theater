<?php
// Task 2 migration — idempotent. Checks column/table existence before every ALTER/CREATE.
declare(strict_types=1);

require_once __DIR__ . '/config/config.php';

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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ", 'create transactions');
    $log[] = 'created transactions';
} else {
    $log[] = 'skip transactions (exists)';
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

$conn->close();
echo json_encode(['status' => 'success', 'log' => $log]);
