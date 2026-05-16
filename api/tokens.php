<?php
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/token_auth.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Authorization, Content-Type');
header('Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }


$me = currentUser();
if (!$me) { http_response_code(401); echo json_encode(['ok'=>false,'error'=>'Unauthorized']); exit; }
$myId = (int)$me['id'];

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    echo json_encode(['ok'=>true, 'tokens'=> getUserApiTokens($myId)]);
    exit;
}

$body = json_decode(file_get_contents('php://input'), true) ?? [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $body['action'] ?? 'create';
    if ($action === 'create') {
        $name  = trim($body['name'] ?? 'Mi Integración');
        $name  = mb_substr($name, 0, 128);
        $token = createApiToken($myId, $name ?: 'Mi Integración');
        $newId = 0;
        if (TESTING_MODE) {
            $file = __DIR__ . '/../data/api_tokens.json';
            $rows = json_decode(file_get_contents($file), true) ?? [];
            foreach ($rows as $r) { if ($r['token'] === $token) { $newId = (int)$r['id']; break; } }
        } else {
            $newId = (int)DB::getConnection()->lastInsertId();
        }
        echo json_encode(['ok'=>true, 'token'=>$token, 'name'=>$name, 'id'=>$newId]);
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    $id = (int)($body['id'] ?? 0);
    if (!$id) { echo json_encode(['ok'=>false,'error'=>'Missing id']); exit; }
    $ok = deleteApiToken($id, $myId);
    echo json_encode(['ok'=>$ok]);
    exit;
}

http_response_code(405);
echo json_encode(['ok'=>false,'error'=>'Method not allowed']);
