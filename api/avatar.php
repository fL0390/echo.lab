<?php
require_once __DIR__ . '/../auth.php';
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Authorization, Content-Type');
header('Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }


if (!isLoggedIn()) { echo json_encode(['error' => 'Unauthorized']); exit; }
$me = currentUser();
if (!$me) { echo json_encode(['error' => 'Unauthorized']); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { echo json_encode(['error' => 'POST only']); exit; }
if (!isset($_FILES['avatar'])) { echo json_encode(['error' => 'No file']); exit; }

$filename = processAvatarUpload($_FILES['avatar'], (int)$me['id'], $me['avatar'] ?? '');
if (!$filename) { echo json_encode(['error' => 'Invalid file.']); exit; }

DB::updateAvatar((int)$me['id'], $filename);
echo json_encode(['ok' => true, 'avatar' => $filename]);
