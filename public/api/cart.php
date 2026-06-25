<?php
declare(strict_types=1);
session_name('ALEX_ADMIN_SESS');
session_start();
header('Content-Type: application/json');
header('Cache-Control: no-store');

require_once __DIR__ . '/../config/config.php';
require_once INCLUDES_PATH . '/Database.php';
require_once INCLUDES_PATH . '/ConcessionRepo.php';
require_once INCLUDES_PATH . '/ShowtimeRepo.php';
require_once INCLUDES_PATH . '/MovieRepo.php';
require_once INCLUDES_PATH . '/RateLimiter.php';

// Flat ticket price, mirrored in checkout.php and movie.php.
const CART_TICKET_PRICE = 5.00;

$action = (string)($_REQUEST['action'] ?? '');

// Throttle only the mutating actions; count/items fire on every page load to
// render the cart badge and must not be limited. Fails open (see RateLimiter).
if (in_array($action, ['add', 'update', 'remove', 'clear'], true)) {
    if (!RateLimiter::allow('cart:' . RateLimiter::clientIp(), 60, 60)) {
        RateLimiter::reject429();
    }
}

/**
 * Reject mutating requests that don't carry a valid CSRF token.
 * Token is sent via the X-CSRF-Token header (falls back to a POST field).
 */
function requireCsrf(): void
{
    $hdr = (string)($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    $tok = $hdr !== '' ? $hdr : (string)($_POST['csrf_token'] ?? '');
    if (empty($_SESSION['csrf_token']) || !hash_equals((string)$_SESSION['csrf_token'], $tok)) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'error' => 'Invalid session token']);
        exit;
    }
}

/** A cart entry is a ticket when it carries type=ticket; everything else is a concession. */
function entryIsTicket(array $e): bool
{
    return ($e['type'] ?? 'concession') === 'ticket';
}

/** Does a cart entry match the (type, id, option) the client is acting on? */
function entryMatches(array $e, string $type, int $id, ?string $opt): bool
{
    if ((int)($e['id'] ?? 0) !== $id) return false;
    if ($type === 'ticket') return entryIsTicket($e);
    return !entryIsTicket($e) && (($e['option'] ?? null) === $opt);
}

/** Seats still purchasable for a showtime, or 0 if it's gone/inactive. */
function ticketAvailable(int $showtimeId): int
{
    $st = tryDb(fn() => ShowtimeRepo::getById($showtimeId), null);
    if (!$st || empty($st['is_active'])) return 0;
    return max(0, (int)$st['available_tickets'] - (int)$st['tickets_sold']);
}

/** Cheap badge count straight off the raw session — no DB. */
function cartCount(): int
{
    return (int)array_sum(array_column($_SESSION['cart'] ?? [], 'qty'));
}

/**
 * Resolve every cart entry into a display/line item, pricing tickets and
 * concessions server-side. Entries that no longer resolve (deleted item,
 * inactive showtime) are silently dropped so the cart self-heals.
 */
function cartItems(): array
{
    $cart = $_SESSION['cart'] ?? [];
    if (empty($cart)) return [];

    $out      = [];
    $concRepo = null;

    foreach ($cart as $e) {
        $qty = max(0, (int)($e['qty'] ?? 0));
        if ($qty < 1) continue;

        if (entryIsTicket($e)) {
            $st = tryDb(fn() => ShowtimeRepo::getById((int)$e['id']), null);
            if (!$st || empty($st['is_active'])) continue;

            $movie = tryDb(fn() => MovieRepo::getById((int)$st['movie_id']), null);
            $title = $movie['title'] ?? 'Movie';

            $when = '';
            if (!empty($st['showtime_date'])) {
                $when = (new DateTime($st['showtime_date']))->format('D, M j');
                if (!empty($st['showtime_time'])) {
                    $when .= ' ' . date('g:i A', strtotime((string)$st['showtime_time']));
                }
            } elseif (!empty($st['label'])) {
                $when = (string)$st['label'];
            }

            $available = max(0, (int)$st['available_tickets'] - (int)$st['tickets_sold']);
            $out[] = [
                'type'      => 'ticket',
                'id'        => (int)$e['id'],
                'name'      => 'Ticket: ' . $title . ($when !== '' ? ' — ' . $when : ''),
                'price'     => CART_TICKET_PRICE,
                'qty'       => $qty,
                'option'    => null,
                'image'     => '',
                'available' => $available,
                'subtotal'  => round(CART_TICKET_PRICE * $qty, 2),
            ];
        } else {
            if ($concRepo === null) {
                $db = tryDb(fn() => Database::getInstance(), null);
                if ($db === null) continue; // DB unavailable → degrade gracefully
                $concRepo = new ConcessionRepo($db);
            }
            $item = $concRepo->getById((int)$e['id']);
            if (!$item) continue;
            $out[] = [
                'type'     => 'concession',
                'id'       => (int)$e['id'],
                'name'     => $item['name'],
                'price'    => (float)$item['price'],
                'qty'      => $qty,
                'option'   => $e['option'] ?? null,
                'image'    => assetRel($item['image_path'] ?? ''),
                'subtotal' => round((float)$item['price'] * $qty, 2),
            ];
        }
    }
    return $out;
}

function cartPayload(): array
{
    $items = cartItems();
    $count = 0;
    $total = 0.0;
    foreach ($items as $i) {
        $count += (int)$i['qty'];
        $total += (float)$i['subtotal'];
    }
    return [
        'ok'    => true,
        'count' => $count,
        'total' => round($total, 2),
        'items' => $items,
    ];
}

switch ($action) {
    case 'add':
        requireCsrf();
        $type = ($_POST['type'] ?? 'concession') === 'ticket' ? 'ticket' : 'concession';
        $id   = (int)($_POST['id'] ?? 0);
        $qty  = max(1, (int)($_POST['qty'] ?? 1));
        $opt  = ($type === 'concession' && isset($_POST['option']) && $_POST['option'] !== '') ? $_POST['option'] : null;
        if ($id < 1) {
            echo json_encode(['ok' => false, 'error' => 'Invalid item']);
            exit;
        }

        $cart = $_SESSION['cart'] ?? [];

        if ($type === 'ticket') {
            $avail = ticketAvailable($id);
            if ($avail < 1) {
                echo json_encode(['ok' => false, 'error' => 'This showtime is sold out or no longer available.']);
                exit;
            }
            $found = false;
            foreach ($cart as &$e) {
                if (entryMatches($e, 'ticket', $id, null)) {
                    $e['qty'] = min($avail, (int)$e['qty'] + $qty);
                    $found = true;
                    break;
                }
            }
            unset($e);
            if (!$found) $cart[] = ['type' => 'ticket', 'id' => $id, 'qty' => min($avail, $qty)];
        } else {
            $found = false;
            foreach ($cart as &$e) {
                if (entryMatches($e, 'concession', $id, $opt)) {
                    $e['qty'] += $qty;
                    $found = true;
                    break;
                }
            }
            unset($e);
            if (!$found) $cart[] = ['type' => 'concession', 'id' => $id, 'qty' => $qty, 'option' => $opt];
        }

        $_SESSION['cart'] = $cart;
        echo json_encode(cartPayload());
        break;

    case 'update':
        requireCsrf();
        $type = ($_POST['type'] ?? 'concession') === 'ticket' ? 'ticket' : 'concession';
        $id   = (int)($_POST['id'] ?? 0);
        $qty  = max(0, (int)($_POST['qty'] ?? 0));
        $opt  = ($type === 'concession' && isset($_POST['option']) && $_POST['option'] !== '') ? $_POST['option'] : null;
        // Never let a ticket qty climb past remaining seats.
        if ($type === 'ticket' && $qty > 0) {
            $qty = min($qty, ticketAvailable($id));
        }
        $cart = $_SESSION['cart'] ?? [];
        if ($qty === 0) {
            $cart = array_values(array_filter($cart, fn($e) => !entryMatches($e, $type, $id, $opt)));
        } else {
            foreach ($cart as &$e) {
                if (entryMatches($e, $type, $id, $opt)) { $e['qty'] = $qty; break; }
            }
            unset($e);
        }
        $_SESSION['cart'] = $cart;
        echo json_encode(cartPayload());
        break;

    case 'remove':
        requireCsrf();
        $type = ($_POST['type'] ?? 'concession') === 'ticket' ? 'ticket' : 'concession';
        $id   = (int)($_POST['id'] ?? 0);
        $opt  = ($type === 'concession' && isset($_POST['option']) && $_POST['option'] !== '') ? $_POST['option'] : null;
        $_SESSION['cart'] = array_values(array_filter(
            $_SESSION['cart'] ?? [],
            fn($e) => !entryMatches($e, $type, $id, $opt)
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
        requireCsrf();
        $_SESSION['cart'] = [];
        echo json_encode(['ok' => true, 'count' => 0, 'total' => 0.0, 'items' => []]);
        break;

    default:
        http_response_code(400);
        echo json_encode(['error' => 'Unknown action']);
}
