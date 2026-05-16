<?php
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../includes/logger.php';
require_once __DIR__ . '/../includes/proxmox.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Authorization, Content-Type');
header('Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }


$me = currentUser();
if (!$me) { http_response_code(403); echo json_encode(['ok'=>false,'error'=>'Forbidden']); exit; }

$myRole = (int)$me['role'];
if ($myRole < ROLE_TEACHER) { http_response_code(403); echo json_encode(['ok'=>false,'error'=>'Forbidden']); exit; }

$myId       = (int)$me['id'];
$myCenterId = isset($me['center_id']) ? (int)$me['center_id'] : null;

//GET
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $vmId = (int)($_GET['vm_id'] ?? 0);
    if ($vmId <= 0) { echo json_encode(['ok'=>false,'error'=>'Missing vm_id']); exit; }
    $vm = DB::getVmById($vmId);
    if (!$vm) { echo json_encode(['ok'=>false,'error'=>'VM not found']); exit; }

    if ($myRole >= ROLE_ADMIN)            $users = DB::getAllUsers();
    elseif ($myRole === ROLE_CENTER_ADMIN) $users = DB::getAllUsers(null, $myCenterId);
    else                                   $users = DB::getAllUsers($myId);

    $safeUsers = array_values(array_map(fn($u) => [
        'id'       => (int)$u['id'],
        'username' => $u['username'],
        'role'     => (int)$u['role'],
        'avatar'   => $u['avatar'] ?? '',
    ], $users));

    $clones      = DB::getVmClones($vmId);
    $assignedIds = array_map(fn($c) => (int)$c['assigned_to'], $clones);
    $userMap     = [];
    foreach ($users as $u) $userMap[(int)$u['id']] = $u;
    $richClones  = array_map(function($c) use ($userMap) {
        $uid = (int)$c['assigned_to'];
        $c['username'] = $userMap[$uid]['username'] ?? ('User '.$uid);
        $c['avatar']   = $userMap[$uid]['avatar']   ?? '';
        return $c;
    }, $clones);

    $groups = [];
    if ($myRole >= ROLE_ADMIN) {
        $groups = DB::getCenters();
    } elseif ($myRole === ROLE_CENTER_ADMIN && $myCenterId) {
        $groups = [DB::getCenterById($myCenterId)];
    } else {
        $groups = DB::getUserGroups($myId);
    }

    echo json_encode([
        'ok' => true, 'vm_id' => $vmId, 'vm_name' => $vm['name'],
        'assigned' => $assignedIds, 'users' => $safeUsers, 'clones' => $richClones,
        'has_proxmox' => !empty($vm['proxmox_vmid']),
        'groups' => $groups
    ]);
    exit;
}

// POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $body    = json_decode(file_get_contents('php://input'), true);
    $action  = $body['action'] ?? 'assign_users';
    $vmId    = (int)($body['vm_id']   ?? 0);

    if ($vmId <= 0) { echo json_encode(['ok'=>false,'error'=>'Missing vm_id']); exit; }
    $vm = DB::getVmById($vmId);
    if (!$vm) { echo json_encode(['ok'=>false,'error'=>'VM not found']); exit; }

    if ($action === 'assign_group') {
        $groupId = (int)($body['group_id'] ?? 0);
        if ($groupId <= 0) { echo json_encode(['ok'=>false,'error'=>'Missing group_id']); exit; }
        $memberIds = DB::getGroupMemberIds($groupId);
        if ($myRole >= ROLE_ADMIN) {
        } elseif ($myRole === ROLE_CENTER_ADMIN) {
            $memberIds = array_filter($memberIds, function($uid) use ($myCenterId) {
                $u = DB::findUserById($uid);
                return $u && $u['center_id'] === $myCenterId;
            });
        } else {
            $memberIds = array_filter($memberIds, function($uid) use ($myId) {
                $u = DB::findUserById($uid);
                return $u && ($u['created_by'] === $myId || $u['id'] === $myId);
            });
        }
        $userIds = array_values($memberIds);
    } else {
        $userIds = array_map('intval', $body['user_ids'] ?? []);
    }

    $existing    = DB::getVmClones($vmId);
    $existingMap = [];
    foreach ($existing as $c) $existingMap[(int)$c['assigned_to']] = $c;
    $existingIds = array_keys($existingMap);

    $toAdd    = array_diff($userIds,    $existingIds);
    $toRemove = array_diff($existingIds, $userIds);

    $pve        = getProxmox();
    $pxErrors   = [];
    $pxTemplate = (int)($vm['proxmox_vmid'] ?? 0);

    // Crear clones para nuevos usuarios
    foreach ($toAdd as $uid) {
        $clone = DB::cloneVmForUser($vmId, $uid, $myId);

        if ($pve && $pxTemplate) {
            $user   = DB::findUserById($uid);
            $clName = $vm['name'] . '--' . ($user['username'] ?? 'u'.$uid);
            $useLinked = defined('PROXMOX_LINKED_CLONES') ? PROXMOX_LINKED_CLONES : true;
            $pxResult = $pve->cloneVM($pxTemplate, $clName, PROXMOX_STORAGE, $useLinked);

            if ($pxResult['ok']) {
                if (!empty($pxResult['task'])) {
                    $pve->waitForTask($pxResult['task'], 120);
                }
                DB::updateVmField((int)$clone['id'], 'proxmox_vmid', $pxResult['vmid']);
                DB::updateVmField((int)$clone['id'], 'proxmox_node', PROXMOX_NODE);
            } else {
                $pxErrors[] = "Clone for user {$uid}: " . $pxResult['error'];
            }
        }
    }

    // Borrar clones de usuarios no asignados
    foreach ($toRemove as $uid) {
        $existingClone = $existingMap[$uid] ?? null;
        if ($pve && $existingClone && !empty($existingClone['proxmox_vmid'])) {
            $pve->deleteVM((int)$existingClone['proxmox_vmid']);
        }
        DB::deleteVmCloneForUser($vmId, $uid);
    }

    DB::updateVmAssignedUsers($vmId, $userIds);

    dlog('info', 'VM assigned', [
        'vm_id'   => $vmId,
        'added'   => array_values($toAdd),
        'removed' => array_values($toRemove),
        'by'      => $myId,
        'proxmox' => $pve ? 'enabled' : 'disabled',
    ]);

    $response = ['ok' => true, 'added' => count($toAdd), 'removed' => count($toRemove)];
    if ($pxErrors) $response['proxmox_errors'] = $pxErrors;
    echo json_encode($response);
    exit;
}

http_response_code(405);
echo json_encode(['ok'=>false,'error'=>'Method not allowed']);