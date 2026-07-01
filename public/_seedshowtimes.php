<?php
declare(strict_types=1);

/**
 * TEMPORARY token-gated one-shot seed: adds a few near-future showtimes for
 * the two existing active movies so the site has purchasable inventory again
 * (the prior seed from 2026-06-25 covered 06/26-06/28, now expired) and so
 * the QR ticket E2E test has something to buy. Self-deletes after running.
 * Owner manages real scheduling going forward via admin -> Showtimes.
 */

$TOKEN = 'seedst-4b7e9a2f1c8d6053';
if (($_GET['token'] ?? '') !== $TOKEN) {
    http_response_code(404);
    exit('Not found');
}

require_once __DIR__ . '/config/config.php';
require_once INCLUDES_PATH . '/Database.php';

header('Content-Type: application/json');
$out = ['created' => []];

try {
    $pdo = Database::getInstance();

    $movies = $pdo->query("SELECT id, title FROM movies WHERE is_active = 1 ORDER BY id ASC LIMIT 2")
                  ->fetchAll(PDO::FETCH_ASSOC);
    if (!$movies) {
        throw new RuntimeException('no active movies found');
    }
    $out['movies'] = $movies;

    $ins = $pdo->prepare(
        "INSERT INTO showtimes (movie_id, label, times, showtime_date, showtime_time, available_tickets, tickets_sold, is_active, sort_order)
         VALUES (:movie_id, '', '', :date, :time, 50, 0, 1, :sort)"
    );

    $times = ['19:00:00'];
    $sort = 0;
    for ($d = 0; $d < 4; $d++) {
        $date = date('Y-m-d', strtotime("+$d day"));
        foreach ($movies as $m) {
            foreach ($times as $t) {
                $ins->execute([':movie_id' => $m['id'], ':date' => $date, ':time' => $t, ':sort' => $sort++]);
                $out['created'][] = ['showtime_id' => (int)$pdo->lastInsertId(), 'movie' => $m['title'], 'date' => $date, 'time' => $t];
            }
        }
    }
} catch (Throwable $e) {
    $out['error'] = $e->getMessage();
}

echo json_encode($out, JSON_PRETTY_PRINT);
@unlink(__FILE__);
