<?php
declare(strict_types=1);

/**
 * Order fulfillment board API (/api/fulfillment.php).
 *
 * No login — same intentional unauthenticated-but-unlinked model as the
 * /checkin kiosk (see fulfillment.php). GET lists pending paid orders
 * (polled every 10s); POST action=complete marks one fulfilled.
 */

require_once __DIR__ . '/../config/config.php';
require_once INCLUDES_PATH . '/Database.php';
require_once INCLUDES_PATH . '/RateLimiter.php';

header('Content-Type: application/json');
header('Cache-Control: no-store');
header('X-Robots-Tag: noindex, nofollow');

try {
    $db = Database::getInstance();
} catch (\Throwable $e) {
    http_response_code(503);
    echo json_encode(['ok' => false, 'error' => 'Unavailable.']);
    exit;
}

const FULFILL_CHANNEL_BADGES = [
    'website'        => ['label' => 'Online Order', 'class' => 'badge-online'],
    'staff_register'  => ['label' => 'Walk-Up', 'class' => 'badge-walkup'],
    'staff'          => ['label' => 'Walk-Up', 'class' => 'badge-walkup'],
    'kiosk'          => ['label' => 'Kiosk', 'class' => 'badge-kiosk'],
];

function fulfillment_pending_orders(PDO $db): array
{
    $stmt = $db->query(
        "SELECT t.id, t.transaction_ref, t.daily_order_number, t.created_at, t.source_channel,
                ti.item_name, ti.quantity, ti.selected_option
         FROM transactions t
         JOIN transaction_items ti ON ti.transaction_id = t.id
         WHERE t.payment_status = 'paid'
           AND t.fulfillment_status = 'pending'
           AND EXISTS (
               SELECT 1 FROM transaction_items ti2
               WHERE ti2.transaction_id = t.id
                 AND ti2.item_type = 'concession'
           )
         ORDER BY t.created_at ASC, ti.id ASC"
    );
    $orders = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $id = (int)$row['id'];
        if (!isset($orders[$id])) {
            $badge = FULFILL_CHANNEL_BADGES[(string)$row['source_channel']] ?? ['label' => 'Order', 'class' => 'badge-online'];
            $orders[$id] = [
                'id'           => $id,
                'ref'          => (string)$row['transaction_ref'],
                'orderNumber'   => $row['daily_order_number'] !== null ? (string)(int)$row['daily_order_number'] : null,
                'created_at'    => (string)$row['created_at'],
                'channelLabel'  => $badge['label'],
                'channelClass'  => $badge['class'],
                'source_channel'=> (string)$row['source_channel'],
                'items'         => [],
            ];
        }
        $orders[$id]['items'][] = [
            'name'   => (string)$row['item_name'],
            'qty'    => (int)$row['quantity'],
            'option' => $row['selected_option'] !== null ? (string)$row['selected_option'] : null,
        ];
    }
    return array_values($orders);
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        echo json_encode(['ok' => true, 'orders' => fulfillment_pending_orders($db)]);
    } catch (\Throwable $e) {
        error_log('[fulfillment GET] ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => 'Could not load orders.']);
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!RateLimiter::allow('fulfillment-complete:' . RateLimiter::clientIp(), 60, 60)) {
        RateLimiter::reject429();
    }
    $raw  = file_get_contents('php://input') ?: '';
    $data = json_decode($raw, true);
    $id   = is_array($data) ? (int)($data['id'] ?? 0) : (int)($_POST['id'] ?? 0);
    if ($id < 1) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Missing order id.']);
        exit;
    }
    try {
        $stmt = $db->prepare(
            "UPDATE transactions SET fulfillment_status = 'fulfilled'
             WHERE id = :id AND payment_status = 'paid' AND fulfillment_status = 'pending'"
        );
        $stmt->execute([':id' => $id]);
        echo json_encode(['ok' => true, 'updated' => $stmt->rowCount() === 1]);
    } catch (\Throwable $e) {
        error_log('[fulfillment POST] ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => 'Could not update the order.']);
    }
    exit;
}

http_response_code(405);
echo json_encode(['ok' => false, 'error' => 'Method not allowed.']);
