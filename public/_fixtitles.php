<?php
declare(strict_types=1);

/**
 * TEMPORARY token-gated one-shot data fix: undo double HTML-encoding in movie
 * titles (e.g. a title stored as "&amp;" rendering as a literal &amp; in the
 * page H1/tab). Decodes entities until stable, then UPDATEs. Self-deletes after
 * the token check so a later poll can't run it. REMOVE from repo after running.
 * Usage: /_fixtitles.php?key=THE_TOKEN
 */

$TOKEN = 'fixtitles-9w3k7q2x';
if (!hash_equals($TOKEN, (string)($_GET['key'] ?? ''))) {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/config/config.php';
require_once INCLUDES_PATH . '/Database.php';

header('Content-Type: text/plain; charset=UTF-8');

$db   = Database::getInstance();
$rows = $db->query('SELECT id, title FROM movies')->fetchAll(PDO::FETCH_ASSOC);

$fixed = 0;
foreach ($rows as $r) {
    $orig = (string)$r['title'];
    $dec  = $orig;
    for ($i = 0; $i < 5; $i++) {
        $next = html_entity_decode($dec, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        if ($next === $dec) {
            break;
        }
        $dec = $next;
    }
    if ($dec !== $orig) {
        $u = $db->prepare('UPDATE movies SET title = ? WHERE id = ?');
        $u->execute([$dec, (int)$r['id']]);
        echo "FIXED #{$r['id']}: '{$orig}'  ->  '{$dec}'\n";
        $fixed++;
    } else {
        echo "ok    #{$r['id']}: '{$orig}'\n";
    }
}

echo "\nTotal titles fixed: {$fixed}\n";

@unlink(__FILE__);
echo "(this script has self-deleted from the server)\n";
