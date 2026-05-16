<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/token_auth.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Authorization, Content-Type');

$user = authenticateApiToken();
if (!$user) {
    if (!empty($_COOKIE[session_name()])) { session_start(); }
    $user = currentUser();
}

if (!$user) {
    http_response_code(401);
    echo json_encode(['ok'=>false,'error'=>'Unauthorized — provide a valid token or session']);
    exit;
}

echo json_encode([
    'ok'       => true,
    'id'       => (int)$user['id'],
    'username' => $user['username'],
    'email'    => $user['email'] ?? '',
    'role'     => (int)$user['role'],
    'role_name'=> getRoleName((int)$user['role']),
    'center_id'=> $user['center_id'] ? (int)$user['center_id'] : null,
    'lang'     => $user['lang'] ?? 'en',
]);
