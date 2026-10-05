<?php
// Explicit local CLI provisioning; no default account or password is installed.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit();
}
require_once __DIR__ . '/../config/database.php';

$username = $argv[1] ?? '';
$password = getenv('NEXLACE_ADMIN_PASSWORD');
if (!preg_match('/^[A-Za-z0-9_.-]{3,50}$/', $username) || !$password || strlen($password) < 16) {
    fwrite(STDERR, "Usage: set NEXLACE_ADMIN_PASSWORD (at least 16 characters), then php scripts/provision-admin.php <username> [full-name]\n");
    exit(1);
}
$db = (new Database())->getConnection();
if (!$db) {
    exit(1);
}
$stmt = $db->prepare('INSERT INTO admin_users (username, password, full_name) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE password = VALUES(password), full_name = VALUES(full_name)');
$stmt->execute([$username, password_hash($password, PASSWORD_DEFAULT), $argv[2] ?? $username]);
fwrite(STDOUT, "Admin password hash provisioned. Remove NEXLACE_ADMIN_PASSWORD from the environment.\n");
