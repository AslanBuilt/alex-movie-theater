<?php
declare(strict_types=1);

/**
 * ONE-SHOT seed: create transactional (purchasable) showtimes for the two
 * current films, then deactivate the stale legacy rows. Token-gated;
 * untokened requests are non-destructive; self-deletes after a real run.
 * Idempotent: skips inserting if transactional rows already exist.
 */

$TOKEN = 'seed-Wm4Qz7Lx2Pn9Tk';

header('Content-Type: application/json');

if (($_GET['token'] ?? '') !== $TOKEN) {
    echo '{"diag":"alive"}';
    return;
}

require_once __DIR__ . '/config/config.php';
require_once INCLUDES_PATH . '/Database.php';
require_once INCLUDES_PATH . '/ShowtimeRepo.php';

// Star Wars (movie 1) and Sheep Detectives (movie 2), this Fri–Sun.
// 24h times so they store cleanly in the showtime_time TIME column;
// movie.php reformats to "1:00 PM" for display.
$schedule = [
    1 => ['13:00', '16:00', '19:15'],
    2 => ['13:30', '16:30', '19:30'],
];
$dates = ['2026-06-26', '2026-06-27', '2026-06-28'];
$seats = 50;

$out = ['ok' => false, 'actions' => []];

try {
    $pdo = Database::getInstance();

    // Idempotency guard — don't double-seed.
    $existing = (int) $pdo->query(
        "SELECT COUNT(*) FROM showtimes
         WHERE movie_id IN (1,2) AND showtime_time IS NOT NULL AND showtime_time <> ''"
    )->fetchColumn();

    if ($existing > 0) {
        $out['skipped'] = "transactional showtimes already exist ($existing) — not re-seeding";
    } else {
        $inserted = 0;
        $sort = 100;
        foreach ($schedule as $movieId => $times) {
            foreach ($dates as $d) {
                foreach ($times as $t) {
                    $id = ShowtimeRepo::createTransactional($movieId, $d, $t, $seats, $sort++);
                    if ($id > 0) {
                        $inserted++;
                    } else {
                        $out['actions'][] = "FAILED insert movie $movieId $d $t";
                    }
                }
            }
        }
        $out['inserted'] = $inserted;

        // Deactivate the legacy (non-purchasable) rows for these movies.
        $deact = $pdo->prepare(
            "UPDATE showtimes SET is_active = 0
             WHERE movie_id IN (1,2) AND (showtime_time IS NULL OR showtime_time = '')"
        );
        $deact->execute();
        $out['legacy_deactivated'] = $deact->rowCount();
    }

    // Report current transactional rows for confirmation.
    $rows = $pdo->query(
        "SELECT movie_id, showtime_date, showtime_time, available_tickets, tickets_sold, is_active
         FROM showtimes
         WHERE movie_id IN (1,2) AND showtime_time IS NOT NULL AND showtime_time <> ''
         ORDER BY movie_id, showtime_date, showtime_time"
    )->fetchAll(PDO::FETCH_ASSOC);
    $out['transactional_rows'] = $rows;
    $out['ok'] = true;
} catch (Throwable $e) {
    $out['error'] = $e->getMessage();
}

echo json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

// Self-delete only after a real (tokened) run completes.
@unlink(__FILE__);
