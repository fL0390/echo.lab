<?php
require_once __DIR__ . '/../auth.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Authorization, Content-Type');
header('Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }


$me = currentUser();
if (!$me || !can('manage_nodes')) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Forbidden']);
    exit;
}

const WOL_AGENT_URL    = 'http://127.0.0.1:9999/wol';
const WOL_AGENT_SECRET = 'echo-wol-secret';

$body    = json_decode(file_get_contents('php://input'), true);
$action  = $body['action'] ?? '';
$nodeKey = $body['key']    ?? '';

if ($action !== 'wol' || $nodeKey === '') {
    echo json_encode(['ok' => false, 'error' => 'Bad request']);
    exit;
}

$payload = json_encode(['key' => $nodeKey]);
$ctx = stream_context_create([
    'http' => [
        'method'        => 'POST',
        'header'        => implode("\r\n", [
            'Content-Type: application/json',
            'X-WoL-Secret: ' . WOL_AGENT_SECRET,
            'Content-Length: ' . strlen($payload),
        ]),
        'content'       => $payload,
        'timeout'       => 4,
        'ignore_errors' => true,
    ],
]);

$response = @file_get_contents(WOL_AGENT_URL, false, $ctx);

if ($response === false) {
    echo json_encode([
        'ok'    => false,
        'error' => 'Cannot reach wol_server.py. Make sure it is running. '
                 . 'Start it with: python3 wol_server.py',
    ]);
    exit;
}

$data = json_decode($response, true);
echo json_encode($data ?? ['ok' => false, 'error' => 'Bad response from WoL agent']);
