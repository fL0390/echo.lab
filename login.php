<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/lang.php';
require_once __DIR__ . '/includes/logger.php';
if (isLoggedIn()) { header('Location: index.php'); exit; }
$currentLang = getCurrentLang();
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login    = trim($_POST['login'] ?? '');
    $password = $_POST['password'] ?? '';
    if ($login===''||$password==='') { $error = t('login_err_required'); }
    else {
        $user = DB::findUserByLogin($login);
        if (!$user||!password_verify($password,$user['password'])) {
            $error = t('login_err_invalid');
            dlog('warn','Failed login',['login'=>$login]);
        } else {
            $_SESSION['user_id'] = $user['id'];
            dlog('info','Login',['user'=>$user['username'],'id'=>$user['id']]);
            if (!empty($_POST['remember'])) {
                setcookie('echo_remember', $user['username'], time() + 86400*30, '/', '', false, true);
            } else {
                setcookie('echo_remember', '', time()-3600, '/');
            }
            header('Location: index.php'); exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="<?= $currentLang ?>">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
    <title><?= t('sign_in') ?> — <?= APP_NAME ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .auth-wrapper{position:relative;overflow:hidden}
        .auth-wrapper::before{content:'';position:absolute;top:35%;left:50%;transform:translate(-50%,-50%);width:400px;height:400px;background:radial-gradient(circle,var(--accent-bg) 0%,transparent 70%);opacity:.6;pointer-events:none}
        .auth-box{animation:authUp .35s var(--spring,cubic-bezier(.16,1,.3,1)) both;position:relative}
        @keyframes authUp{from{opacity:0;transform:translateY(12px)}}
        .auth-head{display:flex;align-items:center;justify-content:center;margin-bottom:.15rem}
    </style>
    <script>(function(){const s=localStorage.getItem('theme'),d=window.matchMedia('(prefers-color-scheme: dark)').matches;if(s==='light'||(!s&&!d))document.documentElement.setAttribute('data-theme','light');else document.documentElement.setAttribute('data-theme','dark');})();</script>
</head>
<body>
<?php if (TESTING_MODE): ?><div class="testing-banner"><?= t('testing_mode') ?></div><?php endif; ?>
<div class="auth-wrapper">
    <div class="auth-box">
        <div class="auth-head">
            <h1 style="margin:0;"><?= APP_NAME ?></h1>
        </div>
        <p class="subtitle"><?= t('login_subtitle') ?></p>
        <?php if ($error): ?><div class="flash flash-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
        <form method="post" action="login.php">
            <div class="form-group"><label for="login"><?= t('login_username_label') ?></label><input type="text" id="login" name="login" value="<?= htmlspecialchars($_POST['login']??'') ?>" autofocus value="<?= htmlspecialchars($_COOKIE['echo_remember'] ?? '') ?>" placeholder="<?= htmlspecialchars(t('login_username_ph')) ?>"></div>
            <div class="form-group"><label for="password"><?= t('login_password_label') ?></label><input type="password" id="password" name="password" placeholder="••••••••"></div>
            <div style="display:flex;align-items:center;justify-content:space-between;margin:-.1rem 0 .7rem;">
                <label style="display:flex;align-items:center;gap:.38rem;font-size:.72rem;color:var(--text-2);cursor:pointer;font-weight:500;">
                    <input type="checkbox" name="remember" value="1" <?= !empty($_COOKIE['echo_remember']) ? 'checked' : '' ?> style="accent-color:var(--accent);width:13px;height:13px;"> <?= t('login_remember') ?>
                </label>
                <a href="forgot.php" style="font-size:.72rem;color:var(--accent);"><?= t('forgot_link') ?></a>
            </div>
            <button type="submit" class="btn"><?= t('login_btn') ?></button>
        </form>
        <a href="register.php" class="alt-link"><?= t('login_no_account') ?> <strong><?= t('register') ?></strong></a>
    </div>
</div>
<script>
function switchLang(lang) {
    fetch('?lang=' + lang, { redirect: 'manual' }).then(function() {
        document.querySelectorAll('[onclick*="switchLang"]').forEach(function(a) {
            a.className = a.getAttribute('onclick').includes("'" + lang + "'") ? 'lang-pill active' : 'lang-pill';
        });
        var form = document.querySelector('form');
        if (form) {
            var data = {};
            new FormData(form).forEach(function(v, k) { if (k !== 'password') data[k] = v; });
            sessionStorage.setItem('formData', JSON.stringify(data));
        }
        location.reload();
    });
}
(function() {
    var saved = sessionStorage.getItem('formData');
    if (!saved) return;
    sessionStorage.removeItem('formData');
    try {
        var data = JSON.parse(saved);
        Object.keys(data).forEach(function(k) {
            var el = document.querySelector('[name="' + k + '"]');
            if (el && el.type !== 'password' && el.type !== 'hidden') el.value = data[k];
        });
    } catch(e) {}
})();
</script>
</body></html>
