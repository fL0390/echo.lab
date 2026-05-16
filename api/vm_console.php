<?php
/**
 * echo — VM Console Ticket API
 * ─────────────────────────────────────────────────────────────────────────────
 * POST { vm_id: N }
 * Vuelve el ticket VNC para la consola
 *
 * Flow:
 *   1. El frontend envía el vm_id.
 *   2. El script se autentica en Proxmox usando el token de API.
 *   3. Proxmox devuelve un ticket + puerto websocket.
 *   4. Devolvemos el ticket al frontend.
 *   5. El frontend conecta noVNC directamente al websocket de Proxmox usando ese ticket.
 */
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/token_auth.php';
require_once __DIR__ . '/../includes/proxmox.php';

header('Content-Type: application/json');


// Acepta token Bearer o cookie de sesión
$me = authenticateApiToken();
if (!$me) {
    if (!empty($_COOKIE[session_name()])) { session_start(); }
    $me = currentUser();
}
if (!$me) { http_response_code(401); echo json_encode(['ok'=>false,'error'=>'Unauthorized']); exit; }

if (!defined('PROXMOX_ENABLED') || !PROXMOX_ENABLED) {
    echo json_encode(['ok'=>false,'error'=>'Proxmox not enabled']);
    exit;
}

$pve = getProxmox();
if (!$pve) { echo json_encode(['ok'=>false,'error'=>'Proxmox not configured']); exit; }

$body  = json_decode(file_get_contents('php://input'), true) ?? [];
$vmId  = (int)($body['vm_id'] ?? 0);
$vm    = $vmId ? DB::getVmById($vmId) : null;

if (!$vm) { echo json_encode(['ok'=>false,'error'=>'VM not found']); exit; }

// Permiso: debe ser el creador, un admin o estar asignado a esta VM
$myId   = (int)$me['id'];
$myRole = (int)$me['role'];
$isOwner    = (int)$vm['created_by'] === $myId;
$assignedStudents = $vm['assigned_students'] ?? [];
if (is_string($assignedStudents)) {
    $decoded = json_decode($assignedStudents, true);
    $assignedStudents = is_array($decoded) ? $decoded : [];
}
$isAssigned = in_array($myId, array_map('intval', $assignedStudents));
$isAssignedClone = !empty($vm['assigned_to']) && (int)$vm['assigned_to'] === $myId;

if (!$isOwner && !$isAssigned && !$isAssignedClone && $myRole < ROLE_ADMIN) {
    echo json_encode(['ok'=>false,'error'=>'Forbidden']); exit;
}

$proxmoxVmid = (int)($vm['proxmox_vmid'] ?? 0);
if (!$proxmoxVmid) {
    echo json_encode(['ok'=>false,'error'=>'This VM has no Proxmox VMID. Register it first.']);
    exit;
}

// Inicia la VM si está parada
if ($vm['status'] !== 'running') {
    $pve->startVM($proxmoxVmid);
    DB::updateVmStatus($vmId, 'running');
    sleep(2); // da un momento para que arranque antes de abrir la consola
}

// Obtiene el ticket VNC
$result = $pve->getVNCTicket($proxmoxVmid);
if (!$result['ok']) {
    echo json_encode(['ok'=>false,'error'=>'Could not get VNC ticket: ' . $result['error']]);
    exit;
}

echo json_encode($result);
