<?php
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../includes/proxmox.php';

header('Content-Type: text/plain; charset=utf-8');
header('X-Accel-Buffering: no');
header('Cache-Control: no-cache');
while (ob_get_level() > 0) ob_end_flush();

$me = currentUser();
if (!$me || (int)$me['role'] < ROLE_TEACHER) {
    echo "[ERROR] Unauthorized\n__RESULT__:" . json_encode(['ok'=>false,'error'=>'Unauthorized']);
    exit;
}

function log_line(string $msg): void {
    echo '[' . date('H:i:s') . '] ' . $msg . "\n";
    flush();
}

if (!defined('PROXMOX_ENABLED') || !PROXMOX_ENABLED) {
    echo "__RESULT__:" . json_encode(['ok'=>false,'error'=>'Proxmox not enabled']);
    exit;
}

$pve = getProxmox();
if (!$pve) {
    echo "__RESULT__:" . json_encode(['ok'=>false,'error'=>'Proxmox not configured']);
    exit;
}

$vmName      = preg_replace('/[^a-zA-Z0-9\-]/', '-', trim($_POST['vm_name'] ?? ''));
$isoVolid    = trim($_POST['iso_volid'] ?? '');
$ram         = max(512,  min((int)($_POST['ram_mb']    ?? 2048),  65536));
$cpuCores    = max(1,    min((int)($_POST['cpu_cores'] ?? 2),     32));
$diskGb      = max(5,    min((int)($_POST['disk_gb']   ?? 20),    2000));
$assignScope = $_POST['assign_scope']  ?? 'personal';
$assignCenter = (int)($_POST['assign_center'] ?? 0) ?: null;
$myId        = (int)$me['id'];
$myCenterId  = $me['center_id'] ?? null;

if (!$vmName)   { echo "__RESULT__:" . json_encode(['ok'=>false,'error'=>'VM name required']); exit; }
if (!$isoVolid) { echo "__RESULT__:" . json_encode(['ok'=>false,'error'=>'ISO required']); exit; }

log_line("Creating VM \"$vmName\" in Proxmox...");
log_line("  RAM: {$ram}MB  |  CPU: {$cpuCores} cores  |  Disk: {$diskGb}GB");
log_line("  ISO: " . basename($isoVolid));

// Consigue el próximo VMID disponible
log_line("Fetching next available VMID...");
$vmid = $pve->nextVmid();
if (!$vmid) { echo "__RESULT__:" . json_encode(['ok'=>false,'error'=>'Could not get next VMID']); exit; }
log_line("Next VMID: $vmid");

// Creando la VM
log_line("Building VM configuration...");
$createResult = (function() use ($pve, $vmid, $vmName, $ram, $cpuCores, $diskGb, $isoVolid) {
    $node    = PROXMOX_NODE;
    $storage = PROXMOX_STORAGE;
    $ch = curl_init("https://" . PROXMOX_HOST . ":" . PROXMOX_PORT . "/api2/json/nodes/{$node}/qemu");
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_HTTPHEADER     => ["Authorization: PVEAPIToken=" . PROXMOX_TOKEN_ID . "=" . PROXMOX_TOKEN_SECRET],
        CURLOPT_POSTFIELDS     => http_build_query([
            'vmid'    => $vmid,
            'name'    => $vmName,
            'memory'  => $ram,
            'cores'   => $cpuCores,
            'sockets' => 1,
            'cpu'     => 'host',
            'kvm'     => 1,
            'net0'    => 'virtio,bridge=vmbr0',
            'scsihw'  => 'virtio-scsi-pci',
            'scsi0'   => "{$storage}:{$diskGb}",
            'ide2'    => "{$isoVolid},media=cdrom",
            'boot'    => 'order=ide2;scsi0',
            'ostype'  => 'l26',
            'agent'   => 1,
        ]),
    ]);
    $raw = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $body = json_decode($raw, true);
    if ($code < 200 || $code >= 300) {
        $err = $body['errors'] ?? ($body['message'] ?? "HTTP $code");
        if (is_array($err)) $err = implode('; ', array_map(fn($k,$v)=>"$k: $v", array_keys($err), $err));
        return ['ok'=>false,'error'=>(string)$err];
    }
    return ['ok'=>true,'task'=>$body['data']??null];
})();

if (!$createResult['ok']) {
    log_line("ERROR: " . $createResult['error']);
    echo "__RESULT__:" . json_encode(['ok'=>false,'error'=>$createResult['error']]);
    exit;
}

log_line("VM creation task started...");

// Espera a que se cree la VM
if (!empty($createResult['task'])) {
    log_line("Waiting for VM to be built (this can take 30-60 seconds)...");
    $wait = $pve->waitForTask($createResult['task'], 120);
    if (!$wait['ok']) {
        log_line("ERROR: " . $wait['error']);
        echo "__RESULT__:" . json_encode(['ok'=>false,'error'=>$wait['error']]);
        exit;
    }
    log_line("VM built successfully.");
}

// Enciende la VM
log_line("Sending boot command...");
$startResult = $pve->startVM($vmid);
if (!$startResult['ok']) {
    log_line("WARNING: Could not start VM: " . $startResult['error']);
} else {
    if (!empty($startResult['data'])) {
        log_line("Waiting for VM to boot...");
        $pve->waitForTask($startResult['data'], 60);
    }
    log_line("VM is running.");
}

// Obtiene el ticket VNC
log_line("Requesting VNC console ticket...");
$vnc = $pve->getVNCTicket($vmid);
if (!$vnc['ok']) {
    log_line("WARNING: VNC ticket failed (" . $vnc['error'] . "). VM created but console unavailable.");
}

//Guarda en la DB de echo 
log_line("Saving to echo database...");
$echoVm = DB::createVm([
    'name'             => $vmName,
    'iso_id'           => 0,
    'iso_name'         => basename($isoVolid),
    'os_type'          => basename($isoVolid),
    'ram_mb'           => $ram,
    'cpu_cores'        => $cpuCores,
    'disk_gb'          => $diskGb,
    'assign_scope'     => $assignScope,
    'assign_center_id' => $assignScope === 'center' ? ($assignCenter ?: $myCenterId) : null,
    'assigned_students'=> [],
    'created_by'       => $myId,
    'node_key'         => null,
    'proxmox_vmid'     => $vmid,
    'proxmox_node'     => PROXMOX_NODE,
    'status'           => 'running',
]);

$proxyHost = defined('PROXMOX_PROXY_HOST') ? PROXMOX_PROXY_HOST : 'localhost';
$proxyPort = defined('PROXMOX_PROXY_PORT') ? PROXMOX_PROXY_PORT : 8081;
$autoTemplate = !empty($_POST['auto_template']);

// Convierte a plantilla si se solicita
if ($autoTemplate) {
    log_line("Converting VM to template (for linked clones)...");
    $ch = curl_init("https://" . PROXMOX_HOST . ":" . PROXMOX_PORT . "/api2/json/nodes/" . PROXMOX_NODE . "/qemu/{$vmid}/template");
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true, CURLOPT_POSTFIELDS => '',
        CURLOPT_TIMEOUT => 30, CURLOPT_SSL_VERIFYPEER => false, CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_HTTPHEADER => ["Authorization: PVEAPIToken=" . PROXMOX_TOKEN_ID . "=" . PROXMOX_TOKEN_SECRET],
    ]);
    $raw = curl_exec($ch); $code = curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
    if ($code >= 200 && $code < 300) {
        log_line("Template conversion done. Students will get linked clones.");
        // Actualiza la DB de echo para marcarla como no directamente iniciable
        DB::updateVmField((int)$echoVm['id'], 'status', 'stopped');
    } else {
        log_line("WARNING: Template conversion failed (HTTP $code). VM still usable as full-clone source.");
    }
}

log_line("Done! VMID: $vmid — opening console...");

echo "__RESULT__:" . json_encode([
    'ok'         => true,
    'vmid'       => $vmid,
    'echo_vm_id' => $echoVm['id'] ?? 0,
    'node'       => PROXMOX_NODE,
    'ticket'     => $vnc['ticket'] ?? '',
    'port'       => $vnc['port']   ?? 5900,
    'proxy_host' => $proxyHost,
    'proxy_port' => $proxyPort,
    'vm_name'    => $vmName,
]);
