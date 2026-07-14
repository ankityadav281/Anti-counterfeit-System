<?php
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json');

$database = new Database();
$db = $database->getConnection();

echo json_encode([
    'service' => app_config('name'),
    'environment' => app_config('env'),
    'debug' => app_is_debug(),
    'database' => $db instanceof PDO ? 'ok' : 'failed',
    'php' => PHP_VERSION,
    'time' => date('c'),
]);
?>
