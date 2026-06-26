<?php
declare(strict_types=1);

/**
 * TEMPORARY token-guarded POS diagnostic/repair endpoint.
 * Ensures the employees table + the default "Front Desk" PIN-7090 operator
 * exist, clears any lockout, and reports whether PIN 7090 verifies.
 * DELETE THIS FILE after confirming the POS login works.
 */

require_once __DIR__ . '/config/config.php';
require_once INCLUDES_PATH . '/Database.php';

header('Content-Type: application/json');

$KEY = 'fixpos-7q2x9k4a';
if (($_GET['key'] ?? '') !== $KEY) {
    http_response_code(403);
    echo json_encode(['error' => 'forbidden']);
    exit;
}

$out = [];

try {
    $db = Database::getInstance();
} catch (Throwable $e) {
    http_response_code(503);
    echo json_encode(['error' => 'db connect: ' . $e->getMessage()]);
    exit;
}

try {
    // 1. Table present?
    $exists = count($db->query("SHOW TABLES LIKE 'employees'")->fetchAll()) > 0;
    $out['employees_table_existed'] = $exists;

    if (!$exists) {
        $db->exec(
            "CREATE TABLE `employees` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `name` VARCHAR(100) NOT NULL,
                `pin_hash` VARCHAR(255) NOT NULL,
                `is_active` TINYINT(1) NOT NULL DEFAULT 1,
                `failed_attempts` INT NOT NULL DEFAULT 0,
                `locked_until` DATETIME NULL,
                `last_login` DATETIME NULL,
                `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
        $out['created_table'] = true;
    }

    // 2. Snapshot current rows.
    $rows = $db->query(
        "SELECT id, name, is_active, failed_attempts, locked_until, pin_hash FROM employees"
    )->fetchAll();
    $out['rows_before'] = array_map(static function (array $r): array {
        return [
            'id'              => (int)$r['id'],
            'name'            => $r['name'],
            'is_active'       => (int)$r['is_active'],
            'failed_attempts' => (int)$r['failed_attempts'],
            'locked_until'    => $r['locked_until'],
            'pin7090_ok'      => password_verify('7090', (string)$r['pin_hash']),
        ];
    }, $rows);

    // 3. Ensure an active operator whose PIN is 7090 exists.
    $has7090 = false;
    foreach ($rows as $r) {
        if ((int)$r['is_active'] === 1 && password_verify('7090', (string)$r['pin_hash'])) {
            $has7090 = true;
            break;
        }
    }
    if (!$has7090) {
        $hash = password_hash('7090', PASSWORD_BCRYPT, ['cost' => 12]);
        $st = $db->prepare(
            "INSERT INTO employees (name, pin_hash, is_active) VALUES ('Front Desk', :h, 1)"
        );
        $st->execute([':h' => $hash]);
        $out['seeded_front_desk'] = true;
    }

    // 4. Clear any lockout so the PIN works immediately.
    $db->exec("UPDATE employees SET failed_attempts = 0, locked_until = NULL WHERE is_active = 1");

    // 5. Final verification.
    $final = $db->query("SELECT id, name, is_active, pin_hash FROM employees")->fetchAll();
    $out['rows_after'] = array_map(static function (array $r): array {
        return [
            'id'         => (int)$r['id'],
            'name'       => $r['name'],
            'is_active'  => (int)$r['is_active'],
            'pin7090_ok' => password_verify('7090', (string)$r['pin_hash']),
        ];
    }, $final);
    $out['login_should_work'] = (bool)array_filter(
        $out['rows_after'],
        static fn (array $r): bool => $r['is_active'] === 1 && $r['pin7090_ok']
    );
    $out['status'] = 'ok';
} catch (Throwable $e) {
    http_response_code(500);
    $out['status'] = 'error';
    $out['error']  = $e->getMessage();
}

echo json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
