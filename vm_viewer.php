<?php

//Visualizador de VMs, si Proxmox esta conectado y la VM tiene un proxmox_vmid, 
//obtiene un ticket VNC del servidor y renderiza noVNC directamente en esta página.
//El estudiante nunca inicia sesión en Proxmox.

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/api/token_auth.php';
require_once __DIR__ . '/lang.php';
require_once __DIR__ . '/includes/proxmox.php';

$tokenUser = authenticateApiToken();
if ($tokenUser) {
    if (!session_id()) { session_start(); }
    $_SESSION['user_id'] = (int)$tokenUser['id'];
}
requireLogin(); enforceBan();

$vmId        = (int)($_GET['id']   ?? 0);
$vm          = $vmId ? DB::getVmById($vmId) : null;
$displayName = $vm ? htmlspecialchars($vm['name']) : htmlspecialchars($_GET['name'] ?? 'VM');
$currentLang = getCurrentLang();
$embedded    = !empty($_GET['embedded']); // True cuando esta dentro de una iframe de VM

// Determinación del modo de consola
$proxmoxVmid = $vm ? (int)($vm['proxmox_vmid'] ?? 0) : 0;
$vncData     = null;   // contiene el ticket + puerto si Proxmox esta disponible
$pveError    = '';

if ($proxmoxVmid && defined('PROXMOX_ENABLED') && PROXMOX_ENABLED) {
    $pve = getProxmox();
    if ($pve) {
        if ($vm && ($vm['status'] ?? 'stopped') !== 'running') {
            $pve->startVM($proxmoxVmid);
            DB::updateVmStatus($vmId, 'running');
        }
        $ticketResult = $pve->getVNCTicket($proxmoxVmid);
        if ($ticketResult['ok']) {
            $vncData = $ticketResult;
        } else {
            $pveError = $ticketResult['error'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="<?= $currentLang ?>">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= $displayName ?> — <?= APP_NAME ?></title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/css/style.css">
<script>(function(){var s=localStorage.getItem('theme'),d=window.matchMedia('(prefers-color-scheme: dark)').matches;if(s==='light'||(!s&&!d))document.documentElement.setAttribute('data-theme','light');else document.documentElement.setAttribute('data-theme','dark');})()</script>
<style>
html,body{width:100%;height:100%;overflow:hidden;background:#000;margin:0;padding:0;}
#vm-wrap{display:flex;flex-direction:column;height:100%;}
#vm-bar{
    display:flex;align-items:center;justify-content:space-between;
    height:48px;padding:0 1rem;flex-shrink:0;
    background:var(--glass);backdrop-filter:blur(20px) saturate(1.5);
    border-bottom:1px solid var(--sep);gap:.75rem;
}
<?php if ($embedded): ?>
#vm-bar { display: none !important; }
#vm-wrap { padding-top: 0; }
<?php endif; ?>
#vm-bar-left{display:flex;align-items:center;gap:.55rem;min-width:0;}
.vm-bar-icon{width:26px;height:26px;border-radius:7px;background:var(--accent-bg);border:1px solid var(--accent-border);display:flex;align-items:center;justify-content:center;color:var(--accent);flex-shrink:0;}
#vm-bar-name{font-size:.82rem;font-weight:700;color:var(--text);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:300px;}
.status-pill{display:inline-flex;align-items:center;gap:.32rem;padding:.12rem .55rem;border-radius:var(--r-pill);font-size:.6rem;font-weight:600;}
.status-pill.running{background:var(--green-bg);color:var(--green);border:1px solid rgba(16,185,129,.2);}
.status-pill.stopped{background:var(--surface2);color:var(--text-3);border:1px solid var(--sep-bold);}
.status-dot{width:5px;height:5px;border-radius:50%;background:currentColor;}
#vm-bar-right{display:flex;align-items:center;gap:.4rem;flex-shrink:0;}
#vm-screen{flex:1;position:relative;overflow:hidden;background:#000;}

#novnc-canvas-wrap{position:absolute;inset:0;}
#novnc-canvas-wrap canvas{width:100%!important;height:100%!important;}

.vm-placeholder{position:absolute;inset:0;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:1rem;background:var(--bg);color:var(--text-3);font-family:var(--font);}
.vm-placeholder-icon{width:52px;height:52px;border-radius:14px;background:var(--surface2);border:1px solid var(--sep-bold);display:flex;align-items:center;justify-content:center;}
.vm-placeholder-title{font-size:.9rem;font-weight:600;color:var(--text);}
.vm-placeholder-sub{font-size:.75rem;text-align:center;max-width:360px;line-height:1.6;}
.vm-placeholder-error{font-size:.7rem;font-family:var(--mono);background:var(--surface2);border:1px solid var(--sep-bold);padding:.4rem .75rem;border-radius:var(--r-sm);color:var(--red);max-width:440px;word-break:break-all;}

#connecting-overlay{position:absolute;inset:0;background:var(--bg);display:flex;flex-direction:column;align-items:center;justify-content:center;gap:.75rem;z-index:10;transition:opacity .4s;}
#connecting-overlay.hidden{opacity:0;pointer-events:none;}
.spinner{width:28px;height:28px;border:3px solid var(--sep-bold);border-top-color:var(--accent);border-radius:50%;animation:spin .7s linear infinite;}
@keyframes spin{to{transform:rotate(360deg)}}
</style>
</head>
<body>
<div id="vm-wrap">

  <div id="vm-bar">
    <div id="vm-bar-left">
      <div class="vm-bar-icon">
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><rect x="2" y="3" width="20" height="14" rx="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg>
      </div>
      <span id="vm-bar-name"><?= $displayName ?></span>
      <span class="status-pill <?= ($vm['status']??'stopped')==='running' ? 'running' : 'stopped' ?>" id="status-pill">
        <div class="status-dot"></div>
        <?= ($vm['status']??'stopped')==='running' ? 'Running' : 'Stopped' ?>
      </span>
      <?php if ($proxmoxVmid): ?>
      <span style="font-size:.58rem;font-family:var(--mono);color:var(--text-3);">PVE #<?= $proxmoxVmid ?></span>
      <?php endif; ?>
    </div>
    <div id="vm-bar-right">
      <?php if ($vncData): ?>
      <button class="btn btn-sm btn-outline" style="font-size:.68rem;" onclick="toggleFullscreen()">
        <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M8 3H5a2 2 0 0 0-2 2v3m18 0V5a2 2 0 0 0-2-2h-3m0 18h3a2 2 0 0 0 2-2v-3M3 16v3a2 2 0 0 0 2 2h3"/></svg>
        Fullscreen
      </button>
      <?php endif; ?>
      <a href="index.php" class="btn btn-sm btn-outline" style="font-size:.68rem;">
        <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
        Dashboard
      </a>
    </div>
  </div>

  <div id="vm-screen">

    <?php if ($vncData): ?>

      <div id="connecting-overlay">
        <div class="spinner"></div>
        <div style="font-size:.78rem;color:var(--text-2);">Connecting to VM console…</div>
        <div style="font-size:.62rem;font-family:var(--mono);color:var(--text-3);">PVE #<?= $proxmoxVmid ?> · <?= htmlspecialchars($vncData['host']) ?></div>
      </div>

      <div id="novnc-canvas-wrap"></div>
      <script type="module">
        import RFB from 'https://cdn.jsdelivr.net/npm/@novnc/novnc@1.4.0/core/rfb.js';

        // Los datos de VNC pueden venir de dos fuentes:
        // 1. Servidor PHP (inicio estándar) → variables abajo
        // 2. sessionStorage (después de crear VM en index.php)
        let ticket = <?= json_encode($vncData ? $vncData['ticket'] : '') ?>;
        let port   = <?= $vncData ? (int)$vncData['port'] : 0 ?>;
        let vmid   = <?= (int)$proxmoxVmid ?>;
        let node   = <?= json_encode($vncData ? $vncData['node'] : PROXMOX_NODE) ?>;

        const stored = sessionStorage.getItem('vnc_data');
        if (stored) {
            try {
                const d = JSON.parse(stored);
                if (d.ticket) { ticket = d.ticket; port = d.port; vmid = d.vmid; node = d.node; }
                sessionStorage.removeItem('vnc_data');
            } catch(_) {}
        }

        const wsProto = window.location.protocol === 'https:' ? 'wss:' : 'ws:';
        const wsBase  = `${wsProto}//${window.location.host}/novnc-proxy`;
        const wsUrl   = `${wsBase}/api2/json/nodes/${node}/qemu/${vmid}/vncwebsocket?port=${port}&vncticket=${encodeURIComponent(ticket)}`;

        const wrap = document.getElementById('novnc-canvas-wrap');
        const overlay = document.getElementById('connecting-overlay');

        let rfb;
        try {
            rfb = new RFB(wrap, wsUrl, {
                credentials: { password: ticket },
            });

            rfb.addEventListener('connect', () => {
                overlay.classList.add('hidden');
                rfb.scaleViewport = true;
                rfb.resizeSession = true;
                document.getElementById('status-pill').className = 'status-pill running';
                document.getElementById('status-pill').innerHTML = '<div class="status-dot"></div>Running';
            });

            rfb.addEventListener('disconnect', (e) => {
                overlay.classList.remove('hidden');
                overlay.innerHTML = `
                    <div style="width:40px;height:40px;border-radius:10px;background:var(--surface2);border:1px solid var(--sep-bold);display:flex;align-items:center;justify-content:center;">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="var(--text-3)" stroke-width="1.8"><line x1="1" y1="1" x2="23" y2="23"/><path d="M16.72 11.06A10.94 10.94 0 0 1 19 12.55"/><path d="M5 12.55a10.94 10.94 0 0 1 5.17-2.39"/><path d="M10.71 5.05A16 16 0 0 1 22.56 9"/><path d="M1.42 9a15.91 15.91 0 0 1 4.7-2.88"/><path d="M8.53 16.11a6 6 0 0 1 6.95 0"/><line x1="12" y1="20" x2="12.01" y2="20"/></svg>
                    </div>
                    <div style="font-size:.82rem;font-weight:600;color:var(--text);">Disconnected</div>
                    <div style="font-size:.72rem;color:var(--text-3);">${e.detail.clean ? 'Session ended cleanly.' : 'Connection lost.'}</div>
                    <button onclick="location.reload()" class="btn btn-sm" style="margin-top:.5rem;">Reconnect</button>`;
            });

            rfb.addEventListener('credentialsrequired', () => {
                rfb.sendCredentials({ password: ticket });
            });

        } catch(err) {
            overlay.innerHTML = `<div class="vm-placeholder-error">noVNC error: ${err.message}</div>`;
        }

        window.toggleFullscreen = function() {
            if (!document.fullscreenElement) {
                document.getElementById('vm-screen').requestFullscreen();
            } else {
                document.exitFullscreen();
            }
        };
      </script>

    <?php elseif ($pveError): ?>

      <div class="vm-placeholder">
        <div class="vm-placeholder-icon">
          <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="var(--red)" stroke-width="1.8" stroke-linecap="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
        </div>
        <div class="vm-placeholder-title">Proxmox error</div>
        <div class="vm-placeholder-error"><?= htmlspecialchars($pveError) ?></div>
        <a href="index.php" class="btn btn-sm btn-outline" style="margin-top:.5rem;">Back to Dashboard</a>
      </div>

    <?php else: ?>

      <div class="vm-placeholder">
        <div class="vm-placeholder-icon">
          <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="var(--accent)" stroke-width="1.8" stroke-linecap="round"><rect x="2" y="3" width="20" height="14" rx="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg>
        </div>
        <div class="vm-placeholder-title">VM Console</div>
        <div class="vm-placeholder-sub">
          <?php if (!defined('PROXMOX_ENABLED') || !PROXMOX_ENABLED): ?>
            Proxmox is not enabled. Set <code>PROXMOX_ENABLED = true</code> in <code>config.php</code> and fill in your server details.
          <?php elseif (!$proxmoxVmid): ?>
            This VM is not yet registered in Proxmox. Go back to the dashboard and click the green cube button on the VM card to create it.
          <?php else: ?>
            Could not connect to Proxmox. Check that your host is reachable and the API token is correct.
          <?php endif; ?>
        </div>
        <a href="index.php" class="btn btn-sm btn-outline" style="margin-top:.5rem;">Back to Dashboard</a>
      </div>

    <?php endif; ?>

  </div><!-- #vm-screen -->
</div><!-- #vm-wrap -->
</body>
</html>
