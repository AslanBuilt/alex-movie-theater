<?php
// One-time setup script — self-deletes after running
error_reporting(E_ALL);
ini_set('display_errors', '1');

$config_dir = __DIR__ . '/config';
$root_path  = dirname($config_dir);
$db_config  = $root_path . '/config/database.php';

if (!file_exists($db_config)) {
    die("database.php not found at $db_config");
}

$cfg = require $db_config;
$pdo = new PDO(
    "mysql:host={$cfg['host']};dbname={$cfg['database']};charset=utf8mb4",
    $cfg['username'], $cfg['password'],
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

$log = [];

// 1. Fix admin password to "changeme123"
$hash = password_hash('changeme123', PASSWORD_BCRYPT);
$pdo->prepare("UPDATE admin_users SET password_hash = ? WHERE username = 'admin'")->execute([$hash]);
$log[] = "Admin password reset to: changeme123";

// 2. Fix concession image paths (images/ -> assets/images/)
$images = [
    'Two Person Combo'       => 'assets/images/concessions/combo-two.webp',
    'One Person Combo'       => 'assets/images/concessions/combo-one.webp',
    'Kids Combo'             => 'assets/images/concessions/combo-kids.webp',
    'Large Popcorn (170oz)'  => 'assets/images/concessions/popcorn-large.webp',
    'Medium Popcorn (130oz)' => 'assets/images/concessions/popcorn-medium.webp',
    'Small Popcorn (85oz)'   => 'assets/images/concessions/popcorn-small.webp',
    'Large Fountain (32oz)'  => 'assets/images/concessions/drink-fountain.webp',
    'Medium Fountain (20oz)' => 'assets/images/concessions/drink-fountain.webp',
    'Bottle Drinks'          => 'assets/images/concessions/drink-bottle.webp',
    'Box Candy'              => 'assets/images/concessions/candy-box.webp',
    'Wrapper Candy'          => 'assets/images/concessions/candy-box.webp',
    'Cotton Candy'           => 'assets/images/concessions/candy-cotton.webp',
];

$total = 0;
foreach ($images as $name => $path) {
    $stmt = $pdo->prepare("UPDATE concessions SET image_path = ? WHERE name = ?");
    $stmt->execute([$path, $name]);
    $n = $stmt->rowCount();
    $total += $n;
    $log[] = ($n ? "OK" : "no match") . " — $name => $path";
}
$log[] = "Image paths updated: $total rows";

@unlink(__FILE__);
?>
<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Setup Done</title>
<style>body{font-family:monospace;padding:2rem;max-width:700px}
.ok{color:green}.label{font-weight:bold;margin-top:1rem;display:block}</style>
</head><body>
<h2>Setup complete</h2>
<span class="label">Results:</span>
<ul>
<?php foreach ($log as $line): ?>
  <li><?= htmlspecialchars($line) ?></li>
<?php endforeach; ?>
</ul>
<p><em>This script has deleted itself.</em></p>
<p>
  <a href="admin/">Go to admin login</a> &mdash; use <strong>admin / changeme123</strong><br>
  <a href="concessions.php">Check concessions page</a>
</p>
</body></html>
