<?php
$token = '155de445ec2e22adbcab7bdbabcd037e';
if (!isset($_GET['t']) || $_GET['t'] !== $token) {
    http_response_code(403); exit;
}
header('Content-Type: text/plain');

require_once __DIR__ . '/config/config.php';
require_once INCLUDES_PATH . '/Database.php';

$log = ini_get('error_log');
echo "Error log path: $log\n\n";

if ($log && file_exists($log)) {
    $lines = file($log);
    $tail  = array_slice($lines, -100);
    $mail_lines = array_filter($tail, function ($l) {
        return stripos($l, 'mail') !== false
            || stripos($l, 'Mailer') !== false
            || stripos($l, 'smtp') !== false
            || stripos($l, 'email') !== false
            || stripos($l, 'sendgrid') !== false;
    });
    echo "=== Mail-related log lines (last 100) ===\n";
    foreach ($mail_lines as $line) {
        echo $line;
    }
    if (!$mail_lines) {
        echo "(none found)\n";
    }
} else {
    echo "Error log not found or not readable at: $log\n";
}

echo "\n=== PHP mail config ===\n";
echo "mail() exists: " . (function_exists('mail') ? "yes" : "no") . "\n";
echo "sendmail_path: " . ini_get('sendmail_path') . "\n";
echo "SMTP: " . ini_get('SMTP') . "\n";
echo "smtp_port: " . ini_get('smtp_port') . "\n";

echo "\n=== Mailer config ===\n";
if (is_file(INCLUDES_PATH . '/Mailer.php')) {
    require_once INCLUDES_PATH . '/Mailer.php';
    echo "Mailer::isConfigured() (SendGrid): " . (Mailer::isConfigured() ? "yes" : "no — will use PHP mail()") . "\n";
} else {
    echo "Mailer.php not found\n";
}

echo "\n=== Last 3 transactions ===\n";
try {
    $pdo  = Database::getInstance();
    $stmt = $pdo->query(
        "SELECT transaction_ref, payment_status, customer_email, created_at
         FROM transactions
         ORDER BY created_at DESC LIMIT 3"
    );
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        echo $row['transaction_ref'] . ' | ' . $row['payment_status'] . ' | email=[' . ($row['customer_email'] ?? '') . '] | ' . $row['created_at'] . "\n";
    }
} catch (\Throwable $e) {
    echo "DB query failed: " . $e->getMessage() . "\n";
}

echo "\n=== Final confirmation — latest transaction + its tickets ===\n";
try {
    $pdo  = Database::getInstance();
    $stmt = $pdo->query(
        "SELECT t.transaction_ref, t.payment_status,
                tk.ticket_token, tk.token_status, tk.checked_in_at
         FROM transactions t
         LEFT JOIN ticket_tokens tk ON tk.transaction_id = t.id
         ORDER BY t.created_at DESC LIMIT 1"
    );
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        echo 'ref=' . $row['transaction_ref']
            . ' payment_status=' . $row['payment_status']
            . ' token_status=' . ($row['token_status'] ?? 'NULL')
            . ' checked_in_at=' . ($row['checked_in_at'] ?? 'NULL')
            . "\n";
    }
} catch (\Throwable $e) {
    echo "DB query failed: " . $e->getMessage() . "\n";
}
