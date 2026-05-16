<?php
/**
 * ╔═══════════════════════════════════════════════════════════════╗
 * ║          echo API — Archivo de demostración                   ║
 * ║          Descargado desde http://echo.dev/docs.php            ║
 * ╚═══════════════════════════════════════════════════════════════╝
 *
 * INSTRUCCIONES:
 * 1. Coloca este archivo en tu proyecto PHP.
 * 2. Cambia ECHO_BASE por la URL real de tu servidor echo.
 * 3. Cambia ECHO_TOKEN por el token que generaste en docs.php.
 * 4. Visita este archivo en tu navegador para ver la demo.
 *
 * ENDPOINTS DISPONIBLES:
 *   GET  /api/me.php                          → datos del usuario autenticado
 *   GET  /api/vms.php                         → lista de VMs del usuario
 *   GET  /api/vm_status.php?vm_id=N           → estado de una VM
 *   POST /api/vm_status.php  {vm_id, action}  → iniciar/parar una VM
 *   GET  /api/vm_clones.php?template_id=N     → clones de una plantilla
 */

// ── Configuración ─────────────────────────────────────────────────────────────
define('ECHO_BASE',  'http://192.168.248.48');  // ← IP o dominio de tu servidor echo (sin / al final)
define('ECHO_TOKEN', 'PON_TU_TOKEN_AQUI');       // ← token generado en docs.php → Mis tokens

// ── Función de ayuda ──────────────────────────────────────────────────────────
/**
 * Realiza una petición a la API de echo.
 *
 * @param string $endpoint  Ruta del endpoint, ej: '/api/me.php'
 * @param array  $params    Para GET: query params. Para POST: body JSON.
 * @param string $method    'GET' o 'POST'
 * @return array|null       Array decodificado o null si falla
 */
function echo_request(string $endpoint, array $params = [], string $method = 'GET'): ?array {
    $url = ECHO_BASE . $endpoint;

    $headers = [
        'Authorization: Bearer ' . ECHO_TOKEN,
        'Content-Type: application/json',
        'Accept: application/json',
    ];

    $opts = [
        'http' => [
            'method'        => $method,
            'header'        => implode("\r\n", $headers),
            'ignore_errors' => true,   // Devuelve la respuesta aunque sea 4xx
            'timeout'       => 8,
        ]
    ];

    if ($method === 'GET' && $params) {
        $url .= '?' . http_build_query($params);
    } elseif ($method === 'POST') {
        $opts['http']['content'] = json_encode($params);
    }

    $raw = @file_get_contents($url, false, stream_context_create($opts));
    return $raw ? json_decode($raw, true) : null;
}

// EJEMPLOS DE USO

// 1. Verificar al usuario autenticado
$user = echo_request('/api/me.php');

// 2. Listar sus VMs (solo si está autenticado)
$vmsData = null;
if ($user && $user['ok']) {
    $vmsData = echo_request('/api/vms.php');
}

// Demo HTML
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>echo API — Demo</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Inter', system-ui, sans-serif; background: #080810; color: #f0f0fa; min-height: 100vh; padding: 2rem 1rem; line-height: 1.6; }
        .wrap { max-width: 760px; margin: 0 auto; }
        h1 { font-size: 1.6rem; font-weight: 700; letter-spacing: -.03em; margin-bottom: .25rem; }
        h1 span { color: #9b7fcc; }
        .subtitle { color: rgba(240,240,250,.45); font-size: .85rem; margin-bottom: 2.5rem; }
        h2 { font-size: .95rem; font-weight: 600; margin: 1.8rem 0 .65rem; color: #f0f0fa; display: flex; align-items: center; gap: .5rem; }
        h2::before { content: ''; width: 3px; height: 1em; background: #7b5ea7; border-radius: 2px; flex-shrink: 0; }
        .card { background: #111120; border: 1px solid rgba(255,255,255,.07); border-radius: 10px; padding: 1.2rem 1.4rem; margin-bottom: .8rem; }
        .field { display: flex; align-items: baseline; gap: .6rem; padding: .28rem 0; border-bottom: 1px solid rgba(255,255,255,.04); font-size: .84rem; }
        .field:last-child { border-bottom: none; }
        .field-key { color: rgba(240,240,250,.4); min-width: 110px; font-size: .75rem; flex-shrink: 0; }
        .field-val { color: #f0f0fa; font-family: 'JetBrains Mono', monospace; font-size: .8rem; word-break: break-all; }
        .badge { display: inline-block; padding: .12rem .45rem; border-radius: 5px; font-size: .65rem; font-weight: 600; }
        .badge-green { background: rgba(34,201,126,.12); color: #22c97e; border: 1px solid rgba(34,201,126,.2); }
        .badge-orange { background: rgba(245,158,11,.1); color: #f59e0b; border: 1px solid rgba(245,158,11,.2); }
        .badge-red { background: rgba(240,74,110,.1); color: #f04a6e; border: 1px solid rgba(240,74,110,.2); }
        .badge-purple { background: rgba(123,94,167,.15); color: #9b7fcc; border: 1px solid rgba(123,94,167,.3); }
        .vm-list { display: flex; flex-direction: column; gap: .5rem; }
        .vm-card { background: #181828; border: 1px solid rgba(255,255,255,.06); border-radius: 8px; padding: .85rem 1rem; display: flex; align-items: center; justify-content: space-between; gap: 1rem; }
        .vm-name { font-size: .88rem; font-weight: 600; }
        .vm-meta { font-size: .7rem; color: rgba(240,240,250,.4); margin-top: .1rem; font-family: 'JetBrains Mono', monospace; }
        .vm-actions { display: flex; gap: .4rem; flex-shrink: 0; }
        .btn { display: inline-flex; align-items: center; gap: .3rem; padding: .38rem .75rem; border-radius: 6px; font-size: .75rem; font-weight: 600; cursor: pointer; border: none; font-family: 'Inter', system-ui; transition: all .14s; white-space: nowrap; }
        .btn-start { background: rgba(34,201,126,.15); color: #22c97e; border: 1px solid rgba(34,201,126,.25); }
        .btn-start:hover { background: rgba(34,201,126,.25); }
        .btn-stop { background: rgba(240,74,110,.1); color: #f04a6e; border: 1px solid rgba(240,74,110,.22); }
        .btn-stop:hover { background: rgba(240,74,110,.2); }
        .btn-disabled { opacity: .4; cursor: not-allowed; }
        .alert { padding: .75rem 1rem; border-radius: 8px; font-size: .83rem; margin-bottom: 1.2rem; display: flex; align-items: flex-start; gap: .6rem; line-height: 1.55; }
        .alert-error { background: rgba(240,74,110,.1); border: 1px solid rgba(240,74,110,.22); color: rgba(240,240,250,.8); }
        .alert-info  { background: rgba(123,94,167,.1); border: 1px solid rgba(123,94,167,.25); color: rgba(240,240,250,.8); }
        .alert svg { flex-shrink: 0; margin-top: .1rem; }
        .code-block { background: #0d0d18; border: 1px solid rgba(255,255,255,.07); border-radius: 8px; padding: 1rem 1.1rem; font-family: 'JetBrains Mono', monospace; font-size: .78rem; color: rgba(240,240,250,.55); margin: .5rem 0 1.2rem; overflow-x: auto; line-height: 1.75; }
        .code-block .hl-key { color: #9b7fcc; }
        .code-block .hl-str { color: #22c97e; }
        .code-block .hl-num { color: #f59e0b; }
        .code-block .hl-cmt { color: rgba(240,240,250,.28); font-style: italic; }
        .status-indicator { display: inline-flex; align-items: center; gap: .35rem; }
        .status-dot { width: 7px; height: 7px; border-radius: 50%; flex-shrink: 0; }
        .status-dot.running { background: #22c97e; box-shadow: 0 0 5px rgba(34,201,126,.5); }
        .status-dot.stopped { background: rgba(240,240,250,.2); }
        .section-note { font-size: .76rem; color: rgba(240,240,250,.35); margin-bottom: 1rem; }
        .toast { position: fixed; bottom: 1.5rem; right: 1.5rem; background: #111120; border: 1px solid rgba(255,255,255,.1); border-radius: 8px; padding: .6rem 1rem; font-size: .8rem; box-shadow: 0 8px 32px rgba(0,0,0,.5); transform: translateY(8px); opacity: 0; transition: all .25s; pointer-events: none; z-index: 999; }
        .toast.show { transform: none; opacity: 1; }
    </style>
</head>
<body>
<div class="wrap">

    <h1><span>echo</span> API — Demo</h1>
    <p class="subtitle">
        Archivo de demostración descargado desde <code style="color:#9b7fcc;background:#1a1a2e;padding:.1rem .35rem;border-radius:4px;">echo.dev/docs.php</code>
        &nbsp;·&nbsp; Consulta la documentación completa en <a href="<?= ECHO_BASE ?>/docs.php" style="color:#9b7fcc;">docs.php</a>
    </p>

    <?php if (!$user): ?>
    <div class="alert alert-error">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#f04a6e" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
        <div>
            <strong>No se pudo conectar con echo.</strong><br>
            Comprueba que <code>ECHO_BASE</code> = <code><?= htmlspecialchars(ECHO_BASE) ?></code> está accesible y que el token es válido.
        </div>
    </div>

    <?php elseif (!$user['ok']): ?>
    <div class="alert alert-error">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#f04a6e" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
        <div>
            <strong>Token inválido o sin sesión.</strong><br>
            Error: <?= htmlspecialchars($user['error'] ?? 'desconocido') ?><br>
            Genera un token nuevo en <a href="<?= ECHO_BASE ?>/docs.php" style="color:#9b7fcc;">docs.php → Mis tokens</a>
        </div>
    </div>

    <?php else: ?>

    <!-- Usuario autenticado -->
    <h2>Usuario autenticado</h2>
    <p class="section-note">
        Respuesta de <code style="color:#9b7fcc"><?= htmlspecialchars(ECHO_BASE) ?>/api/me.php</code>
    </p>
    <div class="card">
        <div class="field"><span class="field-key">id</span>       <span class="field-val"><?= (int)$user['id'] ?></span></div>
        <div class="field"><span class="field-key">username</span>  <span class="field-val"><?= htmlspecialchars($user['username']) ?></span></div>
        <div class="field"><span class="field-key">email</span>     <span class="field-val"><?= htmlspecialchars($user['email'] ?: '—') ?></span></div>
        <div class="field">
            <span class="field-key">role</span>
            <span class="field-val">
                <?= (int)$user['role'] ?> —
                <?php
                $badges = [0=>'badge-red',1=>'badge-orange',2=>'badge-green',3=>'badge-purple',4=>'badge-orange',5=>'badge-red'];
                $cls = $badges[(int)$user['role']] ?? 'badge-orange';
                ?>
                <span class="badge <?= $cls ?>"><?= htmlspecialchars($user['role_name']) ?></span>
            </span>
        </div>
        <div class="field"><span class="field-key">lang</span>      <span class="field-val"><?= htmlspecialchars($user['lang'] ?? '—') ?></span></div>
        <div class="field"><span class="field-key">center_id</span> <span class="field-val"><?= $user['center_id'] ?? '—' ?></span></div>
    </div>

    <!-- VMs -->
    <h2>Mis VMs</h2>
    <p class="section-note">
        Respuesta de <code style="color:#9b7fcc"><?= htmlspecialchars(ECHO_BASE) ?>/api/vms.php</code>
        &nbsp;·&nbsp; <?= $vmsData ? (int)($vmsData['count'] ?? 0) : 0 ?> VM(s)
    </p>

    <?php if (!$vmsData || !$vmsData['ok']): ?>
    <div class="alert alert-error">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#f04a6e" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
        No se pudieron cargar las VMs.
    </div>
    <?php elseif (empty($vmsData['vms'])): ?>
    <div class="alert alert-info">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#9b7fcc" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
        Este usuario no tiene VMs asignadas.
    </div>
    <?php else: ?>
    <div class="vm-list">
        <?php foreach ($vmsData['vms'] as $vm): ?>
        <div class="vm-card" id="vm-<?= (int)$vm['id'] ?>">
            <div style="min-width:0;">
                <div class="vm-name"><?= htmlspecialchars($vm['name']) ?></div>
                <div class="vm-meta">
                    ID:<?= (int)$vm['id'] ?>
                    · <?= (int)$vm['ram_mb'] >= 1024 ? round($vm['ram_mb']/1024,1).'GB' : $vm['ram_mb'].'MB' ?>
                    · <?= (int)$vm['cpu_cores'] ?> CPU
                    · <?= (int)$vm['disk_gb'] ?> GB
                    <?php if ($vm['is_template']): ?> · <span style="color:#9b7fcc">template</span><?php endif; ?>
                </div>
            </div>
            <div class="vm-actions">
                <div class="status-indicator" style="margin-right:.4rem;">
                    <span class="status-dot <?= $vm['status'] === 'running' ? 'running' : 'stopped' ?>"></span>
                    <span style="font-size:.72rem;color:rgba(240,240,250,.5);"><?= htmlspecialchars($vm['status']) ?></span>
                </div>
                <?php if (!$vm['is_template']): ?>
                <button class="btn btn-start" onclick="vmAction(<?= (int)$vm['id'] ?>, 'start', this)">▶ Iniciar</button>
                <button class="btn btn-stop"  onclick="vmAction(<?= (int)$vm['id'] ?>, 'stop', this)">■ Parar</button>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Código de ejemplo -->
    <h2 style="margin-top:2.5rem;">Cómo funciona este archivo</h2>
    <p class="section-note">
        Estas tres líneas al inicio de cualquier página de tu sitio son suficientes para verificar a un usuario de echo:
    </p>
    <div class="code-block">
<span class="hl-cmt">// Copia la función echo_request() de este archivo a tu proyecto</span>

<span class="hl-key">define</span>(<span class="hl-str">'ECHO_BASE'</span>,  <span class="hl-str">'<?= htmlspecialchars(ECHO_BASE) ?>'</span>);
<span class="hl-key">define</span>(<span class="hl-str">'ECHO_TOKEN'</span>, <span class="hl-str">'tu_token_aqui'</span>);

$user = echo_request(<span class="hl-str">'/api/me.php'</span>);

<span class="hl-key">if</span> (!$user || !$user[<span class="hl-str">'ok'</span>]) {
    <span class="hl-cmt">// No autenticado → redirigir al login de echo</span>
    header(<span class="hl-str">'Location: <?= htmlspecialchars(ECHO_BASE) ?>/login.php'</span>);
    exit;
}

<span class="hl-cmt">// ✓ Usuario válido</span>
<span class="hl-key">echo</span> <span class="hl-str">"Hola, "</span> . $user[<span class="hl-str">'username'</span>];          <span class="hl-cmt">// "Hola, maria"</span>
<span class="hl-key">echo</span> <span class="hl-str">"Tu rol es: "</span> . $user[<span class="hl-str">'role_name'</span>];    <span class="hl-cmt">// "Tu rol es: Teacher"</span>

<span class="hl-cmt">// Comprobar rol mínimo (2 = Estudiante, 3 = Profesor)</span>
<span class="hl-key">if</span> ($user[<span class="hl-str">'role'</span>] < <span class="hl-num">2</span>) {
    die(<span class="hl-str">'Acceso denegado. Se necesita al menos rol Estudiante.'</span>);
}
    </div>

    <?php endif; ?>

    <!-- Documentación -->
    <div class="alert alert-info" style="margin-top:2rem;">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#9b7fcc" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
        <div>
            Documentación completa y más ejemplos en
            <a href="<?= ECHO_BASE ?>/docs.php" style="color:#9b7fcc;font-weight:600;"><?= htmlspecialchars(ECHO_BASE) ?>/docs.php</a>
        </div>
    </div>
</div>

<div class="toast" id="toast"></div>

<script>
const ECHO_BASE  = '<?= htmlspecialchars(ECHO_BASE, ENT_QUOTES) ?>';
const ECHO_TOKEN = '<?= htmlspecialchars(ECHO_TOKEN, ENT_QUOTES) ?>';

function showToast(msg, ok = true) {
    const t = document.getElementById('toast');
    t.textContent = msg;
    t.style.borderLeftColor = ok ? '#22c97e' : '#f04a6e';
    t.classList.add('show');
    setTimeout(() => t.classList.remove('show'), 2800);
}

async function vmAction(vmId, action, btn) {
    btn.disabled = true;
    const oldText = btn.textContent;
    btn.textContent = '…';
    try {
        const res = await fetch(ECHO_BASE + '/api/vm_status.php', {
            method: 'POST',
            headers: { 'Authorization': 'Bearer ' + ECHO_TOKEN, 'Content-Type': 'application/json' },
            body: JSON.stringify({ vm_id: vmId, action })
        });
        const d = await res.json();
        if (d.ok) {
            showToast((action === 'start' ? '▶ VM iniciada' : '■ VM parada'));
            // Update status dot
            const card = document.getElementById('vm-' + vmId);
            if (card) {
                const dot  = card.querySelector('.status-dot');
                const lbl  = card.querySelector('.status-indicator span:last-child');
                const stat = action === 'start' ? 'running' : 'stopped';
                if (dot) { dot.className = 'status-dot ' + stat; }
                if (lbl) lbl.textContent = stat;
            }
        } else {
            showToast('Error: ' + (d.error || 'desconocido'), false);
        }
    } catch(e) {
        showToast('Error de red', false);
    }
    btn.textContent = oldText;
    btn.disabled = false;
}
</script>
</body>
</html>
