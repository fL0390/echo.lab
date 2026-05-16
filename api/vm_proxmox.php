<?php
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../includes/proxmox.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Authorization, Content-Type');
header('Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }


$me = currentUser();
if (!$me || (int)$me['role'] < ROLE_TEACHER) {
    http_response_code(403); echo json_encode(['ok'=>false,'error'=>'Forbidden']); exit;
}

if (!defined('PROXMOX_ENABLED') || !PROXMOX_ENABLED) {
    echo json_encode(['ok'=>false,'error'=>'Proxmox not enabled in config.php']); exit;
}

$pve = getProxmox();
if (!$pve) { echo json_encode(['ok'=>false,'error'=>'Proxmox not configured']); exit; }

$body    = json_decode(file_get_contents('php://input'), true) ?? [];
$action  = trim($body['action'] ?? '');
$myId    = (int)$me['id'];
$myRole  = (int)$me['role'];

switch ($action) {

    case 'ping':
        echo json_encode($pve->ping());
        break;

    // Crea una nueva VM en Proxmox para una plantilla de echo
    case 'create': {
        $vmId = (int)($body['vm_id'] ?? 0);
        $vm   = $vmId ? DB::getVmById($vmId) : null;
        if (!$vm) { echo json_encode(['ok'=>false,'error'=>'VM not found']); break; }
        if ((int)$vm['created_by'] !== $myId && $myRole < ROLE_ADMIN) {
            echo json_encode(['ok'=>false,'error'=>'Forbidden']); break;
        }
        $r = $pve->createVM($vm['name'], (int)$vm['ram_mb'], (int)$vm['cpu_cores'], (int)$vm['disk_gb'], PROXMOX_STORAGE);
        if ($r['ok']) {
            DB::updateVmField($vmId, 'proxmox_vmid', $r['vmid']);
            DB::updateVmField($vmId, 'proxmox_node', PROXMOX_NODE);
        }
        echo json_encode($r);
        break;
    }

    // Convierte una VM de Proxmox a plantilla (obligatorio antes de clones vinculados)
    case 'make_template': {
        $pxVmid  = (int)($body['proxmox_vmid'] ?? 0);
        $echoVmId = (int)($body['echo_vm_id']  ?? 0);
        if (!$pxVmid) { echo json_encode(['ok'=>false,'error'=>'Missing proxmox_vmid']); break; }
        $r = $pve->convertToTemplate($pxVmid);
        if ($r['ok'] && $echoVmId) {
            DB::updateVmField($echoVmId, 'is_pve_template', true);
            DB::updateVmField($echoVmId, 'status', 'stopped');
        }
        echo json_encode($r);
        break;
    }

    //Clonar plantilla para un estudiante
    case 'clone': {
        $vmId   = (int)($body['vm_id']   ?? 0);
        $userId = (int)($body['user_id'] ?? 0);
        $linked = (bool)($body['linked'] ?? true);

        $vm   = $vmId   ? DB::getVmById($vmId)      : null;
        $user = $userId ? DB::findUserById($userId)  : null;
        if (!$vm || !$user) { echo json_encode(['ok'=>false,'error'=>'VM or user not found']); break; }

        $pxTemplate = (int)($vm['proxmox_vmid'] ?? 0);
        if (!$pxTemplate) {
            echo json_encode(['ok'=>false,'error'=>'Template has no Proxmox VMID. Register it first (action: create), then optionally convert to template (action: make_template).']);
            break;
        }

        $cloneName = $vm['name'] . '--' . $user['username'];
        $r = $pve->cloneVM($pxTemplate, $cloneName, PROXMOX_STORAGE, $linked);

        if ($r['ok']) {
            if (!empty($r['task'])) {
                $wait = $pve->waitForTask($r['task'], 180);
                if (!$wait['ok']) { echo json_encode(['ok'=>false,'error'=>'Clone task failed: '.$wait['error']]); break; }
            }
            $clones = DB::getVmClones($vmId);
            foreach ($clones as $clone) {
                if ((int)$clone['assigned_to'] === $userId && empty($clone['proxmox_vmid'])) {
                    DB::updateVmField((int)$clone['id'], 'proxmox_vmid', $r['vmid']);
                    DB::updateVmField((int)$clone['id'], 'proxmox_node', PROXMOX_NODE);
                    break;
                }
            }
            $r['linked'] = $linked;
        }
        echo json_encode($r);
        break;
    }

    case 'delete': {
        $pxVmid = (int)($body['proxmox_vmid'] ?? 0);
        if (!$pxVmid) { echo json_encode(['ok'=>false,'error'=>'Missing proxmox_vmid']); break; }
        $r = $pve->deleteVM($pxVmid);
        if ($r['ok'] && isset($body['echo_vm_id'])) DB::deleteVm((int)$body['echo_vm_id']);
        echo json_encode($r);
        break;
    }

    case 'start': {
        $pxVmid = (int)($body['proxmox_vmid'] ?? 0);
        if (!$pxVmid) { echo json_encode(['ok'=>false,'error'=>'Missing proxmox_vmid']); break; }
        $r = $pve->startVM($pxVmid);
        if ($r['ok'] && isset($body['echo_vm_id'])) DB::updateVmStatus((int)$body['echo_vm_id'], 'running');
        echo json_encode($r);
        break;
    }

    case 'stop': {
        $pxVmid = (int)($body['proxmox_vmid'] ?? 0);
        if (!$pxVmid) { echo json_encode(['ok'=>false,'error'=>'Missing proxmox_vmid']); break; }
        $r = $pve->stopVM($pxVmid);
        if ($r['ok'] && isset($body['echo_vm_id'])) DB::updateVmStatus((int)$body['echo_vm_id'], 'stopped');
        echo json_encode($r);
        break;
    }

    case 'kill': {
        $pxVmid = (int)($body['proxmox_vmid'] ?? 0);
        if (!$pxVmid) { echo json_encode(['ok'=>false,'error'=>'Missing proxmox_vmid']); break; }
        $r = $pve->killVM($pxVmid);
        if ($r['ok'] && isset($body['echo_vm_id'])) DB::updateVmStatus((int)$body['echo_vm_id'], 'stopped');
        echo json_encode($r);
        break;
    }

    case 'status': {
        $pxVmid = (int)($body['proxmox_vmid'] ?? 0);
        if (!$pxVmid) { echo json_encode(['ok'=>false,'error'=>'Missing proxmox_vmid']); break; }
        $r = $pve->getVMStatus($pxVmid);
        if ($r['ok'] && isset($body['echo_vm_id'])) DB::updateVmStatus((int)$body['echo_vm_id'], $r['status']);
        echo json_encode($r);
        break;
    }

    default:
        echo json_encode(['ok'=>false,'error'=>"Unknown action: $action"]);
}
