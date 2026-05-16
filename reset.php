<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/lang.php';
if (session_status() === PHP_SESSION_NONE) session_start();

$token = $_GET['token'] ?? '';
$error = '';
$success = '';

if (!$token) {
    header("Location: index.php");
    exit;
}

$user = DB::getUserByResetToken($token);

if (!$user || !$user['reset_expires'] || strtotime($user['reset_expires']) < time()) {
    $error = t('reset_invalid_token') ?: 'Invalid or expired token.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$error) {
    $pass1 = $_POST['pass1'] ?? '';
    $pass2 = $_POST['pass2'] ?? '';
    
    if (strlen($pass1) < 6) {
        $error = t('reset_pass_short') ?: 'Password must be at least 6 characters.';
    } elseif ($pass1 !== $pass2) {
        $error = t('reset_pass_mismatch') ?: 'Passwords do not match.';
    } else {
        DB::updatePassword((int)$user['id'], password_hash($pass1, PASSWORD_DEFAULT));
        DB::setResetToken((int)$user['id'], null, null);
        $success = t('reset_success') ?: 'Password set successfully. You can now login.';
    }
}

$currentLang = getCurrentLang();
?>
<!DOCTYPE html>
<html lang="<?= $currentLang ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title><?= t('reset_title') ?: 'Set Password' ?> — <?= APP_NAME ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
    <script>
    (function(){
        const s=localStorage.getItem('theme');
        const d=window.matchMedia('(prefers-color-scheme: dark)').matches;
        if(s==='light'||(!s&&!d)) document.documentElement.setAttribute('data-theme','light');
        else document.documentElement.setAttribute('data-theme','dark');
    })();
    </script>
</head>
<body style="display:flex;align-items:center;justify-content:center;height:100vh;background:var(--bg);">
    <div class="card" style="max-width:400px;width:100%;margin:1rem;">
        <div style="text-align:center;margin-bottom:1.5rem;">
            <div style="width:36px;height:36px;background:var(--accent);border-radius:10px;display:flex;align-items:center;justify-content:center;margin:0 auto .75rem;">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.5" stroke-linecap="round"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
            </div>
            <h2 style="margin-bottom:.25rem;"><?= t('reset_title') ?: 'Set Password' ?></h2>
            <p style="font-size:.82rem;color:var(--text-2);"><?= t('reset_desc') ?: 'Enter your new password below.' ?></p>
        </div>

        <?php if ($error): ?>
            <div style="padding:.75rem;background:rgba(244,63,94,.1);border:1px solid rgba(244,63,94,.2);border-radius:var(--r-sm);color:var(--red);font-size:.8rem;margin-bottom:1rem;text-align:center;">
                <?= htmlspecialchars($error) ?>
            </div>
            <?php if ($error === (t('reset_invalid_token') ?: 'Invalid or expired token.')): ?>
                <a href="login.php" class="btn btn-outline" style="width:100%;text-align:center;"><?= t('back_to_login') ?: 'Back to Login' ?></a>
            <?php endif; ?>
        <?php elseif ($success): ?>
            <div style="padding:.75rem;background:rgba(16,185,129,.1);border:1px solid rgba(16,185,129,.2);border-radius:var(--r-sm);color:var(--green);font-size:.8rem;margin-bottom:1rem;text-align:center;">
                <?= htmlspecialchars($success) ?>
            </div>
            <a href="login.php" class="btn btn-primary" style="width:100%;text-align:center;"><?= t('go_to_login') ?: 'Go to Login' ?></a>
        <?php else: ?>
            <form method="post">
                <div class="form-group">
                    <label><?= t('new_password') ?: 'New Password' ?></label>
                    <input type="password" name="pass1" required autofocus>
                </div>
                <div class="form-group" style="margin-bottom:1.5rem;">
                    <label><?= t('confirm_password') ?: 'Confirm Password' ?></label>
                    <input type="password" name="pass2" required>
                </div>
                <button type="submit" class="btn btn-primary" style="width:100%;"><?= t('save_password') ?: 'Save Password' ?></button>
            </form>
        <?php endif; ?>
    </div>
</body>
</html>
