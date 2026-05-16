<?php

define('SKIP_SESSION', true);
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/token_auth.php';
require_once __DIR__ . '/../includes/proxmox.php';

header('Content-Type: application/json');


// Auth — acepta BEARER o session cookie
$me = authenticateApiToken();
if (!$me) {
    if (!empty($_COOKIE[session_name()])) { session_start(); }
    if (!empty($_SESSION['user_id'])) {
        $me = DB::findUserById((int)$_SESSION['user_id']);
    }
}
if (!$me) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Unauthorized']);
    exit;
}

$userId = (int)$me['id'];

// GET: return status
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $vmId = (int)($_GET['vm_id'] ?? 0);
    $vm   = $vmId ? DB::getVmById($vmId) : null;
    if (!$vm) { echo json_encode(['ok'=>false,'error'=>'VM not found']); exit; }

    // Fast path: si Proxmox no está habilitado o la VM no tiene ID de Proxmox, solo devuelve el estado de la DB
    $pxVmid = (int)($vm['proxmox_vmid'] ?? 0);
    if (!defined('PROXMOX_ENABLED') || !PROXMOX_ENABLED || !$pxVmid) {
        echo json_encode(['ok'=>true,'status'=>$vm['status'],'last_active'=>$vm['last_active']??null]);
        exit;
    }

    // Proxmox habilitado y VM tiene un VMID — sincroniza el estado real
    $pve = getProxmox();
    if ($pve) {
        $real = $pve->getVMStatus($pxVmid);
        if ($real['ok'] && $real['status'] !== $vm['status']) {
            DB::updateVmStatus($vmId, $real['status']);
            $vm['status'] = $real['status'];
        }
    }
    echo json_encode(['ok'=>true,'status'=>$vm['status'],'last_active'=>$vm['last_active']??null]);
    exit;
}

// POST: action
$body   = json_decode(file_get_contents('php://input'), true) ?? [];
$vmId   = (int)($body['vm_id']  ?? $_POST['vm_id']  ?? 0);
$action = trim($body['action']  ?? $_POST['action']  ?? '');

$vm = $vmId ? DB::getVmById($vmId) : null;
if (!$vm) { echo json_encode(['ok'=>false,'error'=>'VM not found']); exit; }

$myRole = (int)$me['role'];
$isCreator    = (int)$vm['created_by'] === $userId;
$isAssignedTo = !empty($vm['assigned_to']) && (int)$vm['assigned_to'] === $userId;
$assignedStudents = $vm['assigned_students'] ?? [];
if (is_string($assignedStudents)) {
    $decoded = json_decode($assignedStudents, true);
    $assignedStudents = is_array($decoded) ? $decoded : [];
}
$isInStudents = in_array($userId, array_map('intval', $assignedStudents));
$isAdmin      = $myRole >= ROLE_ADMIN;
if (!$isCreator && !$isAssignedTo && !$isInStudents && !$isAdmin) {
    echo json_encode(['ok'=>false,'error'=>'Forbidden']); exit;
}

switch ($action) {
    case 'start':
        $pve = getProxmox();
        $pxVmid = (int)($vm['proxmox_vmid'] ?? 0);
        if ($pve && $pxVmid) {
            $r = $pve->startVM($pxVmid);
            if (!$r['ok']) { echo json_encode(['ok'=>false,'error'=>$r['error']]); break; }
        }
        DB::updateVmStatus($vmId, 'running');
        echo json_encode(['ok'=>true,'status'=>'running','proxmox'=>$pve && $pxVmid]);
        break;
    case 'stop':
        $pve    = getProxmox();
        $pxVmid = (int)($vm['proxmox_vmid'] ?? 0);
        $pxOk   = false;
        if ($pve && $pxVmid) {
            $stopResult = $pve->killVM($pxVmid);
            if (!empty($stopResult['data']) && is_string($stopResult['data'])) {
                $upid    = $stopResult['data'];
                $node    = defined('PROXMOX_NODE') ? PROXMOX_NODE : 'pve';
                $waited  = 0;
                while ($waited < 12) {
                    sleep(1); $waited++;
                    $taskRes = $pve->getTaskStatus($upid, $node);
                    if (!empty($taskRes['data']['status']) && $taskRes['data']['status'] === 'stopped') break;
                }
            }
            $status = $pve->getVMStatus($pxVmid);
            $pxOk   = $status['ok'] && $status['status'] === 'stopped';
        }
        DB::updateVmStatus($vmId, 'stopped');
        echo json_encode(['ok'=>true,'status'=>'stopped','proxmox'=>$pxOk]);
        break;
    case 'heartbeat':
        DB::touchVmActive($vmId);
        DB::stopInactiveVms(60);
        $pve = getProxmox();
        $pxVmid = (int)($vm['proxmox_vmid'] ?? 0);
        if ($pve && $pxVmid) {
            $pxStatus = $pve->getVMStatus($pxVmid);
            if ($pxStatus['ok']) {
                DB::updateVmStatus($vmId, $pxStatus['status']);
                echo json_encode(['ok'=>true,'status'=>$pxStatus['status'],'source'=>'proxmox']);
                break;
            }
        }
        echo json_encode(['ok'=>true,'status'=>$vm['status']]);
        break;
    default:
        echo json_encode(['ok'=>false,'error'=>'Unknown action']);
}
