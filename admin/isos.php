<?php
require_once __DIR__ . '/../lang.php';
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../includes/logger.php';
require_once __DIR__ . '/../includes/proxmox.php';
requirePermission('manage_isos');

$me = currentUser();
$myRole = (int)$me['role'];
$myId = (int)$me['id'];
$myCenterId = $me['center_id'] ?? null;

$proxmoxEnabled = defined('PROXMOX_ENABLED') && PROXMOX_ENABLED;
$isoStorage = defined('PROXMOX_ISO_STORAGE') ? PROXMOX_ISO_STORAGE : 'local';
$pve = null;
$error = '';
$success = '';

if ($proxmoxEnabled) {
    $pve = getProxmox();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    if ($action === 'upload_to_proxmox' && $pve) {
        $visibility = $_POST['visibility'] ?? 'private';
        if (!in_array($visibility, ['private', 'center', 'public'])) $visibility = 'private';

        if (!isset($_FILES['iso_file']) || $_FILES['iso_file']['error'] !== UPLOAD_ERR_OK) {
            $phpErr = $_FILES['iso_file']['error'] ?? -1;
            if ($phpErr === UPLOAD_ERR_INI_SIZE || $phpErr === UPLOAD_ERR_FORM_SIZE) {
                $error = t('iso_err_filesize', ['max' => ini_get('upload_max_filesize')]);
            } elseif ($phpErr === UPLOAD_ERR_PARTIAL) {
                $error = t('iso_err_partial');
            } elseif ($phpErr === UPLOAD_ERR_NO_FILE) {
                $error = t('iso_err_nofile');
            } else {
                $error = t('iso_err_generic', ['code' => $phpErr]);
            }
        } else {
            $file = $_FILES['iso_file'];
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, ['iso', 'img'])) {
                $error = t('iso_err_type');
            } else {
                $displayName = trim($_POST['display_name'] ?? '');
                if ($displayName === '') {
                    $displayName = pathinfo($file['name'], PATHINFO_FILENAME);
                }
                $remoteName = preg_replace('/[^a-zA-Z0-9_.-]/', '_', $file['name']);
                $result = $pve->uploadISO($file['tmp_name'], $isoStorage, $remoteName);
                if ($result['ok']) {
                    $volid = $isoStorage . ':iso/' . $remoteName;
                    $size = filesize($file['tmp_name']);
                    DB::addProxmoxIso($volid, $displayName, $size, $visibility, $myId);
                    $success = t('iso_upload_ok', ['name' => $displayName]);
                    dlog('info', 'Proxmox ISO uploaded', ['volid' => $volid, 'user' => $myId]);
                } else {
                    $error = t('iso_err_proxmox_upload', ['err' => $result['error']]);
                    dlog('error', 'Proxmox ISO upload failed', ['error' => $result['error']]);
                }
            }
        }
        header('Location: isos.php');
        exit;
    }

    if ($action === 'delete_iso' && $pve) {
    $isoId = (int)$_POST['iso_id'];
    $iso = DB::getProxmoxIsoById($isoId);
    if (!$iso) {
        $error = t('iso_err_not_found');
    } else {
        $canDelete = ($myRole >= ROLE_ADMIN || $iso['uploaded_by'] === $myId);
        if (!$canDelete) {
            $error = t('iso_err_no_permission');
        } else {
            $deleteResult = $pve->deleteISO($iso['volid']);
            if ($deleteResult['ok']) {
                DB::deleteProxmoxIso($isoId);
                $success = t('iso_deleted');
                dlog('info', 'Proxmox ISO deleted', ['volid' => $iso['volid'], 'user' => $myId]);
            } else {
                $error = t('iso_err_proxmox_delete', ['err' => $deleteResult['error']]);
                dlog('error', 'Proxmox ISO delete failed', ['volid' => $iso['volid'], 'error' => $deleteResult['error']]);
            }
        }
    }
    header('Location: isos.php');
    exit;
    }
}

// Obtiene isos localmente
$trackedIsos = [];
if ($proxmoxEnabled) {
    $trackedIsos = DB::getProxmoxIsosForUser($myId, $myRole, $myCenterId);
}

// Opcionalmente, comprobar que ISOs siguen habiendo en Proxmox (se podria hacer via AJAX, pero por simplicidad mostraremos todos)

function formatBytes($bytes, $precision = 2) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= pow(1024, $pow);
    return round($bytes, $precision) . ' ' . $units[$pow];
}

$pageTitle  = t('admin_tab_isos') . ' — ' . APP_NAME;
$wideLayout = true;
require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <h1><?= t('admin_panel_title') ?></h1>
    <p><?= t('admin_isos_desc') ?></p>
</div>

<div class="admin-tabs">
    <a href="index.php"><?= t('admin_tab_users') ?></a>
    <?php if (can('change_roles') && $myRole === ROLE_ADMIN): ?>
        <a href="groups.php"><?= t('admin_tab_groups') ?></a>
    <?php endif; ?>
    <?php if (can('manage_nodes')): ?>
        <a href="nodes.php"><?= t('admin_tab_nodes') ?></a><?php endif; ?>
            <?php if ($myRole >= ROLE_ADMIN): ?>
            <a href="db_admin.php" class="<?= basename($_SERVER['PHP_SELF'])==='db_admin.php'?'active':'' ?>">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><ellipse cx="12" cy="5" rx="9" ry="3"/><path d="M21 12c0 1.66-4 3-9 3s-9-1.34-9-3"/><path d="M3 5v14c0 1.66 4 3 9 3s9-1.34 9-3V5"/></svg>
                DB Admin
            </a>
            <?php endif; ?>
    <a href="isos.php" class="active"><?= t('admin_tab_isos') ?></a>
</div>

<?php if (!$proxmoxEnabled): ?>
<div class="card" style="text-align:center;padding:2.5rem;">
    <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="color:var(--text-3);margin-bottom:.5rem;"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
    <h3 style="font-size:.9rem;margin-bottom:.25rem;"><?= t('proxmox_not_enabled') ?></h3>
    <p style="color:var(--text-3);font-size:.78rem;"><?= t('proxmox_not_enabled_desc') ?></p>
</div>
<?php else: ?>

<?php if ($error): ?>
<div class="flash flash-error"><?= htmlspecialchars($error) ?></div>
<?php elseif ($success): ?>
<div class="flash flash-success"><?= htmlspecialchars($success) ?></div>
<?php endif; ?>

<div class="iso-page-grid">
    <div class="iso-upload-col">
        <div class="card iso-upload-card">
            <div class="iso-upload-head">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                <span><?= t('upload_iso_btn') ?></span>
            </div>

            <form id="iso-upload-form" method="post" enctype="multipart/form-data">
                <input type="hidden" name="action" value="upload_to_proxmox">

                <div class="form-group">
                    <label><?= t('iso_name_label') ?> (<?= t('optional') ?>)</label>
                    <input type="text" name="display_name" id="iso-display-name" placeholder="<?= htmlspecialchars(t('iso_name_placeholder')) ?>">
                </div>

                <div class="form-group">
                    <label><?= t('iso_scope_label') ?></label>
                    <select name="visibility">
                        <option value="private"><?= t('iso_scope_mine') ?></option>
                        <option value="center"><?= t('iso_scope_center') ?></option>
                        <option value="public"><?= t('iso_scope_all') ?></option>
                    </select>
                </div>

                <div class="iso-drop-zone" id="iso-drop-zone" onclick="document.getElementById('iso-file-input').click()">
                    <input type="file" id="iso-file-input" name="iso_file" accept=".iso,.img" style="display:none;" required>
                    <div class="iso-drop-icon" id="iso-drop-icon">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                    </div>
                    <div class="iso-drop-check" id="iso-drop-check">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="var(--green)" stroke-width="2.5" stroke-linecap="round"><polyline points="20 6 9 17 4 12"/></svg>
                    </div>
                    <div class="iso-drop-title" id="iso-drop-title"><?= t('iso_drop_title') ?></div>
                    <div class="iso-drop-sub" id="iso-drop-sub"><?= t('iso_drop_sub') ?></div>
                </div>

                <div id="upload-progress-wrap" class="iso-progress-wrap">
                    <div class="iso-progress-header">
                        <span id="upload-status-text">Uploading…</span>
                        <span id="upload-percent">0%</span>
                    </div>
                    <div class="iso-progress-track">
                        <div class="iso-progress-fill" id="upload-progress-fill"></div>
                    </div>
                    <div class="iso-progress-speed" id="upload-speed-text"></div>
                </div>

                <button type="submit" id="upload-btn" class="btn iso-upload-btn" disabled>
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                    <?= t('upload_iso_btn') ?>
                </button>
            </form>

            <div class="iso-upload-hint">
                Max upload: <code><?= ini_get('upload_max_filesize') ?: '2M' ?></code>
                · <?= t('iso_upload_hint', ['storage' => htmlspecialchars($isoStorage)]) ?>
            </div>
        </div>
    </div>

    <div class="iso-list-col">
        <div class="card iso-list-card">
            <div class="iso-list-head">
                <div class="iso-list-title">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="12" r="10"/><circle cx="12" cy="12" r="3"/></svg>
                    <?= t('available_isos') ?>
                    <span class="iso-count" title="<?= count($trackedIsos) ?> ISOs"><?= count($trackedIsos) ?></span>
                </div>
                <input type="text" id="os-filter" placeholder="<?= t('filter_placeholder') ?>…" class="iso-search-input" oninput="filterIsos(this.value)">
            </div>

            <?php if (empty($trackedIsos)): ?>
                <div style="padding:2rem;text-align:center;color:var(--text-3);">
                    <?= t('no_isos') ?>
                </div>
            <?php else: ?>
            <div class="iso-table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th><?= t('iso_col_name') ?></th>
                            <th class="hide-mobile"><?= t('iso_scope_label') ?></th>
                            <th class="hide-mobile"><?= t('iso_col_size') ?></th>
                            <th class="hide-mobile"><?= t('iso_col_uploaded') ?></th>
                            <th style="width:50px;"></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($trackedIsos as $iso):
                        $uploader = DB::findUserById($iso['uploaded_by']);
                        $uploaderName = $uploader ? $uploader['username'] : t('unknown');
                        $canDelete = ($myRole >= ROLE_ADMIN || $iso['uploaded_by'] === $myId);
                        $visibilityLabel = [
                            "private" => t('iso_vis_private'),
                            "center"  => t('iso_vis_center'),
                            "public"  => t('iso_vis_public'),
                        ][$iso['visibility']] ?? 'Private';
                        $visibilityClass = [
                            'private' => 'badge-user',
                            'center'  => 'badge-center',
                            'public'  => 'badge-admin',
                        ][$iso['visibility']] ?? 'badge-user';
                    ?>
                        <tr class="iso-row" data-name="<?= strtolower(htmlspecialchars($iso['name'])) ?>">
                            <td>
                                <div class="iso-name-cell">
                                    <div style="width:26px;height:26px;border-radius:6px;background:var(--accent-bg);border:1px solid var(--accent-border);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="var(--accent)" stroke-width="2"><circle cx="12" cy="12" r="10"/><circle cx="12" cy="12" r="3"/></svg>
                                    </div>
                                    <div>
                                        <div class="iso-primary-name"><?= htmlspecialchars($iso['name']) ?></div>
                                        <div class="iso-filename"><?= htmlspecialchars($iso['volid']) ?></div>
                                    </div>
                                </div>
                            </td>
                            <td class="hide-mobile">
                                <span class="badge <?= $visibilityClass ?>"><?= $visibilityLabel ?></span>
                            </td>
                            <td class="hide-mobile" style="color:var(--text-2);font-size:.76rem;white-space:nowrap;">
                                <?= $iso['size'] ? formatBytes($iso['size']) : '—' ?>
                            </td>
                            <td class="hide-mobile" style="color:var(--text-2);font-size:.76rem;">
                                <?= htmlspecialchars($uploaderName) ?>
                                <?= $iso['uploaded_by'] === $myId ? '<span style="color:var(--text-3);font-size:.58rem;margin-left:.1rem;">(' . t('you') . ')</span>' : '' ?>
                            </td>
                            <td>
                                <?php if ($canDelete): ?>
                                <form method="post" onsubmit="return false;" ">
                                    <input type="hidden" name="action" value="delete_iso">
                                    <input type="hidden" name="iso_id" value="<?= $iso['id'] ?>">
                                    <button type="button" class="icon-btn danger" title="<?= t('iso_delete_title') ?>" onclick="openDeleteModal(<?= $iso['id'] ?>, '<?= htmlspecialchars(addslashes($iso['name']), ENT_QUOTES) ?>')">
                                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                                    </button>
                                </form>
                                <?php else: ?>
                                    <span style="color:var(--text-3);">—</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<div id="delete-iso-modal" class="modal-overlay" style="display:none;">
    <div class="card modal-card" style="padding:1.25rem;">
        <h2 style="color:var(--red);font-size:.95rem;margin-bottom:.35rem;display:flex;align-items:center;gap:.3rem;">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
            Delete ISO
        </h2>
        <p style="color:var(--text-2);font-size:.78rem;margin-bottom:.85rem;line-height:1.5;">
            <?= t('iso_delete_confirm_msg') ?> <strong id="delete-iso-name" style="color:var(--text);"></strong> 
        </p>
        <form method="post" id="delete-iso-form">
            <input type="hidden" name="action" value="delete_iso">
            <input type="hidden" name="iso_id" id="delete-iso-id" value="">
            <div style="display:flex;gap:.3rem;justify-content:flex-end;">
                <button type="button" class="btn btn-sm btn-outline" onclick="closeDeleteModal()"><?= t('cancel') ?></button>
                <button type="submit" class="btn btn-sm btn-danger"><?= t('delete') ?></button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<style>
.iso-page-grid {
    display: grid;
    grid-template-columns: 320px 1fr;
    gap: 1rem;
    align-items: start;
}

.iso-upload-card { margin-bottom: 0; position: sticky; top: 64px; }
.iso-upload-head {
    display: flex;
    align-items: center;
    gap: .4rem;
    font-size: .85rem;
    font-weight: 600;
    margin-bottom: .85rem;
    color: var(--text);
}
.iso-upload-head svg { color: var(--accent); }

.iso-upload-btn { width: 100%; }

.iso-upload-hint {
    margin-top: .5rem;
    font-size: .6rem;
    color: var(--text-3);
    text-align: center;
}

.iso-drop-zone {
    border: 2px dashed var(--sep-bold);
    border-radius: var(--r);
    padding: .85rem .65rem;
    text-align: center;
    cursor: pointer;
    transition: all .2s;
    margin-bottom: .55rem;
}
.iso-drop-zone:hover,
.iso-drop-zone.drag-over { border-color: var(--accent); background: var(--accent-bg); }
.iso-drop-zone.has-file { border-color: var(--green); border-style: solid; background: var(--green-bg); }
.iso-drop-icon { color: var(--text-3); margin-bottom: .15rem; }
.iso-drop-zone.has-file .iso-drop-icon { display: none; }
.iso-drop-check { display: none; margin-bottom: .15rem; }
.iso-drop-zone.has-file .iso-drop-check { display: block; }
.iso-drop-title { font-size: .76rem; font-weight: 600; color: var(--text); }
.iso-drop-sub { font-size: .64rem; color: var(--text-2); margin-top: .05rem; }

.iso-progress-wrap { display: none; margin-bottom: .5rem; }
.iso-progress-header {
    display: flex;
    justify-content: space-between;
    font-size: .7rem;
    color: var(--text-2);
    margin-bottom: .25rem;
    font-weight: 500;
}
.iso-progress-track {
    height: 4px;
    background: var(--surface3);
    border-radius: 2px;
    overflow: hidden;
}
.iso-progress-fill {
    height: 100%;
    width: 0%;
    background: var(--accent);
    border-radius: 2px;
    transition: width .15s, background .3s;
}
.iso-progress-speed { font-size: .6rem; color: var(--text-3); margin-top: .15rem; }

.iso-list-card { margin-bottom: 0; padding: 0; overflow: hidden; }
.iso-list-head {
    padding: .55rem .85rem;
    border-bottom: 1px solid var(--sep);
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: .4rem;
}
.iso-list-title {
    display: flex;
    align-items: center;
    gap: .35rem;
    font-size: .82rem;
    font-weight: 600;
}
.iso-list-title svg { color: var(--text-3); }
.iso-count {
    background: var(--surface3);
    color: var(--text-2);
    font-size: .58rem;
    font-weight: 700;
    padding: .08rem .3rem;
    border-radius: var(--r-pill);
    min-width: 18px;
    text-align: center;
}
.iso-search-input {
    padding: .25rem .5rem;
    font-size: .73rem;
    border-radius: var(--r-sm);
    border: 1px solid var(--sep);
    background: var(--surface2);
    color: var(--text);
    width: 140px;
    font-family: var(--font);
}
.iso-search-input:focus {
    outline: none;
    border-color: var(--accent);
    box-shadow: 0 0 0 2px var(--accent-bg);
}
.iso-table-wrap { overflow-x: auto; }
.iso-name-cell { display: flex; align-items: flex-start; gap: .35rem; }
.iso-primary-name { font-weight: 500; font-size: .8rem; color: var(--text); line-height: 1.3; }
.iso-filename { font-size: .6rem; color: var(--text-3); font-family: var(--mono); margin-top: .02rem; }

.btn-spinner {
    display: inline-block;
    width: 13px; height: 13px;
    border: 2px solid rgba(255,255,255,.3);
    border-top-color: #fff;
    border-radius: 50%;
    animation: spin .6s linear infinite;
}
@keyframes spin { to { transform: rotate(360deg); } }

@media (max-width: 900px) {
    .iso-page-grid { grid-template-columns: 1fr; }
    .iso-upload-card { position: static; }
}
@media (max-width: 768px) {
    .hide-mobile { display: none !important; }
    .iso-search-input { width: 100px; }
}
</style>

<script>
const fileInput = document.getElementById('iso-file-input');
const dropZone = document.getElementById('iso-drop-zone');
const uploadBtn = document.getElementById('upload-btn');
const displayNameInput = document.getElementById('iso-display-name');

fileInput.addEventListener('change', function() {
    if (this.files.length > 0) handleFileSelected(this.files[0]);
});

dropZone.addEventListener('dragover', e => { e.preventDefault(); dropZone.classList.add('drag-over'); });
dropZone.addEventListener('dragleave', () => dropZone.classList.remove('drag-over'));
dropZone.addEventListener('drop', e => {
    e.preventDefault();
    dropZone.classList.remove('drag-over');
    if (e.dataTransfer.files.length > 0) {
        const dt = new DataTransfer();
        dt.items.add(e.dataTransfer.files[0]);
        fileInput.files = dt.files;
        handleFileSelected(e.dataTransfer.files[0]);
    }
});

function handleFileSelected(file) {
    document.getElementById('iso-drop-title').textContent = file.name;
    document.getElementById('iso-drop-sub').textContent = fmtSize(file.size);
    dropZone.classList.add('has-file');
    uploadBtn.disabled = false;
    if (!displayNameInput.value.trim()) {
        displayNameInput.value = file.name.replace(/\.[^.]+$/, '').replace(/[_-]+/g, ' ');
    }
}

function fmtSize(b) {
    if (b < 1024) return b + ' B';
    if (b < 1048576) return (b / 1024).toFixed(1) + ' KB';
    if (b < 1073741824) return (b / 1048576).toFixed(1) + ' MB';
    return (b / 1073741824).toFixed(2) + ' GB';
}

function fmtTime(s) {
    if (s < 60) return Math.ceil(s) + 's';
    return Math.floor(s / 60) + 'm ' + Math.ceil(s % 60) + 's';
}

document.getElementById('iso-upload-form').addEventListener('submit', function(e) {
    if (!fileInput.files.length) {
        e.preventDefault();
        showToast('error', '<?= addslashes(t('iso_err_nofile')) ?>');
        return;
    }

    const progressWrap = document.getElementById('upload-progress-wrap');
    const progressFill = document.getElementById('upload-progress-fill');
    const statusText = document.getElementById('upload-status-text');
    const percentText = document.getElementById('upload-percent');
    const speedText = document.getElementById('upload-speed-text');

    uploadBtn.disabled = true;
    uploadBtn.innerHTML = '<span class="btn-spinner"></span> <?= addslashes(t("iso_uploading")) ?>';
    progressWrap.style.display = 'block';
    progressFill.style.width = '0%';
    progressFill.style.background = 'var(--accent)';
    statusText.textContent = '<?= addslashes(t('iso_uploading')) ?>';
    percentText.textContent = '0%';
    speedText.textContent = '';

    let startTime = Date.now();
    const xhr = new XMLHttpRequest();

    xhr.upload.addEventListener('progress', function(ev) {
        if (ev.lengthComputable) {
            const pct = Math.round((ev.loaded / ev.total) * 100);
            progressFill.style.width = pct + '%';
            percentText.textContent = pct + '%';

            const elapsed = (Date.now() - startTime) / 1000;
            if (elapsed > 0.3) {
                const speed = ev.loaded / elapsed;
                const remaining = (ev.total - ev.loaded) / speed;
                speedText.textContent = fmtSize(Math.round(speed)) + '/s · ~' + fmtTime(remaining) + ' left';
            }

            if (pct >= 100) {
                statusText.textContent = '<?= addslashes(t('iso_processing')) ?>';
                progressFill.style.background = 'var(--orange)';
                speedText.textContent = '<?= addslashes(t('iso_large_wait')) ?>';
            }
        }
    });

    xhr.onload = function() {
        window.location.reload();
    };

    xhr.onerror = function() {
        showToast('error', '<?= addslashes(t('network_error')) ?>');
        resetUpload();
    };

    const formData = new FormData(this);
    xhr.open('POST', 'isos.php', true);
    xhr.send(formData);

    e.preventDefault();
});

function resetUpload() {
    uploadBtn.disabled = false;
    uploadBtn.innerHTML = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg> <?= addslashes(t('upload_iso_btn')) ?>';
    document.getElementById('upload-progress-wrap').style.display = 'none';
}

function filterIsos(q) {
    q = q.toLowerCase().trim();
    document.querySelectorAll('.iso-row').forEach(row => {
        const name = (row.getAttribute('data-name') || '');
        row.style.display = (!q || name.includes(q)) ? '' : 'none';
    });
}

let currentDeleteId = null;
let currentDeleteName = '';

function openDeleteModal(isoId, isoName) {
    currentDeleteId = isoId;
    currentDeleteName = isoName;
    document.getElementById('delete-iso-id').value = isoId;
    document.getElementById('delete-iso-name').textContent = isoName;
    document.getElementById('delete-iso-modal').style.display = 'flex';
}

function closeDeleteModal() {
    document.getElementById('delete-iso-modal').style.display = 'none';
    currentDeleteId = null;
}

document.getElementById('delete-iso-modal').addEventListener('click', function(e) {
    if (e.target === this) closeDeleteModal();
});

function filterIsos(q) {
    q = q.toLowerCase().trim();
    document.querySelectorAll('.iso-row').forEach(row => {
        const name = (row.getAttribute('data-name') || '');
        row.style.display = (!q || name.includes(q)) ? '' : 'none';
    });
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>