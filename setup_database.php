<?php
/** Install the reconstructed schema in an empty database. Run only from the CLI. */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit(); }
$name = getenv('DB_NAME') ?: 'nexlace';
if (!preg_match('/^[a-zA-Z0-9_]+$/', $name)) {
    fwrite(STDERR, "DB_NAME must contain only letters, digits and underscores.\n"); exit(1);
}
try {
    $host = getenv('DB_HOST') ?: 'localhost'; $port = getenv('DB_PORT') ?: '3306';
    $pdo = new PDO("mysql:host=$host;port=$port;charset=utf8mb4", getenv('DB_USER') ?: 'nexlace_app', getenv('DB_PASSWORD') ?: '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $check = $pdo->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = ?');
    $check->execute([$name]);
    if ((int) $check->fetchColumn() !== 0) throw new RuntimeException('Database must be empty. Existing tables will not be overwritten.');
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `$name` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo->exec("USE `$name`");
    $pdo->exec(file_get_contents(__DIR__ . '/nexlace_schema.sql'));
    echo "Installed schema. No accounts or default passwords were created.\n";
} catch (Throwable $e) {
    fwrite(STDERR, "Setup failed: " . $e->getMessage() . "\n"); exit(1);
}
