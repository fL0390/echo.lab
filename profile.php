<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/lang.php';
require_once __DIR__ . '/includes/mailer.php';
requireLogin(); enforceBan();
$me = currentUser(); $myRole = (int)$me['role'];

if (!isset($_GET['id']) || $_GET['id'] === '') { header('Location: profile.php?id=' . $me['id']); exit; }
$profileUser = DB::findUserById((int)$_GET['id']);
if (!$profileUser) { setFlash('error', t('user_not_found')); header('Location: index.php'); exit; }

$isOwn = ((int)$profileUser['id'] === (int)$me['id']);
$isAdmin = ($myRole >= ROLE_ADMIN);
$canEdit = $isOwn || $isAdmin;
$tab = $_GET['tab'] ?? 'profile';
$redir = 'profile.php?id=' . $profileUser['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $targetId = (int)$profileUser['id'];
    $action = $_POST['action'];

    if ($action === 'update_profile' && $canEdit) {
        $changed = false;
        $newEmail = trim($_POST['email'] ?? '');
        if ($newEmail !== ($profileUser['email'] ?? '') && $newEmail !== '') {
            if (DB::emailExists($newEmail) && strtolower($newEmail) !== strtolower($profileUser['email'] ?? '')) {
                setFlash('error', t('profile_email_taken')); header("Location: $redir&tab=profile"); exit;
            }
            // Ruta a través de confirmación de correo electrónico (solo para el perfil propio, los administradores pueden cambiar directamente)
            if ($isOwn) {
                $token   = bin2hex(random_bytes(32));
                $expires = date('Y-m-d H:i:s', time() + 86400);
                DB::setPendingEmail($targetId, $newEmail, $token, $expires);
                $link = APP_URL . '/confirm_email.php?token=' . $token;
                $lang = getCurrentLang();
                sendEmailChangeConfirmation($profileUser, $newEmail, $link, $lang);
                setFlash('success', t('email_change_pending', ['email' => $newEmail]));
                header("Location: $redir&tab=profile"); exit;
            } else {
                // Si el admin cambia el correo de alguien no se confirma
                DB::updateProfile($targetId, $newEmail); $changed = true;
            }
        } elseif ($newEmail === '' && ($profileUser['email'] ?? '') !== '') {
            DB::updateProfile($targetId, ''); $changed = true;
        }
        $newUsername = trim($_POST['username'] ?? '');
        if ($isAdmin && !$isOwn && $newUsername !== '' && $newUsername !== $profileUser['username']) {
            if (strlen($newUsername) < 3 || strlen($newUsername) > 32 || !preg_match('/^[a-zA-Z0-9_]+$/', $newUsername)) {
                setFlash('error', t('profile_invalid_username')); header("Location: $redir&tab=profile"); exit;
            }
            if (isOffensiveUsername($newUsername)) { setFlash('error', t('profile_username_blocked')); header("Location: $redir&tab=profile"); exit; }
            if (DB::usernameExists($newUsername)) { setFlash('error', t('profile_username_taken')); header("Location: $redir&tab=profile"); exit; }
            DB::updateUsername($targetId, $newUsername); $changed = true;
        }
        if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
            $newAv = processAvatarUpload($_FILES['avatar'], $targetId, $profileUser['avatar'] ?? '');
            if ($newAv) { DB::updateAvatar($targetId, $newAv); $changed = true; }
            else { header("Location: $redir&tab=profile"); exit; }
        }
        if ($changed) setFlash('success', t('profile_updated'));
        header("Location: $redir&tab=profile"); exit;

    } elseif ($action === 'remove_avatar' && $canEdit) {
        $dir = __DIR__ . '/data/avatars';
        $old = $profileUser['avatar'] ?? '';
        if ($old && file_exists($dir.'/'.$old)) @unlink($dir.'/'.$old);
        DB::updateAvatar($targetId, '');
        setFlash('success', t('profile_avatar_removed'));
        header("Location: $redir&tab=profile"); exit;

    } elseif ($action === 'change_password' && $isOwn) {
        $cur = $_POST['current_pass'] ?? '';
        $new = $_POST['new_pass'] ?? '';
        $conf = $_POST['confirm_new'] ?? '';
        if (!password_verify($cur, $profileUser['password'])) { setFlash('error', t('security_err_wrong')); }
        elseif (strlen($new) < 4) { setFlash('error', t('security_err_short')); }
        elseif ($new !== $conf) { setFlash('error', t('security_err_match')); }
        else { DB::updatePassword($targetId, password_hash($new, PASSWORD_DEFAULT)); setFlash('success', t('security_updated')); }
        header("Location: $redir&tab=security"); exit;

    } elseif ($action === 'admin_send_reset' && $isAdmin && !$isOwn) {
        if (!empty($profileUser['email'])) {
            $tok = bin2hex(random_bytes(32));
            $exp = date('Y-m-d H:i:s', time() + 3600);
            DB::setResetToken($targetId, $tok, $exp);
            $link = APP_URL . '/reset.php?token=' . $tok;
            sendResetEmail($profileUser, $link, getCurrentLang());
            setFlash('success', t('admin_reset_sent', ['email' => $profileUser['email']]));
        }
        header("Location: $redir&tab=profile"); exit;
    }
}

$profileUser = DB::findUserById((int)$profileUser['id']);
$initial = strtoupper(substr($profileUser['username'], 0, 1));
$role = (int)$profileUser['role'];
$avatarUrl = !empty($profileUser['avatar']) ? 'data/avatars/' . $profileUser['avatar'] : '';

$pageTitle = $isOwn ? t('profile_title') : htmlspecialchars($profileUser['username']);
require_once __DIR__ . '/includes/header.php';
?>

<style>
.prof{max-width:560px;margin:0 auto;}
.prof-hero{display:flex;flex-direction:column;align-items:center;text-align:center;padding:1.75rem 1.25rem 1.25rem;}
.prof-av{width:80px;height:80px;border-radius:50%;object-fit:cover;}
.prof-av-init{width:80px;height:80px;border-radius:50%;background:var(--surface2);display:flex;align-items:center;justify-content:center;font-size:1.6rem;font-weight:600;color:var(--blue);}
.prof-name{font-size:1.15rem;font-weight:700;letter-spacing:-.03em;margin-top:.7rem;margin-bottom:.1rem;}
.prof-handle{font-size:.72rem;color:var(--text-3);font-family:var(--mono);}
.admin-editing-badge{display:inline-flex;align-items:center;gap:.25rem;background:var(--orange-bg);color:var(--orange);border-radius:var(--r-pill);padding:.12rem .5rem;font-size:.6rem;font-weight:600;margin-top:.35rem;}
.prof-tabs{display:flex;gap:.15rem;padding:.1rem .5rem;border-bottom:.5px solid var(--sep);}
.prof-tab{padding:.45rem .7rem;font-size:.72rem;font-weight:600;color:var(--text-3);background:none;border:none;border-radius:var(--r-sm) var(--r-sm) 0 0;cursor:pointer;transition:all .12s;font-family:var(--font);text-decoration:none;display:inline-flex;align-items:center;gap:.3rem;}
.prof-tab:hover{color:var(--text-2);background:rgba(255,255,255,.03);}
.prof-tab.active{color:var(--text);background:rgba(255,255,255,.06);}
.prof-tab svg{opacity:.5;}.prof-tab.active svg{opacity:.8;}
.tab-content{display:none;padding:.75rem .65rem;}
.tab-content.active{display:block;}
.prof-section{font-size:.6rem;color:var(--text-3);text-transform:uppercase;letter-spacing:.06em;font-weight:600;margin-bottom:.3rem;}
.prof-row{display:flex;align-items:center;gap:.6rem;padding:.55rem 0;border-bottom:.5px solid var(--sep);}
.prof-row:last-child{border-bottom:none;}
.prof-row-icon{flex-shrink:0;color:var(--text-3);}
.prof-row-content{flex:1;min-width:0;}
.prof-row-label{font-size:.6rem;color:var(--text-3);text-transform:uppercase;letter-spacing:.05em;font-weight:600;}
.prof-row-val{font-size:.82rem;color:var(--text);margin-top:.04rem;}
.prof-row-val.dim{color:var(--text-3);}
.edit-row{margin-bottom:.55rem;}
.edit-row label{display:block;font-size:.6rem;color:var(--text-3);text-transform:uppercase;letter-spacing:.05em;font-weight:600;margin-bottom:.12rem;}
.edit-row input[type="text"],.edit-row input[type="email"],.edit-row input[type="password"]{width:100%;padding:.42rem .6rem;background:rgba(255,255,255,.04);border:.5px solid var(--sep-bold);border-radius:var(--r-sm);color:var(--text);font-family:var(--font);font-size:.8rem;transition:all .2s;}
.edit-row input:focus{outline:none;border-color:var(--blue);box-shadow:0 0 0 2px var(--blue-bg);}
.prof-av-wrap { position: relative; display: flex; align-items: center; justify-content: center; cursor: pointer; border-radius: 50%; width: 84px; height: 84px; overflow: hidden; border: 2px solid var(--sep-bold); flex-shrink: 0; }
.prof-av-overlay { position: absolute; inset: 0; background: rgba(0,0,0,0.5); border-radius: 50%; display: flex; align-items: center; justify-content: center; opacity: 0; transition: opacity 0.2s; color: #fff; z-index: 1; }
.prof-av-wrap:hover .prof-av-overlay { opacity: 1; }
.prof-av-wrap .prof-av { width: 100%; height: 100%; border-radius: 50%; object-fit: cover; display: block; border: none; }
.prof-av-wrap .prof-av-init { width: 100%; height: 100%; border-radius: 50%; background: var(--surface2); display: flex; align-items: center; justify-content: center; font-size: 1.6rem; font-weight: 600; color: var(--blue); border: none; }
.file-label{display:inline-flex;align-items:center;gap:.3rem;padding:.25rem .55rem;border-radius:var(--r-pill);background:rgba(255,255,255,.04);border:.5px solid var(--sep-bold);color:var(--text-2);font-size:.68rem;font-weight:500;cursor:pointer;transition:all .12s;}
.file-label:hover{background:rgba(255,255,255,.07);color:var(--text);}
.remove-link{font-size:.65rem;color:var(--red);cursor:pointer;background:none;border:none;font-family:var(--font);font-weight:500;padding:0;}.remove-link:hover{text-decoration:underline;}
.security-note{font-size:.72rem;color:var(--text-3);margin-bottom:.65rem;}
@keyframes profIn{from{opacity:0;transform:translateY(8px);}}
.prof .card{animation:profIn .35s var(--spring) both;}
</style>

<div class="prof">
    <div class="card" style="padding:0;overflow:hidden;">
        <div class="prof-hero">
            <div class="prof-av-wrap" <?= $canEdit ? 'onclick="openPfpModal()"' : '' ?>>
                <?php if ($avatarUrl): ?>
                    <img src="<?= htmlspecialchars($avatarUrl) ?>" class="prof-av" alt="">
                <?php else: ?>
                    <div class="prof-av-init"><?= $initial ?></div>
                <?php endif; ?>
                <?php if ($canEdit): ?>
                <div class="prof-av-overlay">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/></svg>
                </div>
                <?php endif; ?>
            </div>
            <div class="prof-name"><?= htmlspecialchars($profileUser['username']) ?></div>
            <div class="prof-handle">@<?= htmlspecialchars(strtolower($profileUser['username'])) ?></div>
            <div style="margin-top:.4rem;"><span class="badge <?= roleBadgeClass($role) ?>"><?= getRoleName($role) ?></span></div>
            <?php if (!$isOwn && $isAdmin): ?>
                <div class="admin-editing-badge">
                    <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                    <?= t('admin_editing_badge') ?>
                </div>
                <?php if (!empty($profileUser['email'])): ?>
                <form method="post" style="margin-top:.5rem;">
                    <input type="hidden" name="action" value="admin_send_reset">
                    <input type="hidden" name="target_id" value="<?= (int)$profileUser['id'] ?>">
                    <button type="submit" class="btn btn-sm btn-outline" style="width:100%;gap:.4rem;">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                        <?= t('admin_send_reset') ?>
                    </button>
                </form>
                <?php endif; ?>
            <?php endif; ?>
        </div>

        <div class="prof-tabs">
            <a href="<?= $redir ?>&tab=profile" class="prof-tab<?= $tab==='profile'?' active':'' ?>">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="8" r="4"/><path d="M5.5 21a6.5 6.5 0 0 1 13 0"/></svg>
                <?= t('profile_tab_label') ?>
            </a>
            <?php if ($isOwn): ?>
            <a href="<?= $redir ?>&tab=security" class="prof-tab<?= $tab==='security'?' active':'' ?>">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                <?= t('security_tab_label') ?>
            </a>
            <?php endif; ?>
        </div>

        <div class="tab-content<?= $tab==='profile'?' active':'' ?>">
            <div class="prof-section"><?= t('profile_info_section') ?></div>
            <div class="prof-row">
                <svg class="prof-row-icon" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="2" y="5" width="20" height="14" rx="2"/><polyline points="2 7 12 13 22 7"/></svg>
                <div class="prof-row-content"><div class="prof-row-label"><?= t('profile_email_label') ?></div><div class="prof-row-val<?= !$profileUser['email']?' dim':'' ?>"><?= $profileUser['email']?htmlspecialchars($profileUser['email']):t('profile_email_not_set') ?></div><?php if (!empty($profileUser['pending_email'])): ?><div style="font-size:.62rem;color:var(--orange);margin-top:.2rem;">&#8987; <?= t('email_change_confirm_note') ?> &rarr; <?= htmlspecialchars($profileUser['pending_email']) ?></div><?php endif; ?></div>
            </div>
            <div class="prof-row">
                <svg class="prof-row-icon" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                <div class="prof-row-content"><div class="prof-row-label"><?= t('profile_joined_label') ?></div><div class="prof-row-val"><?= htmlspecialchars($profileUser['created_at'] ?? '—') ?></div></div>
            </div>

            <?php if ($canEdit): ?>
            <div style="margin-top:.85rem;">
                <div class="prof-section"><?= $isOwn ? t('profile_edit_section') : t('profile_edit_user') ?></div>
                <form method="post" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="update_profile">
                    <?php if ($isAdmin && !$isOwn): ?>
                    <div class="edit-row"><label><?= t('login_username_label') ?></label><input type="text" name="username" value="<?= htmlspecialchars($profileUser['username']) ?>"></div>
                    <?php endif; ?>
                    <div class="edit-row"><label><?= t('profile_email_label') ?></label><input type="email" name="email" value="<?= htmlspecialchars($profileUser['email'] ?? '') ?>" placeholder="email"></div>
                    <button type="submit" class="btn" style="width:100%;"><?= t('save_changes') ?></button>
                </form>
            </div>
            <?php endif; ?>
        </div>

        <?php if ($isOwn): ?>
        <div class="tab-content<?= $tab==='security'?' active':'' ?>">
            <div class="prof-section"><?= t('security_title') ?></div>
            <p class="security-note"><?= t('security_note') ?></p>
            <form method="post">
                <input type="hidden" name="action" value="change_password">
                <div class="edit-row"><label><?= t('security_current_pass') ?></label><input type="password" name="current_pass" required></div>
                <div class="edit-row"><label><?= t('security_new_pass') ?></label><input type="password" name="new_pass" required></div>
                <div class="edit-row"><label><?= t('security_confirm_pass') ?></label><input type="password" name="confirm_new" required></div>
                <button type="submit" class="btn" style="width:100%;"><?= t('security_btn') ?></button>
            </form>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php if ($canEdit): ?>
<div id="pfp-modal" class="modal-overlay" style="display:none;">
    <div class="card modal-card" style="max-width:320px;">
        <h2 style="font-size:1rem;margin-bottom:1rem;"><?= t('profile_pfp_title') ?></h2>
        <div style="display:flex;justify-content:center;margin-bottom:1.5rem;">
            <?php if ($avatarUrl): ?>
                <img src="<?= htmlspecialchars($avatarUrl) ?>" style="width:96px;height:96px;border-radius:50%;object-fit:cover;border:1px solid var(--sep-bold);" alt="">
            <?php else: ?>
                <div style="width:96px;height:96px;border-radius:50%;background:var(--surface2);display:flex;align-items:center;justify-content:center;font-size:2rem;font-weight:600;color:var(--blue);border:1px solid var(--sep-bold);"><?= $initial ?></div>
            <?php endif; ?>
        </div>
        <form method="post" enctype="multipart/form-data" id="pfp-form" style="display:flex;flex-direction:column;gap:.75rem;">
            <input type="hidden" name="action" value="update_profile">
            <label class="btn btn-outline" style="width:100%;cursor:pointer;justify-content:center;">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/></svg>
                Upload New Image
                <input type="file" name="avatar" accept="image/*" style="display:none;" onchange="document.getElementById('pfp-form').submit()">
            </label>
        </form>
        <?php if ($avatarUrl): ?>
        <form method="post" style="margin-top:.75rem;">
            <input type="hidden" name="action" value="remove_avatar">
            <button type="submit" class="btn btn-danger" style="width:100%;justify-content:center;">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><line x1="10" y1="11" x2="10" y2="17"/><line x1="14" y1="11" x2="14" y2="17"/></svg>
                Remove Current
            </button>
        </form>
        <?php endif; ?>
        <div style="margin-top:1.25rem;text-align:center;">
            <button type="button" class="btn btn-sm" style="background:transparent;color:var(--text-3);box-shadow:none;" onclick="closePfpModal()"><?= t('cancel') ?></button>
        </div>
    </div>
</div>
<script>
function openPfpModal() { document.getElementById('pfp-modal').style.display = 'flex'; }
function closePfpModal() { document.getElementById('pfp-modal').style.display = 'none'; }
document.getElementById('pfp-modal').addEventListener('click', e => { if (e.target === e.currentTarget) closePfpModal(); });
</script>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
