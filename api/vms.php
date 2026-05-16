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
    echo json_encode(['ok'=>false,'error'=>'Unauthorized']);
    exit;
}

$myId       = (int)$user['id'];
$myRole     = (int)$user['role'];
$myCenterId = $user['center_id'] ? (int)$user['center_id'] : null;

$vms = DB::getVms($myId, $myRole, $myCenterId);

$out = array_map(fn($vm) => [
    'id'             => (int)$vm['id'],
    'name'           => $vm['name'],
    'status'         => $vm['status'] ?? 'stopped',
    'os_type'        => $vm['os_type'] ?? '',
    'ram_mb'         => (int)($vm['ram_mb'] ?? 0),
    'cpu_cores'      => (int)($vm['cpu_cores'] ?? 0),
    'disk_gb'        => (int)($vm['disk_gb'] ?? 0),
    'is_template'    => !empty($vm['is_pve_template']),
    'template_id'    => $vm['template_id'] ? (int)$vm['template_id'] : null,
    'assigned_to'    => $vm['assigned_to'] ? (int)$vm['assigned_to'] : null,
    'last_active'    => $vm['last_active'] ?? null,
], $vms);

echo json_encode(['ok'=>true, 'vms'=>$out, 'count'=>count($out)]);
