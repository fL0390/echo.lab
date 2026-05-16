<?php
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../lang.php';
$_currentUser = currentUser();
$_currentPage = basename($_SERVER['PHP_SELF'], '.php');
$_isBanned    = $_currentUser && isBanned();
$_inAdmin     = str_contains($_SERVER['PHP_SELF'], '/admin/');
$_prefix      = $_inAdmin ? '../' : '';
$_lang        = getCurrentLang();

function roleBadgeClass(int $role): string {
    return match($role) {
        ROLE_BANNED       => 'badge-banned',
        ROLE_USER         => 'badge-user',
        ROLE_STUDENT      => 'badge-student',
        ROLE_TEACHER      => 'badge-teacher',
        ROLE_CENTER_ADMIN => 'badge-center',
        ROLE_ADMIN        => 'badge-admin',
        default           => 'badge-user',
    };
}
$_initial    = $_currentUser ? strtoupper(substr($_currentUser['username'], 0, 1)) : '';
$_avatarPath = '';
if ($_currentUser && !empty($_currentUser['avatar'])) {
    $_avatarPath = $_prefix . 'data/avatars/' . $_currentUser['avatar'];
}
?>
<!DOCTYPE html>
<html lang="<?= $_lang ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle ?? APP_NAME) ?></title>
    <link rel="stylesheet" href="<?= $_prefix ?>assets/css/style.css">
    <script>
        (function() {
            const s = localStorage.getItem('theme');
            const d = window.matchMedia('(prefers-color-scheme: dark)').matches;
            if (s === 'light' || (!s && !d)) document.documentElement.setAttribute('data-theme','light');
            else document.documentElement.setAttribute('data-theme','dark');
        })();
        function setTheme(t) {
    var html = document.documentElement;
    html.classList.add('theme-switching');
    html.setAttribute('data-theme', t);
    localStorage.setItem('theme', t);
    setTimeout(function(){ html.classList.remove('theme-switching'); }, 220);
}
    </script>
</head>
<body>
<?php if (TESTING_MODE): ?>
    <div class="testing-banner"><?= t('testing_mode') ?></div>
<?php endif; ?>
<header class="site-header">
    <a href="<?= $_prefix ?>index.php" class="logo"><?= APP_NAME ?></a>
    <?php if ($_currentUser && !$_isBanned): ?>
    <nav>
        <a href="<?= $_prefix ?>index.php" class="<?= $_currentPage==='index'&&!$_inAdmin?'active':'' ?>"><?= t('nav_dashboard') ?></a>
            <?php if ($_currentUser && (int)$_currentUser['role'] >= ROLE_TEACHER): ?>
            <a href="<?= $_prefix ?>docs.php" class="<?= $_currentPage==='docs'?'active':'' ?>"><?= t('nav_docs') ?></a>
            <?php endif; ?>
        <?php if (can('admin_panel')): ?>
            <a href="<?= $_inAdmin?'index.php':'admin/index.php' ?>" class="<?= $_inAdmin?'active':'' ?>"><?= t('nav_admin') ?></a>
        <?php endif; ?>
    </nav>
    <?php else: ?><nav></nav><?php endif; ?>
    <div class="header-right">
        <?php if ($_currentUser): ?>
        <div class="user-menu-wrap" id="userMenuWrap">
            <button class="user-menu-trigger" id="userMenuBtn" onclick="toggleUserMenu()">
                <?php if ($_avatarPath): ?>
                    <img src="<?= htmlspecialchars($_avatarPath) ?>" class="trigger-avatar" alt="">
                <?php else: ?>
                    <span class="trigger-initial"><?= $_initial ?></span>
                <?php endif; ?>
                <span class="trigger-name"><?= htmlspecialchars($_currentUser['username']) ?></span>
                <svg class="trigger-chevron" width="10" height="10" viewBox="0 0 10 10" fill="none"><path d="M2.5 3.75L5 6.25L7.5 3.75" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/></svg>
            </button>
            <div class="user-menu" id="userMenu">
                <div class="user-menu-header">
                    <?php if ($_avatarPath): ?><img src="<?= htmlspecialchars($_avatarPath) ?>" class="menu-avatar" alt="">
                    <?php else: ?><span class="menu-initial"><?= $_initial ?></span><?php endif; ?>
                    <div>
                        <div class="menu-name"><?= htmlspecialchars($_currentUser['username']) ?></div>
                        <div class="menu-role"><span class="badge <?= roleBadgeClass((int)$_currentUser['role']) ?>"><?= getRoleName((int)$_currentUser['role']) ?></span></div>
                    </div>
                </div>
                <div class="menu-sep"></div>
                <!-- Cambiar idioma -->
                <div style="display:flex;align-items:center;justify-content:space-between;padding:.28rem .5rem;">
                    <span style="font-size:.65rem;color:var(--text-3);font-weight:600;letter-spacing:.04em;text-transform:uppercase;"><?= t('language_label') ?></span>
                    <div style="display:flex;gap:2px;background:var(--surface2);border:1px solid var(--sep-bold);border-radius:var(--r-pill);padding:2px;">
                        <a href="?lang=en" class="lang-pill <?= $_lang==='en'?'active':'' ?>">EN</a>
                        <a href="?lang=es" class="lang-pill <?= $_lang==='es'?'active':'' ?>">ES</a>
                    </div>
                </div>
                <div class="menu-sep"></div>
                <div class="theme-toggle-wrap">
                    <button class="theme-btn light-btn" onclick="setTheme('light')"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/></svg><?= t('light') ?></button>
                    <button class="theme-btn dark-btn" onclick="setTheme('dark')"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg><?= t('dark') ?></button>
                </div>
                <div class="menu-sep"></div>
                <a href="<?= $_prefix ?>profile.php?id=<?= $_currentUser['id'] ?>" class="menu-item"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="4"/><path d="M5.5 21a6.5 6.5 0 0 1 13 0"/></svg><?= t('profile') ?></a>
                <?php if (can('admin_panel')): ?>
                <a href="<?= $_inAdmin?'index.php':'admin/index.php' ?>" class="menu-item"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg><?= t('administration') ?></a>
                <?php endif; ?>
                <div class="menu-sep"></div>
                <a href="<?= $_prefix ?>logout.php" class="menu-item menu-item-danger"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg><?= t('sign_out') ?></a>
            </div>
        </div>
        <?php else: ?>
            <a href="<?= $_prefix ?>login.php" class="user-menu-trigger" style="text-decoration:none;">
                <span class="trigger-initial" style="background:var(--surface2);color:var(--text-3);"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="8" r="4"/><path d="M5.5 21a6.5 6.5 0 0 1 13 0"/></svg></span>
                <span class="trigger-name"><?= t('sign_in') ?></span>
                <svg class="trigger-chevron" width="10" height="10" viewBox="0 0 10 10" fill="none"><path d="M3 4l2 2.5L7 4" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/></svg>
            </a>
        <?php endif; ?>
    </div>
</header>
<div id="toast-container"></div>
<script>
(function(){
    let open=false;
    const btn=document.getElementById('userMenuBtn');
    const menu=document.getElementById('userMenu');
    if(!btn||!menu)return;
    window.toggleUserMenu=function(){open=!open;btn.classList.toggle('open',open);menu.classList.toggle('open',open);};
    document.addEventListener('click',function(e){if(open&&!document.getElementById('userMenuWrap').contains(e.target)){open=false;btn.classList.remove('open');menu.classList.remove('open');}});
    document.addEventListener('keydown',function(e){if(e.key==='Escape'&&open){open=false;btn.classList.remove('open');menu.classList.remove('open');}});
})();
function showToast(type,msg){
    const c=document.getElementById('toast-container');
    const icons={success:'<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><polyline points="20 6 9 17 4 12"/></svg>',error:'<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>',warning:'<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>'};
    const t=document.createElement('div');
    t.className=`toast toast-${type}`;
    t.innerHTML=`<span class="toast-icon">${icons[type]||icons.success}</span><span class="toast-msg">${msg.replace(/</g,'&lt;')}</span><button class="toast-close" onclick="this.parentElement.classList.add('removing');setTimeout(()=>this.parentElement.remove(),220)">&#x2715;</button>`;
    c.appendChild(t);
    setTimeout(()=>{t.classList.add('removing');setTimeout(()=>t.remove(),220);},4200);
}
</script>
<main class="main-content<?= !empty($wideLayout) ? ' wide' : '' ?>">
<?php $flash=getFlash(); if($flash): ?>
<script>document.addEventListener('DOMContentLoaded',()=>showToast('<?= htmlspecialchars($flash['type']) ?>','<?= htmlspecialchars(addslashes($flash['message'])) ?>'));</script>
<?php endif; ?>
