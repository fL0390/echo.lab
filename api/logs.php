<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Authorization, Content-Type');
header('Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }


if (!TESTING_MODE) {
    echo json_encode([]);
    exit;
}

if (!isLoggedIn() || (int)currentUser()['role'] < ROLE_ADMIN) {
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    $logPath = __DIR__ . '/../data/logs.json';
    file_put_contents($logPath, '[]');
    echo json_encode(['ok' => true]);
    exit;
}

$logPath = __DIR__ . '/../data/logs.json';
if (!file_exists($logPath)) {
    echo json_encode([]);
    exit;
}

$after = (int)($_GET['after'] ?? 0);
$entries = json_decode(file_get_contents($logPath), true) ?: [];

if ($after > 0 && $after < count($entries)) {
    $entries = array_slice($entries, $after);
}

echo json_encode(['total' => count(json_decode(file_get_contents($logPath), true) ?: []), 'entries' => $entries]);
