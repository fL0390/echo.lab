<?php

class ProxmoxAPI {

    private string $host;
    private int    $port;
    private string $node;
    private string $tokenId;
    private string $secret;

    public function __construct(string $host, string $node, string $tokenId, string $secret, int $port = 8006) {
        $this->host    = rtrim($host, '/');
        $this->node    = $node;
        $this->tokenId = $tokenId;
        $this->secret  = $secret;
        $this->port    = $port;
    }

    private function request(string $method, string $path, array $data = []): array {
        $url = "https://{$this->host}:{$this->port}/api2/json{$path}";
        $ch  = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 5,
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_HTTPHEADER     => [
                "Authorization: PVEAPIToken={$this->tokenId}={$this->secret}",
                "Content-Type: application/x-www-form-urlencoded",
            ],
        ]);
        if ($method === 'POST')   { curl_setopt($ch, CURLOPT_POST, true); curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data)); }
        elseif ($method === 'DELETE') { curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE'); }

        $raw  = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $cerr = curl_error($ch);
        @curl_close($ch);

        if ($raw === false) return ['ok'=>false, 'error'=>"cURL: $cerr", 'data'=>null];
        $body = json_decode($raw, true);
        if ($code < 200 || $code >= 300) {
            $msg = $body['errors'] ?? ($body['message'] ?? "HTTP $code");
            if (is_array($msg)) $msg = implode('; ', array_map(fn($k,$v)=>"$k: $v", array_keys($msg), $msg));
            return ['ok'=>false, 'error'=>(string)$msg, 'data'=>$body];
        }
        return ['ok'=>true, 'error'=>'', 'data'=>$body['data'] ?? null];
    }
    /**
 * Sube un archivo ISO al almacenamiento de Proxmox.
 *
 * @param string $localFilePath Ruta completa del archivo ISO en el servidor PHP
 * @param string $storage       Nombre del almacenamiento de Proxmox (ej. "local")
 * @param string $remoteName    Nombre del archivo en Proxmox (ej. "ubuntu-24.04.iso")
 * @return array ['ok'=>bool, 'error'=>string]
 */
public function uploadISO(string $localFilePath, string $storage, string $remoteName): array {
    if (!file_exists($localFilePath)) {
        return ['ok' => false, 'error' => 'Local file not found: ' . $localFilePath];
    }

    $url = "https://{$this->host}:{$this->port}/api2/json/nodes/{$this->node}/storage/{$storage}/upload";
    $postData = [
        'content'   => 'iso',
        'filename'  => $remoteName,
    ];

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_TIMEOUT        => 0,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_HTTPHEADER     => [
            "Authorization: PVEAPIToken={$this->tokenId}={$this->secret}",
        ],
        CURLOPT_POSTFIELDS     => [
            'content'  => 'iso',
            'filename' => new CURLFile($localFilePath, 'application/octet-stream', $remoteName),
        ],
    ]);

    $raw  = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);

    if ($raw === false) {
        return ['ok' => false, 'error' => "cURL error: $err"];
    }

    $body = json_decode($raw, true);
    if ($code < 200 || $code >= 300) {
        $msg = $body['errors'] ?? ($body['message'] ?? "HTTP $code");
        if (is_array($msg)) $msg = implode('; ', array_map(fn($k,$v) => "$k: $v", array_keys($msg), $msg));
        return ['ok' => false, 'error' => (string)$msg];
    }

    return ['ok' => true, 'data' => $body['data'] ?? null];
}

    private function get(string $p): array             { return $this->request('GET',    $p); }
    public  function post(string $p, array $d): array  { return $this->request('POST',   $p, $d); }
    private function del(string $p): array             { return $this->request('DELETE', $p); }

    // Diagnostics

    public function ping(): array {
        $r = $this->get('/nodes/' . $this->node . '/status');
        return $r['ok'] ? ['ok'=>true, 'node'=>$this->node, 'data'=>$r['data']] : $r;
    }

    public function nextVmid(): ?int {
        $r = $this->get('/cluster/nextid');
        return $r['ok'] ? (int)$r['data'] : null;
    }

    // VM creation (VM nueva - usada cuando el profesor crea una plantilla por primera vez)

    public function createVM(string $name, int $ramMb, int $cpuCores, int $diskGb, string $storage = 'local-lvm', ?int $vmid = null): array {
        $vmid = $vmid ?? $this->nextVmid();
        if (!$vmid) return ['ok'=>false, 'error'=>'No se pudo obtener el siguiente VMID'];

        $r = $this->post('/nodes/' . $this->node . '/qemu', [
            'vmid'    => $vmid,
            'name'    => $this->sanitizeName($name),
            'memory'  => $ramMb,
            'cores'   => $cpuCores,
            'sockets' => 1,
            'cpu'     => 'host',
            'kvm'     => 1,
            'net0'    => 'virtio,bridge=vmbr0',
            'ostype'  => 'l26',
            'agent'   => 1,
            'boot'    => 'order=scsi0',
            'scsi0'   => "{$storage}:{$diskGb},format=qcow2",
        ]);
        return $r['ok'] ? ['ok'=>true, 'vmid'=>$vmid, 'task'=>$r['data']] : $r;
    }


//Elimina un archivo ISO del almacenamiento de Proxmox.

public function deleteISO(string $volid): array {
    // volid format: "storage:iso/filename.iso"
    $parts = explode(':', $volid, 2);
    if (count($parts) !== 2) {
        return ['ok' => false, 'error' => 'Invalid volid format'];
    }
    $storage = $parts[0];
    $path = $parts[1];
    
    $encodedPath = implode('/', array_map('rawurlencode', explode('/', $path)));
    
    $url = "/nodes/{$this->node}/storage/{$storage}/content/{$encodedPath}";
    return $this->del($url);
}

    //Convertir una VM en una plantilla de Proxmox (llamar una vez y usar clones vinculados)
    public function convertToTemplate(int $vmid): array {
        $r = $this->post("/nodes/{$this->node}/qemu/{$vmid}/template", []);
        return $r['ok'] ? ['ok'=>true] : $r;
    }

    // Clone de una VM
    public function cloneVM(int $sourceVmid, string $newName, string $storage = 'local-lvm', bool $linked = true): array {
        $newVmid = $this->nextVmid();
        if (!$newVmid) return ['ok'=>false, 'error'=>'Could not get next VMID'];

        $params = [
            'newid' => $newVmid,
            'name'  => $this->sanitizeName($newName),
            'full'  => $linked ? 0 : 1,   // 0 = linked, 1 = full
        ];
        if (!$linked) $params['storage'] = $storage;

        $r = $this->post("/nodes/{$this->node}/qemu/{$sourceVmid}/clone", $params);
        return $r['ok'] ? ['ok'=>true, 'vmid'=>$newVmid, 'task'=>$r['data']] : $r;
    }

    // Ciclo de vida

    public function startVM(int $vmid): array {
        return $this->post("/nodes/{$this->node}/qemu/{$vmid}/status/start", []);
    }

    public function stopVM(int $vmid): array {
        return $this->post("/nodes/{$this->node}/qemu/{$vmid}/status/stop", []);
    }

    public function killVM(int $vmid): array {
        return $this->post("/nodes/{$this->node}/qemu/{$vmid}/status/stop", []);
    }

    public function deleteVM(int $vmid): array {
        $this->killVM($vmid);
        sleep(1);
        return $this->del("/nodes/{$this->node}/qemu/{$vmid}?purge=1&destroy-unreferenced-disks=1");
    }

    public function getTaskStatus(string $upid, string $node): array {
        return $this->get("/nodes/{$node}/tasks/" . rawurlencode($upid) . "/status");
    }

    public function getVMStatus(int $vmid): array {
        $r = $this->get("/nodes/{$this->node}/qemu/{$vmid}/status/current");
        if (!$r['ok']) return $r;
        $s = $r['data']['status'] ?? 'stopped';
        return ['ok'=>true, 'status'=>($s==='running'?'running':'stopped'), 'proxmox_status'=>$s, 'data'=>$r['data']];
    }

    // Consola VNC (no se requiere inicio de sesión en Proxmox para el usuario final)
    public function getVNCTicket(int $vmid): array {
        $r = $this->post("/nodes/{$this->node}/qemu/{$vmid}/vncproxy", ['websocket'=>1]);
        if (!$r['ok']) return $r;
        return [
            'ok'     => true,
            'ticket' => $r['data']['ticket'] ?? '',
            'port'   => $r['data']['port']   ?? 5900,
            'host'   => $this->host,
            'pve_port' => $this->port,
            'node'   => $this->node,
            'vmid'   => $vmid,
        ];
    }

    // Espera a que una tarea de Proxmox (como un clon) finalice.
    // Proxmox devuelve una cadena UPID como "UPID:pve:00001A2B:..." para operaciones asíncronas.
    
    public function waitForTask(string $upid, int $timeout = 180): array {
        $enc   = urlencode($upid);
        $start = time();
        while (time() - $start < $timeout) {
            $r = $this->get("/nodes/{$this->node}/tasks/{$enc}/status");
            if (!$r['ok']) return $r;
            if (($r['data']['status'] ?? '') === 'stopped') {
                $exit = $r['data']['exitstatus'] ?? '';
                return $exit === 'OK' ? ['ok'=>true] : ['ok'=>false, 'error'=>"Task failed: $exit"];
            }
            sleep(2);
        }
        return ['ok'=>false, 'error'=>"Task timed out after {$timeout}s"];
    }

    // Helpers
    private function sanitizeName(string $name): string {
        $name = preg_replace('/[^a-zA-Z0-9\-]/', '-', $name);
        $name = trim(preg_replace('/-+/', '-', $name), '-');
        return substr($name ?: 'vm', 0, 63);
    }

    // Obtiene todos los archivos ISO del almacenamiento de Proxmox
    public function getISOs(string $storage): array {
        $r = $this->get("/nodes/{$this->node}/storage/{$storage}/content");
        if (!$r['ok']) return [];
        $isos = [];
        foreach (($r['data'] ?? []) as $item) {
            if (isset($item['content']) && str_contains($item['content'], 'iso')) {
                $isos[] = [
                    'volid'  => $item['volid'],
                    'name'   => basename($item['volid']),
                    'size'   => $item['size'] ?? 0,
                ];
            }
        }
        usort($isos, fn($a,$b) => strcmp($a['name'], $b['name']));
        return $isos;
    }

    // Crea una VM en Proxmox y la inicia inmediatamente, devolviendo un ticket VNC.
    public function createAndStart(
        string $name,
        int    $ramMb,
        int    $cpuCores,
        int    $diskGb,
        string $isoVolid,
        string $diskStorage = 'local-lvm'
    ): array {
        $vmid = $this->nextVmid();
        if (!$vmid) return ['ok'=>false,'error'=>'Could not get next VMID'];

        // Crea la VM
        $r = $this->post("/nodes/{$this->node}/qemu", [
            'vmid'    => $vmid,
            'name'    => $this->sanitizeName($name),
            'memory'  => $ramMb,
            'cores'   => $cpuCores,
            'sockets' => 1,
            'cpu'     => 'host',
            'kvm'     => 1,
            'net0'    => 'virtio,bridge=vmbr0',
            'scsihw'  => 'virtio-scsi-pci',
            'scsi0'   => "{$diskStorage}:{$diskGb}",
            'ide2'    => "{$isoVolid},media=cdrom",
            'boot'    => 'order=ide2;scsi0',
            'ostype'  => 'l26',
            'agent'   => 1,
        ]);
        if (!$r['ok']) return $r;

        // Espera a que la tarea de creación finalice
        if (!empty($r['data'])) {
            $wait = $this->waitForTask($r['data'], 120);
            if (!$wait['ok']) return ['ok'=>false,'error'=>'VM creation task failed: '.$wait['error']];
        }

        // Inicia la VM
        $start = $this->post("/nodes/{$this->node}/qemu/{$vmid}/status/start", []);
        if ($start['ok'] && !empty($start['data'])) {
            $this->waitForTask($start['data'], 60);
        }

        // Obtiene el ticket VNC
        $vnc = $this->post("/nodes/{$this->node}/qemu/{$vmid}/vncproxy", ['websocket'=>1]);
        if (!$vnc['ok']) return ['ok'=>false,'error'=>'VM created (VMID '.$vmid.') but VNC ticket failed: '.$vnc['error'],'vmid'=>$vmid];

        return [
            'ok'     => true,
            'vmid'   => $vmid,
            'ticket' => $vnc['data']['ticket'] ?? '',
            'port'   => $vnc['data']['port']   ?? 5900,
            'node'   => $this->node,
            'host'   => $this->host,
        ];
    }

    // Genera proxy_config.json
    public function writeProxyConfig(string $path, int $proxyPort): bool {
        $cfg = json_encode([
            'pve_ip'       => $this->host,
            'pve_port'     => $this->port,
            'token_id'     => $this->tokenId,
            'token_secret' => $this->secret,
            'proxy_port'   => $proxyPort,
        ], JSON_PRETTY_PRINT);
        return file_put_contents($path, $cfg) !== false;
    }

    public function getHost(): string  { return $this->host; }
    public function getPort(): int     { return $this->port; }
    public function getNode(): string  { return $this->node; }
}

// Obtiene una instancia de ProxmoxAPI
function getProxmox(): ?ProxmoxAPI {
    if (!defined('PROXMOX_ENABLED') || !PROXMOX_ENABLED) return null;
    if (!function_exists('curl_init'))                   return null;
    if (!defined('PROXMOX_HOST')    || !PROXMOX_HOST)    return null;
    return new ProxmoxAPI(
        PROXMOX_HOST,
        PROXMOX_NODE          ?? 'pve',
        PROXMOX_TOKEN_ID      ?? '',
        PROXMOX_TOKEN_SECRET  ?? '',
        PROXMOX_PORT          ?? 8006
    );
}
