<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/lang.php';
if (isLoggedIn()) { header('Location: index.php'); exit; }
$currentLang = getCurrentLang();
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $pass     = $_POST['password'] ?? '';
    $confirm  = $_POST['confirm'] ?? '';
    if ($username===''||$pass==='')           { $error = t('register_err_required'); }
    elseif (strlen($username)<3||strlen($username)>32) { $error = t('register_err_length'); }
    elseif (!preg_match('/^[a-zA-Z0-9_]+$/',$username)) { $error = t('register_err_chars'); }
    elseif (isOffensiveUsername($username))   { $error = t('register_err_offensive'); }
    elseif (strlen($pass)<4)                  { $error = t('register_err_pass_short'); }
    elseif ($pass!==$confirm)                 { $error = t('register_err_pass_match'); }
    elseif (DB::usernameExists($username))    { $error = t('register_err_user_taken'); }
    elseif ($email!==''&&DB::emailExists($email)) { $error = t('register_err_email_taken'); }
    else {
        DB::createUser($username,$email,password_hash($pass,PASSWORD_DEFAULT));
        $user = DB::findUserByLogin($username);
        $_SESSION['user_id'] = $user['id'];
        header('Location: index.php'); exit;
    }
}
?>
<!DOCTYPE html>
<html lang="<?= $currentLang ?>">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
    <title><?= t('register_title') ?> — <?= APP_NAME ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .auth-wrapper{position:relative;overflow:hidden}
        .auth-wrapper::before{content:'';position:absolute;top:35%;left:50%;transform:translate(-50%,-50%);width:400px;height:400px;background:radial-gradient(circle,var(--accent-bg) 0%,transparent 70%);opacity:.6;pointer-events:none}
        .auth-box{animation:authUp .35s var(--spring,cubic-bezier(.16,1,.3,1)) both;position:relative}
        @keyframes authUp{from{opacity:0;transform:translateY(12px)}}
        .auth-head{display:flex;align-items:center;justify-content:center;margin-bottom:.15rem}
        .auth-lang{display:flex;gap:2px;background:var(--surface2);border:1px solid var(--sep-bold);border-radius:var(--r-pill);padding:2px}
        .auth-lang a{padding:.22rem .58rem;font-size:.63rem;font-weight:700;letter-spacing:.07em;text-transform:uppercase;border-radius:var(--r-pill);color:var(--text-3);text-decoration:none;transition:all .15s;line-height:1}
        .auth-lang a.on,.auth-lang a:hover{background:var(--accent);color:#fff}
        .auth-lang-row{display:flex;align-items:center;gap:.5rem;margin:.1rem 0 .6rem}
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
        <p class="subtitle"><?= t('register_subtitle') ?></p>
        <?php if ($error): ?><div class="flash flash-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
        <form method="post" action="register.php">
            <div class="form-group"><label for="username"><?= t('register_username_label') ?></label><input type="text" id="username" name="username" value="<?= htmlspecialchars($_POST['username']??'') ?>" autofocus></div>
            <div class="form-group"><label for="email"><?= t('register_email_label') ?> <span style="color:var(--text-3);text-transform:none;font-weight:400;"><?= t('register_email_optional') ?></span></label><input type="email" id="email" name="email" value="<?= htmlspecialchars($_POST['email']??'') ?>"></div>
            <div class="form-group"><label for="password"><?= t('register_password_label') ?></label><input type="password" id="password" name="password"></div>
            <div class="form-group"><label for="confirm"><?= t('register_confirm_label') ?></label><input type="password" id="confirm" name="confirm"></div>
            <div class="auth-lang-row">
                <span style="font-size:.68rem;color:var(--text-3);"><?= t('language_label') ?>:</span>
                <div class="auth-lang">
                    <a href="#" onclick="switchLang('en');return false;" class="<?= $currentLang==='en'?'on':'' ?>">EN</a>
                    <a href="#" onclick="switchLang('es');return false;" class="<?= $currentLang==='es'?'on':'' ?>">ES</a>
                </div>
            </div>
            <button type="submit" class="btn"><?= t('register_btn') ?></button>
        </form>
        <a href="login.php" class="alt-link"><?= t('register_have_account') ?> <strong><?= t('sign_in') ?></strong></a>
    </div>
</div>
<script>
function switchLang(lang) {
    fetch('?lang=' + lang, { redirect: 'manual' }).then(function() {
        document.querySelectorAll('[onclick*="switchLang"]').forEach(function(a) {
            a.className = a.getAttribute('onclick').includes("'" + lang + "'") ? 'on' : '';
        });
        var form = document.querySelector('form');
        if (form) {
            var data = {};
            new FormData(form).forEach(function(v, k) { data[k] = v; });
            sessionStorage.setItem('formData', JSON.stringify(data));
        }
        location.reload();
    });
}
// Vuelve a poner datos antes del cambio de idioma
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
