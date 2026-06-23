<?php
require_once __DIR__ . '/../config/config.php';
require_once INCLUDES_PATH . '/AdminAuth.php';
require_once INCLUDES_PATH . '/Database.php';

AdminAuth::requireLogin();

$updates = [
    'Two Person Combo'       => 'assets/images/concessions/combo-two.png',
    'One Person Combo'       => 'assets/images/concessions/combo-one.png',
    'Kids Combo'             => 'assets/images/concessions/combo-kids.png',
    'Large Popcorn (170oz)'  => 'assets/images/concessions/popcorn-large.png',
    'Medium Popcorn (130oz)' => 'assets/images/concessions/popcorn-medium.png',
    'Small Popcorn (85oz)'   => 'assets/images/concessions/popcorn-small.png',
    'Large Fountain (32oz)'  => 'assets/images/concessions/drink-fountain.png',
    'Medium Fountain (20oz)' => 'assets/images/concessions/drink-fountain.png',
    'Bottle Drinks'          => 'assets/images/concessions/drink-bottle.png',
    'Box Candy'              => 'assets/images/concessions/candy-box.png',
    'Wrapper Candy'          => 'assets/images/concessions/candy-box.png',
    'Cotton Candy'           => 'assets/images/concessions/candy-cotton.png',
];

$db = Database::getInstance();
$updated = 0;
$rows = [];

foreach ($updates as $name => $path) {
    $stmt = $db->prepare("UPDATE concessions SET image_path = ? WHERE name = ?");
    $stmt->execute([$path, $name]);
    $count = $stmt->rowCount();
    $updated += $count;
    $rows[] = "$name → $path ($count row" . ($count !== 1 ? 's' : '') . " updated)";
}

// Self-delete after running
@unlink(__FILE__);
?>
<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Image Paths Fixed</title>
<style>body{font-family:monospace;padding:2rem;} li{margin:.3rem 0;} .ok{color:green;}</style>
</head><body>
<h2>Concession image_path update</h2>
<p class="ok">Total rows updated: <strong><?= $updated ?></strong></p>
<ul>
<?php foreach ($rows as $r): ?><li><?= htmlspecialchars($r) ?></li><?php endforeach; ?>
</ul>
<p><em>This script has deleted itself.</em></p>
<p><a href="index.php">Back to admin</a></p>
</body></html>
