<?php
// Location merged into the unified Visit & Contact page. Preserve inbound links.
require_once __DIR__ . '/config/config.php';

header('Location: ' . url('contact.php'), true, 301);
exit;
