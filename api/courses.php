<?php
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../includes/proxmox.php';
require_once __DIR__ . '/../includes/logger.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Authorization, Content-Type');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

require_once __DIR__ . '/token_auth.php';
$me = authenticateApiToken();
if (!$me) {
    if (!empty($_COOKIE[session_name()])) { session_start(); }
    $me = currentUser();
}
if (!$me) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Unauthorized. Provide a valid API token.']);
    exit;
}
$myRole = (int)$me['role'];
if ($myRole < ROLE_TEACHER) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Forbidden. Teacher role or above required.']);
    exit;
}
$myId = (int)$me['id'];

$action = $_GET['action'] ?? $_POST['action'] ?? '';
$body   = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $raw  = file_get_contents('php://input');
    $body = $raw ? (json_decode($raw, true) ?? []) : [];
    foreach ($_POST as $k => $v) { if (!isset($body[$k])) $body[$k] = $v; }
    if (!$action) $action = $body['action'] ?? '';
}

function resolveStudent(string|int $identifier): ?array {
    if (is_numeric($identifier)) {
        $u = DB::findUserById((int)$identifier);
    } else {
        $u = DB::findUserByUsername((string)$identifier);
    }
    return $u;
}

function getOwnedTemplate(int $templateId, int $myId, int $myRole): ?array {
    $vm = DB::getVmById($templateId);
    if (!$vm) return null;
    if (!$vm['is_pve_template'] && empty($vm['template_id'])) {
        if (empty($vm['is_pve_template'])) return null;
    }
    if ($myRole < ROLE_ADMIN && (int)$vm['created_by'] !== $myId) return null;
    return $vm;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' && $action === 'list_templates') {
    $allVms = DB::getVms($myId, $myRole, $me['center_id'] ?? null);
    $templates = array_values(array_filter($allVms, fn($v) => !empty($v['is_pve_template'])));
    $out = array_map(fn($t) => [
        'template_id'  => (int)$t['id'],
        'name'         => $t['name'],
        'proxmox_vmid' => $t['proxmox_vmid'] ? (int)$t['proxmox_vmid'] : null,
        'ram_mb'       => (int)$t['ram_mb'],
        'cpu_cores'    => (int)$t['cpu_cores'],
        'disk_gb'      => (int)$t['disk_gb'],
        'created_at'   => $t['created_at'],
        'clone_count'  => count(DB::getVmClones((int)$t['id'])),
    ], $templates);
    echo json_encode(['ok' => true, 'templates' => $out, 'count' => count($out)]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' && $action === 'template_status') {
    $templateId = (int)($_GET['template_id'] ?? 0);
    if (!$templateId) { echo json_encode(['ok'=>false,'error'=>'Missing template_id']); exit; }

    $vm = DB::getVmById($templateId);
    if (!$vm) { echo json_encode(['ok'=>false,'error'=>'Template not found']); exit; }
    if ($myRole < ROLE_ADMIN && (int)$vm['created_by'] !== $myId) {
        echo json_encode(['ok'=>false,'error'=>'Not your template']); exit;
    }

    $clones = DB::getVmClones($templateId);
    $cloneOut = array_map(function($c) {
        $u = DB::findUserById((int)$c['assigned_to']);
        return [
            'clone_id'     => (int)$c['id'],
            'username'     => $u['username'] ?? null,
            'user_id'      => (int)$c['assigned_to'],
            'proxmox_vmid' => $c['proxmox_vmid'] ? (int)$c['proxmox_vmid'] : null,
            'status'       => $c['status'] ?? 'stopped',
            'last_active'  => $c['last_active'] ?? null,
        ];
    }, $clones);

    echo json_encode([
        'ok'           => true,
        'template_id'  => $templateId,
        'name'         => $vm['name'],
        'proxmox_vmid' => $vm['proxmox_vmid'] ? (int)$vm['proxmox_vmid'] : null,
        'is_template'  => !empty($vm['is_pve_template']),
        'ram_mb'       => (int)$vm['ram_mb'],
        'cpu_cores'    => (int)$vm['cpu_cores'],
        'disk_gb'      => (int)$vm['disk_gb'],
        'clone_count'  => count($clones),
        'clones'       => $cloneOut,
    ]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'create_template') {
    $name      = trim($body['name'] ?? '');
    $isoVolid  = trim($body['iso_volid'] ?? '');
    $ram       = max(512,  min((int)($body['ram_mb']    ?? 2048),  65536));
    $cpu       = max(1,    min((int)($body['cpu_cores'] ?? 2),     32));
    $disk      = max(5,    min((int)($body['disk_gb']   ?? 20),    2000));

    if (!$name)     { echo json_encode(['ok'=>false,'error'=>'name is required']); exit; }
    if (!$isoVolid) { echo json_encode(['ok'=>false,'error'=>'iso_volid is required (e.g. "local:iso/ubuntu-24.04.iso")']); exit; }

    if (!defined('PROXMOX_ENABLED') || !PROXMOX_ENABLED) {
        echo json_encode(['ok'=>false,'error'=>'Proxmox is not enabled on this echo instance']); exit;
    }
    $pve = getProxmox();
    if (!$pve) { echo json_encode(['ok'=>false,'error'=>'Proxmox connection failed']); exit; }

    $vmid = $pve->nextVmid();
    if (!$vmid) { echo json_encode(['ok'=>false,'error'=>'Could not get next Proxmox VMID']); exit; }

    $safeName = preg_replace('/[^a-zA-Z0-9\-]/', '-', $name);
    $createResult = $pve->post("/nodes/" . PROXMOX_NODE . "/qemu", [
        'vmid'    => $vmid,
        'name'    => $safeName,
        'memory'  => $ram,
        'cores'   => $cpu,
        'sockets' => 1,
        'cpu'     => 'host',
        'kvm'     => 1,
        'net0'    => 'virtio,bridge=vmbr0',
        'scsihw'  => 'virtio-scsi-pci',
        'scsi0'   => PROXMOX_STORAGE . ":$disk",
        'ide2'    => "$isoVolid,media=cdrom",
        'boot'    => 'order=ide2;scsi0',
        'ostype'  => 'l26',
        'agent'   => 1,
    ]);
    if (!$createResult['ok']) {
        echo json_encode(['ok'=>false,'error'=>'Proxmox VM creation failed: ' . ($createResult['error'] ?? 'unknown')]);
        exit;
    }

    if (!empty($createResult['data'])) {
        $wait = $pve->waitForTask($createResult['data'], 120);
        if (!$wait['ok']) {
            echo json_encode(['ok'=>false,'error'=>'VM creation task failed: ' . $wait['error']]);
            exit;
        }
    }

    $tplResult = $pve->convertToTemplate($vmid);
    if (!$tplResult['ok']) {
        echo json_encode(['ok'=>false,'error'=>'Template conversion failed: ' . ($tplResult['error'] ?? 'unknown')]);
        exit;
    }
    if (!empty($tplResult['data'])) {
        $pve->waitForTask($tplResult['data'], 60);
    }

    $echoVm = DB::createVm([
        'name'             => $name,
        'iso_id'           => 0,
        'iso_name'         => basename($isoVolid),
        'os_type'          => basename($isoVolid),
        'ram_mb'           => $ram,
        'cpu_cores'        => $cpu,
        'disk_gb'          => $disk,
        'assign_scope'     => 'personal',
        'assign_center_id' => null,
        'assigned_students'=> [],
        'created_by'       => $myId,
        'node_key'         => null,
        'proxmox_vmid'     => $vmid,
        'proxmox_node'     => PROXMOX_NODE,
        'template_id'      => null,
        'is_pve_template'  => 1,
        'status'           => 'stopped',
    ]);

    dlog('info', 'API: template created', [
        'template_id'  => $echoVm['id'],
        'proxmox_vmid' => $vmid,
        'by'           => $myId,
    ]);

    echo json_encode([
        'ok'           => true,
        'template_id'  => (int)$echoVm['id'],
        'proxmox_vmid' => $vmid,
        'name'         => $name,
        'message'      => 'Template created. Use template_id to assign students.',
    ]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'assign_student') {
    $templateId = (int)($body['template_id'] ?? 0);
    $identifier = $body['username'] ?? $body['user_id'] ?? null;

    if (!$templateId) { echo json_encode(['ok'=>false,'error'=>'template_id is required']); exit; }
    if ($identifier === null) { echo json_encode(['ok'=>false,'error'=>'username or user_id is required']); exit; }

    $template = DB::getVmById($templateId);
    if (!$template || empty($template['is_pve_template'])) {
        echo json_encode(['ok'=>false,'error'=>'Template not found or not a PVE template']);
        exit;
    }
    if ($myRole < ROLE_ADMIN && (int)$template['created_by'] !== $myId) {
        echo json_encode(['ok'=>false,'error'=>'Not your template']); exit;
    }

    $student = resolveStudent($identifier);
    if (!$student) {
        echo json_encode(['ok'=>false,'error'=>"User not found: $identifier"]); exit;
    }
    $studentId = (int)$student['id'];

    $existing = DB::getVmClones($templateId);
    foreach ($existing as $c) {
        if ((int)$c['assigned_to'] === $studentId) {
            echo json_encode(['ok'=>false,'error'=>'Student already has a clone of this template',
                'clone_id' => (int)$c['id'], 'proxmox_vmid' => $c['proxmox_vmid'] ? (int)$c['proxmox_vmid'] : null]);
            exit;
        }
    }

    $clone = DB::cloneVmForUser($templateId, $studentId, $myId);
    if (!$clone || empty($clone['id'])) {
        echo json_encode(['ok'=>false,'error'=>'Failed to create clone record in database']); exit;
    }
    $cloneId = (int)$clone['id'];

    $pveVmid = null;
    $pveError = null;
    $pve = defined('PROXMOX_ENABLED') && PROXMOX_ENABLED ? getProxmox() : null;
    $pxTemplate = (int)($template['proxmox_vmid'] ?? 0);

    if ($pve && $pxTemplate) {
        $cloneName  = $template['name'] . '--' . $student['username'];
        $useLinked  = defined('PROXMOX_LINKED_CLONES') ? PROXMOX_LINKED_CLONES : true;
        $pxResult   = $pve->cloneVM($pxTemplate, $cloneName, PROXMOX_STORAGE, $useLinked);

        if ($pxResult['ok']) {
            if (!empty($pxResult['task'])) {
                $pve->waitForTask($pxResult['task'], 120);
            }
            $pveVmid = $pxResult['vmid'];
            DB::updateVmField($cloneId, 'proxmox_vmid', $pveVmid);
            DB::updateVmField($cloneId, 'proxmox_node', PROXMOX_NODE);
        } else {
            $pveError = $pxResult['error'];
        }
    }

    dlog('info', 'API: student assigned', [
        'template_id' => $templateId,
        'student_id'  => $studentId,
        'clone_id'    => $cloneId,
        'proxmox_vmid'=> $pveVmid,
        'by'          => $myId,
    ]);

    $response = [
        'ok'           => true,
        'clone_id'     => $cloneId,
        'username'     => $student['username'],
        'user_id'      => $studentId,
        'proxmox_vmid' => $pveVmid,
        'status'       => 'stopped',
    ];
    if ($pveError) $response['proxmox_warning'] = $pveError;
    echo json_encode($response);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'assign_course') {
    $templateId = (int)($body['template_id'] ?? 0);
    $students   = $body['students'] ?? [];

    if (!$templateId) { echo json_encode(['ok'=>false,'error'=>'template_id is required']); exit; }
    if (!is_array($students) || empty($students)) {
        echo json_encode(['ok'=>false,'error'=>'students must be a non-empty array of usernames or user_ids']); exit;
    }

    $template = DB::getVmById($templateId);
    if (!$template || empty($template['is_pve_template'])) {
        echo json_encode(['ok'=>false,'error'=>'Template not found or not a PVE template']); exit;
    }
    if ($myRole < ROLE_ADMIN && (int)$template['created_by'] !== $myId) {
        echo json_encode(['ok'=>false,'error'=>'Not your template']); exit;
    }

    $pve       = defined('PROXMOX_ENABLED') && PROXMOX_ENABLED ? getProxmox() : null;
    $pxTemplate = (int)($template['proxmox_vmid'] ?? 0);
    $useLinked  = defined('PROXMOX_LINKED_CLONES') ? PROXMOX_LINKED_CLONES : true;

    $existing   = DB::getVmClones($templateId);
    $existingMap = [];
    foreach ($existing as $c) $existingMap[(int)$c['assigned_to']] = $c;

    $results = [];
    foreach ($students as $identifier) {
        $student = resolveStudent($identifier);
        if (!$student) {
            $results[] = ['identifier'=>$identifier,'ok'=>false,'error'=>'User not found'];
            continue;
        }
        $studentId = (int)$student['id'];

        if (isset($existingMap[$studentId])) {
            $results[] = [
                'username' => $student['username'],
                'user_id'  => $studentId,
                'ok'       => false,
                'error'    => 'Already assigned',
                'clone_id' => (int)$existingMap[$studentId]['id'],
            ];
            continue;
        }

        $clone   = DB::cloneVmForUser($templateId, $studentId, $myId);
        $cloneId = (int)($clone['id'] ?? 0);
        $pveVmid = null;
        $pveErr  = null;

        if ($pve && $pxTemplate && $cloneId) {
            $cloneName = $template['name'] . '--' . $student['username'];
            $pxResult  = $pve->cloneVM($pxTemplate, $cloneName, PROXMOX_STORAGE, $useLinked);
            if ($pxResult['ok']) {
                if (!empty($pxResult['task'])) $pve->waitForTask($pxResult['task'], 120);
                $pveVmid = $pxResult['vmid'];
                DB::updateVmField($cloneId, 'proxmox_vmid', $pveVmid);
                DB::updateVmField($cloneId, 'proxmox_node', PROXMOX_NODE);
            } else {
                $pveErr = $pxResult['error'];
            }
        }

        $r = ['username'=>$student['username'],'user_id'=>$studentId,'ok'=>true,
              'clone_id'=>$cloneId,'proxmox_vmid'=>$pveVmid];
        if ($pveErr) $r['proxmox_warning'] = $pveErr;
        $results[] = $r;
    }

    $succeeded = count(array_filter($results, fn($r) => $r['ok']));
    dlog('info', 'API: batch assign', ['template_id'=>$templateId,'requested'=>count($students),'ok'=>$succeeded,'by'=>$myId]);

    echo json_encode([
        'ok'        => true,
        'requested' => count($students),
        'assigned'  => $succeeded,
        'failed'    => count($students) - $succeeded,
        'results'   => $results,
    ]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'remove_student') {
    $templateId = (int)($body['template_id'] ?? 0);
    $identifier = $body['username'] ?? $body['user_id'] ?? null;

    if (!$templateId) { echo json_encode(['ok'=>false,'error'=>'template_id is required']); exit; }
    if ($identifier === null) { echo json_encode(['ok'=>false,'error'=>'username or user_id is required']); exit; }

    $template = DB::getVmById($templateId);
    if (!$template) { echo json_encode(['ok'=>false,'error'=>'Template not found']); exit; }
    if ($myRole < ROLE_ADMIN && (int)$template['created_by'] !== $myId) {
        echo json_encode(['ok'=>false,'error'=>'Not your template']); exit;
    }

    $student = resolveStudent($identifier);
    if (!$student) { echo json_encode(['ok'=>false,'error'=>"User not found: $identifier"]); exit; }
    $studentId = (int)$student['id'];

    $clones = DB::getVmClones($templateId);
    $clone  = null;
    foreach ($clones as $c) {
        if ((int)$c['assigned_to'] === $studentId) { $clone = $c; break; }
    }
    if (!$clone) {
        echo json_encode(['ok'=>false,'error'=>'No clone found for this student on this template']); exit;
    }

    $pveDeleted = false;
    $pve = defined('PROXMOX_ENABLED') && PROXMOX_ENABLED ? getProxmox() : null;
    if ($pve && !empty($clone['proxmox_vmid'])) {
        $pve->post("/nodes/" . PROXMOX_NODE . "/qemu/" . $clone['proxmox_vmid'] . "/status/stop", []);
        sleep(2);
        $del = $pve->deleteVM((int)$clone['proxmox_vmid']);
        $pveDeleted = $del['ok'];
    }

    DB::deleteVmCloneForUser($templateId, $studentId);

    dlog('info', 'API: student removed', ['template_id'=>$templateId,'student_id'=>$studentId,'by'=>$myId]);

    echo json_encode([
        'ok'           => true,
        'username'     => $student['username'],
        'user_id'      => $studentId,
        'clone_id'     => (int)$clone['id'],
        'proxmox_deleted' => $pveDeleted,
    ]);
    exit;
}

http_response_code(400);
echo json_encode([
    'ok'    => false,
    'error' => 'Unknown action. Valid actions: create_template, assign_student, assign_course, remove_student, template_status, list_templates',
]);
