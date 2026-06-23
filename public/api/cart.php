<?php
declare(strict_types=1);
session_name('ALEX_ADMIN_SESS');
session_start();
header('Content-Type: application/json');
header('Cache-Control: no-store');

require_once __DIR__ . '/../config/config.php';
require_once INCLUDES_PATH . '/Database.php';
require_once INCLUDES_PATH . '/ConcessionRepo.php';

$action = (string)($_REQUEST['action'] ?? '');

function cartCount(): int
{
    return (int)array_sum(array_column($_SESSION['cart'] ?? [], 'qty'));
}

function cartTotal(): float
{
    $cart = $_SESSION['cart'] ?? [];
    if (empty($cart)) return 0.0;
    try {
        $repo = new ConcessionRepo(Database::getInstance());
        $total = 0.0;
        foreach ($cart as $e) {
            $item = $repo->getById((int)$e['id']);
            if ($item) $total += (float)$item['price'] * (int)$e['qty'];
        }
        return round($total, 2);
    } catch (Throwable $ex) {
        return 0.0;
    }
}

function cartItems(): array
{
    $cart = $_SESSION['cart'] ?? [];
    if (empty($cart)) return [];
    try {
        $repo = new ConcessionRepo(Database::getInstance());
        $out  = [];
        foreach ($cart as $e) {
            $item = $repo->getById((int)$e['id']);
            if (!$item) continue;
            $out[] = [
                'id'       => (int)$e['id'],
                'name'     => $item['name'],
                'price'    => (float)$item['price'],
                'qty'      => (int)$e['qty'],
                'option'   => $e['option'] ?? null,
                'image'    => $item['image_path'] ?? '',
                'subtotal' => round((float)$item['price'] * (int)$e['qty'], 2),
            ];
        }
        return $out;
    } catch (Throwable $ex) {
        return [];
    }
}

function cartPayload(): array
{
    return [
        'ok'    => true,
        'count' => cartCount(),
        'total' => cartTotal(),
        'items' => cartItems(),
    ];
}

switch ($action) {
    case 'add':
        $id  = (int)($_POST['id'] ?? 0);
        $qty = max(1, (int)($_POST['qty'] ?? 1));
        $opt = isset($_POST['option']) && $_POST['option'] !== '' ? $_POST['option'] : null;
        if ($id < 1) {
            echo json_encode(['ok' => false, 'error' => 'Invalid item']);
            exit;
        }
        $cart  = $_SESSION['cart'] ?? [];
        $found = false;
        foreach ($cart as &$e) {
            if ((int)$e['id'] === $id && ($e['option'] ?? null) === $opt) {
                $e['qty'] += $qty;
                $found = true;
                break;
            }
        }
        unset($e);
        if (!$found) $cart[] = ['id' => $id, 'qty' => $qty, 'option' => $opt];
        $_SESSION['cart'] = $cart;
        echo json_encode(cartPayload());
        break;

    case 'remove':
        $id  = (int)($_POST['id'] ?? 0);
        $opt = isset($_POST['option']) && $_POST['option'] !== '' ? $_POST['option'] : null;
        $_SESSION['cart'] = array_values(array_filter(
            $_SESSION['cart'] ?? [],
            fn($e) => !((int)$e['id'] === $id && ($e['option'] ?? null) === $opt)
        ));
        echo json_encode(cartPayload());
        break;

    case 'items':
        echo json_encode(cartPayload());
        break;

    case 'count':
        echo json_encode(['count' => cartCount()]);
        break;

    case 'clear':
        $_SESSION['cart'] = [];
        echo json_encode(['ok' => true, 'count' => 0, 'total' => 0.0, 'items' => []]);
        break;

    default:
        http_response_code(400);
        echo json_encode(['error' => 'Unknown action']);
}
