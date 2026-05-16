<?php
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/token_auth.php';
require_once __DIR__ . '/../includes/proxmox.php';

header('Content-Type: application/json');


$me = authenticateApiToken();
if (!$me) {
    if (!empty($_COOKIE[session_name()])) { session_start(); }
    $me = currentUser();
}
if (!$me) {
    http_response_code(401); echo json_encode(['ok'=>false,'error'=>'Unauthorized']); exit;
}
$myId   = (int)$me['id'];
$myRole = (int)$me['role'];

// GET: lista de clones
if ($_SERVER['REQUEST_METHOD'] === 'GET') { try {
    $tplId = (int)($_GET['template_id'] ?? 0);
    if (!$tplId) { echo json_encode(['ok'=>false,'error'=>'Missing template_id']); exit; }

    $tpl = DB::getVmById($tplId);
    if (!$tpl) { echo json_encode(['ok'=>false,'error'=>'Not found']); exit; }
    $assignedStudents = $tpl['assigned_students'] ?? [];
    if (is_string($assignedStudents)) {
        $decoded = json_decode($assignedStudents, true);
        $assignedStudents = is_array($decoded) ? $decoded : [];
    }
    $iCreator  = (int)$tpl['created_by'] === $myId;
    $iAdmin    = $myRole >= ROLE_ADMIN;
    $hasClone  = !empty(array_filter(DB::getVmClones($tplId), fn($c) => (int)($c['assigned_to'] ?? 0) === $myId));
    $inStudents= in_array($myId, array_map('intval', $assignedStudents));
    if (!$iCreator && !$iAdmin && !$hasClone && !$inStudents) {
        echo json_encode(['ok'=>false,'error'=>'Forbidden']); exit;
    }

    $clones = DB::getVmClones($tplId);
    $result = array_map(function($c) use ($myId) {
        $assignedTo = isset($c['assigned_to']) ? (int)$c['assigned_to'] : 0;
        $u          = $assignedTo > 0 ? DB::findUserById($assignedTo) : null;
        $uRole      = is_array($u) ? (int)($u['role'] ?? 0) : 0;
        $isMe       = $assignedTo === $myId;
        $roleLabel = match($uRole) {
            ROLE_ADMIN, ROLE_CENTER_ADMIN => 'Admin',
            ROLE_TEACHER                  => 'Teacher',
            ROLE_STUDENT                  => 'Student',
            default                       => 'User',
        };
        return [
            'id'           => (int)($c['id'] ?? 0),
            'name'         => $c['name'] ?? '',
            'status'       => $c['status'] ?? 'stopped',
            'last_active'  => $c['last_active'] ?? null,
            'assigned_to'  => $assignedTo > 0 ? $assignedTo : null,
            'username'     => (is_array($u) && !empty($u['username'])) ? $u['username'] : ($assignedTo > 0 ? 'User '.$assignedTo : 'Sin asignar'),
            'role_label'   => $roleLabel,
            'is_me'        => $isMe,
            'proxmox_vmid' => !empty($c['proxmox_vmid']) ? (int)$c['proxmox_vmid'] : null,
        ];
    }, $clones);

    // Ordenar: poner el clon del usuario actual primero
    usort($result, fn($a,$b) => ($b['is_me'] ? 1 : 0) - ($a['is_me'] ? 1 : 0));

    echo json_encode(['ok'=>true, 'clones'=>$result, 'template_name'=>$tpl['name']]);
    exit;
} catch (\Throwable $e) {
    http_response_code(500);
    error_log('[vm_clones.php] ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
    echo json_encode([
        'ok'    => false,
        'error' => 'Server error: ' . $e->getMessage(),
        'where' => basename($e->getFile()) . ':' . $e->getLine(),
    ]);
    exit;
} }

// POST: matar un clon
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $body    = json_decode(file_get_contents('php://input'), true) ?? [];
    $tplId   = (int)($body['template_id']  ?? 0);
    $cloneId = (int)($body['clone_id']     ?? 0);
    $action  = $body['action'] ?? '';
    $pxVmid  = (int)($body['proxmox_vmid'] ?? 0);

    $tpl = $tplId ? DB::getVmById($tplId) : null;
    if (!$tpl) { echo json_encode(['ok'=>false,'error'=>'Template not found']); exit; }
    $iCreator = (int)$tpl['created_by'] === $myId;
    $iAdmin   = $myRole >= ROLE_ADMIN;
    $hasClone = !empty(array_filter(DB::getVmClones($tplId), fn($c) => (int)$c['assigned_to'] === $myId));
    if (!$iCreator && !$iAdmin && !$hasClone) {
        echo json_encode(['ok'=>false,'error'=>'Forbidden']); exit;
    }

    if ($action === 'kill' && $cloneId) {
        if ($pxVmid && defined('PROXMOX_ENABLED') && PROXMOX_ENABLED) {
            $pve = getProxmox();
            if ($pve) $pve->killVM($pxVmid);
        }
        DB::updateVmStatus($cloneId, 'stopped');
        echo json_encode(['ok'=>true]);
        exit;
    }

    if ($action === 'delete' && $cloneId) {
        $clone = DB::getVmById($cloneId);
        if (!$clone) { echo json_encode(['ok'=>false,'error'=>'Clone not found']); exit; }
        $pxVmidDel = (int)($clone['proxmox_vmid'] ?? 0);
        if ($pxVmidDel && defined('PROXMOX_ENABLED') && PROXMOX_ENABLED) {
            $pve = getProxmox();
            if ($pve) {
                $pve->killVM($pxVmidDel);
                $pve->deleteVM($pxVmidDel);
            }
        }
        // Quitar el clon de la base de datos
        $uid = (int)($clone['assigned_to'] ?? 0);
        DB::deleteVmCloneForUser($tplId, $uid);
        echo json_encode(['ok'=>true]);
        exit;
    }

    echo json_encode(['ok'=>false,'error'=>'Unknown action']);
    exit;
}

http_response_code(405);
echo json_encode(['ok'=>false,'error'=>'Method not allowed']);
