<?php
declare(strict_types=1);

/**
 * TEMPORARY token-gated diagnostic. Confirms whether the QR codes embedded
 * in real confirmation emails are genuine scannable codes or the GD
 * placeholder box (renderPlaceholder() in QrCode.php).
 * Usage: /_qrdiag.php?key=THE_TOKEN
 * REMOVE after use.
 */

$TOKEN = 'qrdiag-4h8p2w6t';
if (!hash_equals($TOKEN, (string)($_GET['key'] ?? ''))) {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/config/config.php';
require_once INCLUDES_PATH . '/Database.php';
require_once INCLUDES_PATH . '/TransactionRepo.php';
require_once INCLUDES_PATH . '/TicketTokenRepo.php';
require_once INCLUDES_PATH . '/QrCode.php';
require_once INCLUDES_PATH . '/OrderEmail.php';

header('Content-Type: text/plain; charset=UTF-8');

echo "=== Server capability ===\n";
echo "GD (imagecreatetruecolor): " . (function_exists('imagecreatetruecolor') ? 'yes' : 'NO') . "\n";
echo "QrCode::isReady():         " . (QrCode::isReady() ? 'yes (real QR)' : 'no (WILL USE PLACEHOLDER)') . "\n";

echo "\n=== Most recent paid transaction with tickets ===\n";
$rows = TransactionRepo::getRecent(10);
$target = null;
foreach ($rows as $r) {
    if (($r['payment_status'] ?? '') === 'paid') {
        $tix = TicketTokenRepo::getByTransaction((int)$r['id']);
        if ($tix) {
            $target = $r;
            $tickets = $tix;
            break;
        }
    }
}

if ($target === null) {
    echo "No paid transaction with tickets found in the last 10 rows.\n";
    exit;
}

echo "ref=" . $target['transaction_ref'] . "  id=" . $target['id'] . "  email=" . ($target['customer_email'] ?: '(blank)') . "\n";
echo "ticket count: " . count($tickets) . "\n";

echo "\n=== Rendering OrderEmail::build() for this transaction ===\n";
$target['items'] = TransactionRepo::getItems((int)$target['id']);
$mail = OrderEmail::build($target, $tickets);
echo "inlineImages count: " . count($mail['inlineImages']) . "\n";

foreach ($mail['inlineImages'] as $img) {
    $bytes = $img['bytes'];
    $info  = getimagesizefromstring($bytes);
    $w     = $info[0] ?? '?';
    $h     = $info[1] ?? '?';
    $len   = strlen($bytes);
    $verdict = ($w === 224 && $h === 224) ? 'LIKELY PLACEHOLDER (matches fixed 224x224 box size)' : 'likely real QR (non-placeholder dimensions)';
    echo "cid={$img['cid']}  {$w}x{$h}  {$len} bytes  -> $verdict\n";
}
