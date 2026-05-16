<?php
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../db.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Authorization, Content-Type');
header('Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }


$me = currentUser();
if (!$me || (int)$me['role'] < ROLE_TEACHER) {
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden']);
    exit;
}

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    echo json_encode(['error' => 'Invalid ID']);
    exit;
}

$user = DB::findUserById($id);
if (!$user) {
    echo json_encode(['error' => 'User not found']);
    exit;
}

$myRole = (int)$me['role'];
if ($myRole < ROLE_ADMIN && (int)$user['role'] >= ROLE_CENTER_ADMIN) {
    echo json_encode(['error' => 'User not found']);
    exit;
}

$avatarUrl = null;
if (!empty($user['avatar'])) {
    $avatarUrl = '../data/avatars/' . htmlspecialchars($user['avatar']);
}

echo json_encode([
    'id'       => (int)$user['id'],
    'username' => $user['username'],
    'email'    => $user['email'] ?? null,
    'role'     => (int)$user['role'],
    'roleName' => getRoleName((int)$user['role']),
    'avatar'   => $avatarUrl,
    'initial'  => strtoupper(substr($user['username'], 0, 1)),
]);
