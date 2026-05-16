<?php
require_once __DIR__ . '/../auth.php';
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Authorization, Content-Type');
header('Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

$u = currentUser();
if (!$u) { echo json_encode(['ok' => false]); exit; }
echo json_encode(['ok' => true, 'role' => (int)$u['role']]);
