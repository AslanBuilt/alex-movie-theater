<?php
declare(strict_types=1);

/**
 * ONE-SHOT inspector + seeder for movie 1/2 showtimes.
 * Token-gated; untokened is non-destructive; self-deletes after a real run.
 * Dumps all showtime rows, and if there are no ACTIVE transactional rows,
 * creates them (this Fri–Sun) and deactivates legacy rows.
 */

$TOKEN = 'insp-Rt6Yh2Vq8Zb';

header('Content-Type: application/json');

if (($_GET['token'] ?? '') !== $TOKEN) {
    echo '{"diag":"alive"}';
    return;
}

require_once __DIR__ . '/config/config.php';
require_once INCLUDES_PATH . '/Database.php';
require_once INCLUDES_PATH . '/ShowtimeRepo.php';

$out = ['ok' => false];

$dump = static function (PDO $pdo): array {
    return $pdo->query(
        "SELECT id, movie_id, label, times, showtime_date, showtime_time,
                available_tickets, tickets_sold, is_active
         FROM showtimes WHERE movie_id IN (1,2)
         ORDER BY movie_id, id"
    )->fetchAll(PDO::FETCH_ASSOC);
};

try {
    $pdo = Database::getInstance();

    $out['before'] = $dump($pdo);

    $activeTrans = (int) $pdo->query(
        "SELECT COUNT(*) FROM showtimes
         WHERE movie_id IN (1,2)
           AND showtime_time IS NOT NULL AND showtime_time <> ''
           AND is_active = 1"
    )->fetchColumn();
    $out['active_transactional_before'] = $activeTrans;

    if ($activeTrans === 0) {
        $schedule = [
            1 => ['13:00', '16:00', '19:15'],
            2 => ['13:30', '16:30', '19:30'],
        ];
        $dates = ['2026-06-26', '2026-06-27', '2026-06-28'];
        $inserted = 0; $sort = 100; $fails = [];
        foreach ($schedule as $movieId => $times) {
            foreach ($dates as $d) {
                foreach ($times as $t) {
                    $id = ShowtimeRepo::createTransactional($movieId, $d, $t, 50, $sort++);
                    if ($id > 0) { $inserted++; } else { $fails[] = "m$movieId $d $t"; }
                }
            }
        }
        $out['inserted'] = $inserted;
        $out['insert_failures'] = $fails;

        $deact = $pdo->prepare(
            "UPDATE showtimes SET is_active = 0
             WHERE movie_id IN (1,2) AND (showtime_time IS NULL OR showtime_time = '')"
        );
        $deact->execute();
        $out['legacy_deactivated'] = $deact->rowCount();

        $out['after'] = $dump($pdo);
    }

    $out['ok'] = true;
} catch (Throwable $e) {
    $out['error'] = $e->getMessage();
}

echo json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

@unlink(__FILE__);
