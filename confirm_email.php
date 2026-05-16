<?php
/**
 * echo — Confirmación de cambio de email
 * El usuario hace click en el enlace enviado a su NUEVO correo para confirmar el cambio.
 */
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/lang.php';

if (session_status() === PHP_SESSION_NONE) session_start();

$currentLang = getCurrentLang();
$token  = $_GET['token'] ?? '';
$error  = '';
$success = '';

if (!$token) { header('Location: index.php'); exit; }

$user = DB::getUserByEmailConfirmToken($token);

if (!$user) {
    $error = t('email_confirm_invalid');
} elseif (!empty($user['email_confirm_expires']) && strtotime($user['email_confirm_expires']) < time()) {
    // Limpia el token expirado
    DB::clearPendingEmail((int)$user['id']);
    $error = t('email_confirm_expired');
} else {
    $newEmail = $user['pending_email'] ?? '';
    if (!$newEmail) {
        $error = t('email_confirm_invalid');
    } else {
        DB::confirmEmailChange((int)$user['id'], $newEmail);
        // Actualiza la sesión si este es el usuario logueado
        if (!empty($_SESSION['user_id']) && (int)$_SESSION['user_id'] === (int)$user['id']) {
            $_SESSION['email'] = $newEmail;
        }
        $success = t('email_confirm_success');
    }
}
?>
<!DOCTYPE html>
<html lang="<?= $currentLang ?>">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
    <title><?= t('email_confirm_title') ?> — <?= APP_NAME ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
    <script>(function(){const s=localStorage.getItem('theme'),d=window.matchMedia('(prefers-color-scheme: dark)').matches;if(s==='light'||(!s&&!d))document.documentElement.setAttribute('data-theme','light');else document.documentElement.setAttribute('data-theme','dark');})();</script>
</head>
<body style="display:flex;align-items:center;justify-content:center;min-height:100vh;background:var(--bg);">
<div class="card" style="max-width:420px;width:100%;margin:1rem;text-align:center;">
    <div style="margin-bottom:1.25rem;">
        <div style="width:42px;height:42px;border-radius:50%;background:<?= $error ? 'var(--red-bg)' : 'var(--green-bg)' ?>;display:inline-flex;align-items:center;justify-content:center;margin-bottom:.75rem;">
            <?php if ($error): ?>
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="var(--red)" stroke-width="2.2" stroke-linecap="round"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
            <?php else: ?>
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="var(--green)" stroke-width="2.2" stroke-linecap="round"><polyline points="20 6 9 17 4 12"/></svg>
            <?php endif; ?>
        </div>
        <h2 style="font-size:1.05rem;margin-bottom:.3rem;"><?= t('email_confirm_title') ?></h2>
        <p style="font-size:.82rem;color:<?= $error ? 'var(--red)' : 'var(--green)' ?>;">
            <?= htmlspecialchars($error ?: $success) ?>
        </p>
    </div>
    <a href="<?= !empty($_SESSION['user_id']) ? 'profile.php?id=' . (int)$_SESSION['user_id'] . '&tab=profile' : 'login.php' ?>"
       class="btn <?= $error ? 'btn-outline' : 'btn-primary' ?>" style="width:100%;">
        <?= !empty($_SESSION['user_id']) ? t('profile_title') : t('back_to_login') ?>
    </a>
</div>
</body>
</html>
