<?php
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../includes/logger.php';

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

const SHUTDOWN_AGENT_BASE   = 'http://127.0.0.1:9998';
const SHUTDOWN_AGENT_SECRET = 'echo-shutdown-secret';   

$body   = json_decode(file_get_contents('php://input'), true);
$action = $body['action'] ?? '';

if (!in_array($action, ['shutdown', 'shutdown_all'])) {
    echo json_encode(['ok' => false, 'error' => 'Bad request — use action=shutdown or shutdown_all']);
    exit;
}


if ($action === 'shutdown_all') {
    $url     = SHUTDOWN_AGENT_BASE . '/shutdown/all';
    $payload = json_encode([]);
} else {
    $url     = SHUTDOWN_AGENT_BASE . '/shutdown';
    $payload = json_encode(array_filter([
        'node' => $body['node'] ?? null,
        'ip'   => $body['ip']   ?? null,
    ]));
}

dlog('cmd', "Sending shutdown request", ['url' => $url, 'action' => $action]);

$ctx = stream_context_create([
    'http' => [
        'method'        => 'POST',
        'header'        => implode("\r\n", [
            'Content-Type: application/json',
            'X-Shutdown-Secret: ' . SHUTDOWN_AGENT_SECRET,
            'Content-Length: ' . strlen($payload),
        ]),
        'content'       => $payload,
        'timeout'       => 10,
        'ignore_errors' => true,
    ],
]);

$response = @file_get_contents($url, false, $ctx);

if ($response === false) {
    $err = error_get_last();
    dlog('error', "Shutdown agent unreachable", ['url' => $url]);
    echo json_encode([
        'ok'    => false,
        'error' => 'Cannot reach shutdown.py. Make sure it is running: python3 shutdown.py --serve',
        'detail' => $err['message'] ?? '',
    ]);
    exit;
}

dlog('info', "Shutdown agent responded", ['response' => $response]);

$data = json_decode($response, true);
echo json_encode($data ?? ['ok' => false, 'error' => 'Bad response from shutdown agent']);
