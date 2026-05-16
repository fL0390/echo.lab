<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/lang.php';
require_once __DIR__ . '/includes/mailer.php';

if (session_status() === PHP_SESSION_NONE) session_start();

if (!empty($_SESSION['user_id'])) { header('Location: index.php'); exit; }

$currentLang = getCurrentLang();
$sent  = false;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login = trim($_POST['login'] ?? '');
    if ($login === '') {
        $error = t('forgot_field_required');
    } else {
        $user = DB::findUserByLogin($login);
        if ($user && !empty($user['email'])) {
            $token   = bin2hex(random_bytes(32));
            $expires = date('Y-m-d H:i:s', time() + 3600);
            DB::setResetToken((int)$user['id'], $token, $expires);
            $link = APP_URL . '/reset.php?token=' . $token;
            sendResetEmail($user, $link, $currentLang);
        }
        $sent = true;
    }
}
?>
<!DOCTYPE html>
<html lang="<?= $currentLang ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title><?= t('forgot_title') ?> — <?= APP_NAME ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
    <script>(function(){const s=localStorage.getItem('theme'),d=window.matchMedia('(prefers-color-scheme: dark)').matches;if(s==='light'||(!s&&!d))document.documentElement.setAttribute('data-theme','light');else document.documentElement.setAttribute('data-theme','dark');})();</script>
</head>
<body>
<?php if (defined('TESTING_MODE') && TESTING_MODE): ?>
<div class="testing-banner"><?= t('testing_mode') ?></div>
<?php endif; ?>
<div class="auth-wrapper">
    <div class="auth-box">
        <div style="text-align:center;margin-bottom:.75rem;">
            <div style="width:36px;height:36px;background:var(--accent);border-radius:10px;display:inline-flex;align-items:center;justify-content:center;margin-bottom:.6rem;">
                <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.5" stroke-linecap="round"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
            </div>
            <h1><?= APP_NAME ?></h1>
        </div>
        <p class="subtitle"><?= t('forgot_subtitle') ?></p>

        <?php if ($sent): ?>
            <div class="flash flash-success">
                <?= t('forgot_sent_notice') ?>
            </div>
            <a href="login.php" class="btn" style="width:100%;margin-top:.5rem;"><?= t('back_to_login') ?></a>
        <?php else: ?>
            <?php if ($error): ?>
                <div class="flash flash-error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            <form method="post">
                <div class="form-group">
                    <label><?= t('forgot_field_label') ?></label>
                    <input type="text" name="login" autofocus
                           value="<?= htmlspecialchars($_POST['login'] ?? '') ?>"
                           placeholder="<?= htmlspecialchars(t('forgot_field_ph')) ?>">
                </div>
                <button type="submit" class="btn" style="width:100%;margin-top:.25rem;">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><rect x="2" y="5" width="20" height="14" rx="2"/><polyline points="2 7 12 13 22 7"/></svg>
                    <?= t('forgot_send_btn') ?>
                </button>
            </form>
            <a href="login.php" class="alt-link"><?= t('back_to_login') ?></a>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
