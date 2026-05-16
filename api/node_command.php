<?php
// Suppress warnings de PHP
error_reporting(0);
ini_set('display_errors', '0');
ob_start();

require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../includes/logger.php';


ob_end_clean();

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Authorization, Content-Type');
header('Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }


if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

if (!isLoggedIn() || (int)currentUser()['role'] < ROLE_ADMIN) {
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$ip      = trim($input['ip'] ?? '');
$port    = (int)($input['port'] ?? 5150);
$command = $input['command'] ?? '';
$pid     = (int)($input['pid'] ?? 0);

$allowed = ['shutdown', 'reboot', 'kill'];
if (!in_array($command, $allowed) || $ip === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid command or ip']);
    exit;
}

$url     = "http://{$ip}:{$port}/command";
$payloadData = ['command' => $command];
if ($command === 'kill' && $pid > 0) $payloadData['pid'] = $pid;
$payload = json_encode($payloadData);

dlog('cmd', "Sending '{$command}' to agent", ['url' => $url, 'ip' => $ip, 'port' => $port]);

$context = stream_context_create([
    'http' => [
        'method'  => 'POST',
        'header'  => "Content-Type: application/json\r\nX-Node-Secret: " . NODE_API_SECRET . "\r\n",
        'content' => $payload,
        'timeout' => 5,
        'ignore_errors' => true,
    ],
]);

$response = @file_get_contents($url, false, $context);

if ($response === false) {
    $err = error_get_last();
    dlog('error', "Agent unreachable", ['url' => $url, 'error' => $err['message'] ?? 'unknown']);
    http_response_code(502);
    echo json_encode(['error' => "Cannot reach agent at {$ip}:{$port}", 'detail' => $err['message'] ?? '']);
    exit;
}

dlog('info', "Agent responded", ['url' => $url, 'response' => $response]);

$statusCode = 200;
if (isset($http_response_header)) {
    foreach ($http_response_header as $h) {
        if (preg_match('/^HTTP\/\S+\s+(\d+)/', $h, $m)) {
            $statusCode = (int)$m[1];
        }
    }
}

http_response_code($statusCode);
echo $response;
