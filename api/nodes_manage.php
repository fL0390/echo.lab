<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../includes/logger.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Authorization, Content-Type');
header('Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }


$nodesPath = __DIR__ . '/../data/nodes.json';
if (!is_dir(dirname($nodesPath))) mkdir(dirname($nodesPath), 0755, true);

function loadNodes(): array {
    global $nodesPath;
    if (!file_exists($nodesPath)) return [];
    $d = json_decode(file_get_contents($nodesPath), true);
    return is_array($d) ? $d : [];
}

function saveNodes(array $nodes): void {
    global $nodesPath;
    file_put_contents($nodesPath, json_encode($nodes, JSON_PRETTY_PRINT));
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (!isLoggedIn() || (int)currentUser()['role'] < ROLE_ADMIN) {
        http_response_code(403);
        echo json_encode(['error' => 'Forbidden']);
        exit;
    }
    echo json_encode(['ok' => true, 'nodes' => loadNodes()]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON']);
    exit;
}

$action = $input['action'] ?? '';
$nodes  = loadNodes();

if ($action === 'register') {
    $inSecret = $input['secret'] ?? '';
    if ($inSecret !== NODE_API_SECRET) {
        http_response_code(403);
        echo json_encode(['error' => 'Invalid secret', 'hint' => 'Check NODE_API_SECRET in config.php matches --secret on the agent.']);
        exit;
    }
    $ip   = trim($input['ip'] ?? '');
    $port = (int)($input['port'] ?? 5150);
    if ($ip === '') {
        http_response_code(400);
        echo json_encode(['error' => 'Missing ip']);
        exit;
    }
    $key = $ip . ':' . $port;
    $isNew = !isset($nodes[$key]);
    $nodes[$key] = [
        'ip'        => $ip,
        'port'      => $port,
        'hostname'  => $input['hostname'] ?? $ip,
        'source'    => 'auto',
        'added_at'  => $nodes[$key]['added_at'] ?? date('Y-m-d H:i:s'),
        'last_seen' => date('Y-m-d H:i:s'),
    ];
    saveNodes($nodes);
    if ($isNew) {
        dlog('info', "Agent registered", ['key' => $key, 'hostname' => $input['hostname'] ?? $ip]);
    }
    echo json_encode(['ok' => true, 'key' => $key]);
    exit;
}

if (!isLoggedIn() || (int)currentUser()['role'] < ROLE_ADMIN) {
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden']);
    exit;
}

if ($action === 'add') {
    $ip   = trim($input['ip'] ?? '');
    $port = (int)($input['port'] ?? 5150);
    if ($ip === '' || !filter_var($ip, FILTER_VALIDATE_IP)) {
        echo json_encode(['error' => 'Invalid IP address']);
        exit;
    }
    $key = $ip . ':' . $port;
    $nodes[$key] = [
        'ip'        => $ip,
        'port'      => $port,
        'hostname'  => $ip,
        'source'    => 'manual',
        'added_at'  => date('Y-m-d H:i:s'),
        'last_seen' => null,
    ];
    saveNodes($nodes);
    echo json_encode(['ok' => true, 'key' => $key]);

} elseif ($action === 'remove') {
    $key = $input['key'] ?? '';
    if (isset($nodes[$key])) unset($nodes[$key]);
    saveNodes($nodes);
    echo json_encode(['ok' => true]);

} else {
    echo json_encode(['error' => 'Unknown action']);
}
