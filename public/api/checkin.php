<?php
declare(strict_types=1);

/**
 * Ticket check-in API (/api/checkin.php) — backs the /checkin kiosk.
 *
 * Single atomic UPDATE...WHERE token_status='valid' to claim a token; a
 * follow-up SELECT (after the claim, not before it) distinguishes "already
 * used" from "never existed/voided" for the response. No login — same
 * intentional unauthenticated-but-unlinked model as /fulfillment.
 */

require_once __DIR__ . '/../config/config.php';
require_once INCLUDES_PATH . '/Database.php';
require_once INCLUDES_PATH . '/RateLimiter.php';

header('Content-Type: application/json');
header('Cache-Control: no-store');
header('X-Robots-Tag: noindex, nofollow');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['result' => 'invalid', 'message' => 'Method not allowed.']);
    exit;
}

if (!RateLimiter::allow('checkin:' . RateLimiter::clientIp(), 60, 60)) {
    RateLimiter::reject429();
}

try {
    $db = Database::getInstance();
} catch (\Throwable $e) {
    http_response_code(503);
    echo json_encode(['result' => 'invalid', 'message' => 'Unavailable.']);
    exit;
}

$raw    = file_get_contents('php://input') ?: '';
$data   = json_decode($raw, true);
$token  = is_array($data) ? trim((string)($data['token'] ?? '')) : '';
$terminal = is_array($data) ? trim((string)($data['terminal'] ?? '')) : '';
$terminal = $terminal !== '' ? substr($terminal, 0, 100) : 'Front Door';

if ($token === '' || strlen($token) > 128) {
    echo json_encode(['result' => 'invalid', 'message' => 'Invalid ticket — please see staff.']);
    exit;
}

try {
    $upd = $db->prepare(
        "UPDATE ticket_tokens
         SET token_status = 'used', checked_in_at = NOW(), checked_in_terminal = :terminal
         WHERE ticket_token = :token AND token_status = 'valid'"
    );
    $upd->execute([':terminal' => $terminal, ':token' => $token]);

    if ($upd->rowCount() === 1) {
        $stmt = $db->prepare(
            "SELECT tt.checked_in_at, m.title AS movie_title, s.showtime_date, s.showtime_time, s.label
             FROM ticket_tokens tt
             LEFT JOIN movies m ON m.id = tt.movie_id
             LEFT JOIN showtimes s ON s.id = tt.showtime_id
             WHERE tt.ticket_token = :token"
        );
        $stmt->execute([':token' => $token]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        $when = '';
        if (!empty($row['showtime_date'])) {
            try {
                $when = (new DateTime((string)$row['showtime_date']))->format('D, M j');
            } catch (\Throwable $e) {
                $when = '';
            }
            if (!empty($row['showtime_time'])) {
                $when .= ' ' . date('g:i A', strtotime((string)$row['showtime_time']));
            }
        } elseif (!empty($row['label'])) {
            $when = (string)$row['label'];
        }

        echo json_encode([
            'result'     => 'success',
            'movieTitle' => (string)($row['movie_title'] ?? 'your movie'),
            'when'       => $when,
        ]);
        exit;
    }

    // Not claimed — find out why.
    $stmt = $db->prepare('SELECT token_status, checked_in_at FROM ticket_tokens WHERE ticket_token = :token');
    $stmt->execute([':token' => $token]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($row && $row['token_status'] === 'used') {
        echo json_encode([
            'result'      => 'used',
            'message'     => 'Ticket already scanned',
            'checkedInAt' => $row['checked_in_at'] !== null ? date('g:i A', strtotime((string)$row['checked_in_at'])) : '',
        ]);
        exit;
    }

    echo json_encode(['result' => 'invalid', 'message' => 'Invalid ticket — please see staff.']);
} catch (\Throwable $e) {
    error_log('[checkin] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['result' => 'invalid', 'message' => 'Could not check this ticket. Please see staff.']);
}
