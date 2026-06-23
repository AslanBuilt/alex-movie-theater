<?php
require_once __DIR__ . '/config/config.php';
require_once INCLUDES_PATH . '/Database.php';

if (!Database::isAvailable()) {
    die('Database not available.');
}

$db = Database::getInstance();
$stmt = $db->query(
    "UPDATE concessions SET image_path = CONCAT('assets/', image_path) WHERE image_path LIKE 'images/%'"
);
$rows = $stmt->rowCount();
echo "Done. Fixed $rows row(s). You can delete this file now.";
