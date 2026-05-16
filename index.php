<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/lang.php';
require_once __DIR__ . '/includes/proxmox.php';
$user        = currentUser();
$currentLang = getCurrentLang();

// Cuenta suspendida
if ($user && isBanned()) {
    $pageTitle = t('account_suspended');
    require_once __DIR__ . '/includes/header.php';
    $banMessage = $user['ban_message'] ?? '';
    $bannedBy   = !empty($user['banned_by']) ? DB::findUserById((int)$user['banned_by']) : null;
    ?>
    <div style="max-width:400px;margin:5rem auto;">
        <div class="card" style="text-align:center;">
            <h2 style="color:var(--red);margin-bottom:.5rem;"><?= t('account_suspended') ?></h2>
            <?php if ($banMessage): ?><p style="font-size:.82rem;color:var(--text-2);margin-bottom:.5rem;"><?= nl2br(htmlspecialchars($banMessage)) ?></p><?php endif; ?>
            <?php if ($bannedBy): ?><p style="font-size:.7rem;color:var(--text-3);"><?= t('suspended_by', ['name' => $bannedBy['username']]) ?></p><?php endif; ?>
            <p style="font-size:.78rem;color:var(--text-3);margin-top:.75rem;"><?= t('suspended_contact') ?></p>
            <a href="logout.php" class="btn btn-danger btn-sm" style="margin-top:1rem;"><?= t('sign_out') ?></a>
        </div>
    </div>
    <?php
    require_once __DIR__ . '/includes/footer.php';
    exit;
}

// Página de inicio (no conectado)
if (!$user): ?>
<!DOCTYPE html>
<html lang="<?= $currentLang ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= APP_NAME ?> — <?= t('app_tagline') ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Geist:wght@300;400;500;600;700;800;900&family=Geist+Mono:wght@400;500&display=swap" rel="stylesheet">
<script>
(function(){
  var s=localStorage.getItem('theme'),d=window.matchMedia('(prefers-color-scheme:dark)').matches;
  if(s==='light'||(!s&&!d))document.documentElement.setAttribute('data-theme','light');
  else document.documentElement.setAttribute('data-theme','dark');
})();
function setTheme(t){document.documentElement.setAttribute('data-theme',t);localStorage.setItem('theme',t);}
</script>
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
html{-webkit-font-smoothing:antialiased;scroll-behavior:smooth}
:root,[data-theme="dark"]{
  --bg:#000;--s1:#0a0a0a;--s2:#111;--s3:#1a1a1a;
  --sep:rgba(255,255,255,.07);--sep2:rgba(255,255,255,.12);--sep3:rgba(255,255,255,.18);
  --t:#ededed;--t2:rgba(237,237,237,.55);--t3:rgba(237,237,237,.3);--t4:rgba(237,237,237,.12);
  --pur:#ededed;--pur-d:rgba(237,237,237,.07);--pur-b:rgba(237,237,237,.14);
  --grn:#4ade80;--grn-d:rgba(74,222,128,.1);
  --r:8px;--r-sm:5px;--r-lg:12px;--r-pill:999px;
  --font:'Geist',system-ui,sans-serif;--mono:'Geist Mono',ui-monospace,monospace;
  --spring:cubic-bezier(.16,1,.3,1);
  --sh-xl:0 20px 60px rgba(0,0,0,.8),0 0 0 1px var(--sep2);
}
[data-theme="light"]{
  --bg:#fff;--s1:#fafafa;--s2:#f4f4f4;--s3:#ebebeb;
  --sep:rgba(0,0,0,.07);--sep2:rgba(0,0,0,.12);--sep3:rgba(0,0,0,.18);
  --t:#0a0a0a;--t2:rgba(10,10,10,.55);--t3:rgba(10,10,10,.35);--t4:rgba(10,10,10,.12);
  --pur:#0a0a0a;--pur-d:rgba(0,0,0,.05);--pur-b:rgba(0,0,0,.12);
  --grn:#16a34a;--grn-d:rgba(22,163,74,.08);
  --sh-xl:0 20px 60px rgba(0,0,0,.1),0 0 0 1px var(--sep2);
}
body{font-family:var(--font);background:var(--bg);color:var(--t);line-height:1.5;overflow-x:hidden}
::selection{background:var(--t);color:var(--bg)}
a{color:inherit;text-decoration:none}
button{font-family:var(--font)}
body::after{content:'';position:fixed;inset:0;pointer-events:none;z-index:998;opacity:.5;background-image:url("data:image/svg+xml,%3Csvg viewBox='0 0 200 200' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='.85' numOctaves='4' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)' opacity='.05'/%3E%3C/svg%3E")}
[data-theme="light"] body::after{opacity:.2}

/* ── Navegación ── */
.nav{position:fixed;top:0;inset-inline:0;z-index:200;height:54px;display:flex;align-items:center;justify-content:space-between;padding:0 1.5rem;border-bottom:1px solid var(--sep);background:rgba(0,0,0,.78);backdrop-filter:blur(20px);-webkit-backdrop-filter:blur(20px)}
[data-theme="light"] .nav{background:rgba(255,255,255,.82)}
.nav-logo{display:flex;align-items:center;gap:.45rem;font-size:.78rem;font-weight:700;letter-spacing:.1em;text-transform:uppercase;color:var(--t)}
.logo-sq{width:22px;height:22px;border-radius:4px;background:var(--t);display:flex;align-items:center;justify-content:center;flex-shrink:0}
.logo-sq svg{color:#fff}
.nav-mid{display:flex;gap:1px}
.nav-a{padding:.3rem .6rem;font-size:.77rem;color:var(--t3);border-radius:5px;transition:all .12s;font-weight:450}
.nav-a:hover{color:var(--t);background:rgba(255,255,255,.05)}
[data-theme="light"] .nav-a:hover{background:rgba(0,0,0,.05)}
.nav-r{display:flex;align-items:center;gap:.38rem}
.tbtn{width:28px;height:28px;border-radius:5px;border:1px solid var(--sep2);background:transparent;color:var(--t3);cursor:pointer;display:flex;align-items:center;justify-content:center;transition:all .12s}
.tbtn:hover{color:var(--t);background:var(--s2)}
.tbtn .sun{display:none}.tbtn .moon{display:block}
[data-theme="light"] .tbtn .sun{display:block}[data-theme="light"] .tbtn .moon{display:none}
.nb{display:inline-flex;align-items:center;gap:.3rem;padding:.32rem .8rem;border-radius:5px;font-size:.77rem;font-weight:500;cursor:pointer;border:1px solid var(--sep2);background:transparent;color:var(--t2);transition:all .13s;text-decoration:none;white-space:nowrap}
.nb:hover{color:var(--t);border-color:var(--sep3);background:var(--s2)}
.nb-p{background:var(--t);color:var(--bg);border-color:var(--t)}
.nb-p:hover{background:var(--t2);border-color:var(--t2);color:var(--bg)}

/* ── Héroe ── */
.hero{min-height:100vh;display:flex;flex-direction:column;align-items:center;justify-content:center;text-align:center;padding:8rem 1.5rem 5rem;position:relative;overflow:hidden}
.hero::before{content:'';position:absolute;top:0;left:50%;transform:translateX(-50%);width:900px;height:700px;background:radial-gradient(ellipse at 50% 0%,rgba(237,237,237,.04) 0%,transparent 65%);pointer-events:none}
[data-theme="light"] .hero::before{background:radial-gradient(ellipse at 50% 0%,rgba(0,0,0,.03) 0%,transparent 65%)}
.hero::after{content:'';position:absolute;top:0;left:0;right:0;height:1px;background:linear-gradient(90deg,transparent 10%,rgba(237,237,237,.18) 50%,transparent 90%)}
.hero-grid{position:absolute;inset:0;background-image:linear-gradient(var(--sep) 1px,transparent 1px),linear-gradient(90deg,var(--sep) 1px,transparent 1px);background-size:60px 60px;mask-image:radial-gradient(ellipse 90% 70% at 50% 30%,black 20%,transparent 100%);-webkit-mask-image:radial-gradient(ellipse 90% 70% at 50% 30%,black 20%,transparent 100%);pointer-events:none}

.badge{display:inline-flex;align-items:center;gap:.4rem;font-size:.63rem;font-weight:600;letter-spacing:.12em;text-transform:uppercase;color:var(--pur);background:var(--pur-d);border:1px solid var(--pur-b);padding:.26rem .72rem;border-radius:var(--r-pill);margin-bottom:1.8rem;animation:rise .5s var(--spring) both}
.bdot{width:5px;height:5px;background:var(--pur);border-radius:50%;animation:pulse 2.5s infinite}
@keyframes pulse{0%,100%{opacity:1}50%{opacity:.35}}

h1.hh{font-size:clamp(3rem,7.5vw,5.8rem);font-weight:800;line-height:1.03;letter-spacing:-.055em;margin-bottom:1.4rem;max-width:900px;animation:rise .5s .06s var(--spring) both}
.hh .acc{color:var(--t2);-webkit-text-fill-color:unset;background:none}
[data-theme="light"] .hh .acc{color:var(--t2);-webkit-text-fill-color:unset;background:none}

.hsub{font-size:clamp(.88rem,1.9vw,1.05rem);color:var(--t2);line-height:1.78;max-width:540px;margin-bottom:2.5rem;font-weight:400;animation:rise .5s .13s var(--spring) both}

.hctas{display:flex;gap:.5rem;justify-content:center;flex-wrap:wrap;margin-bottom:3.5rem;animation:rise .5s .19s var(--spring) both}
.cta-p{display:inline-flex;align-items:center;gap:.38rem;padding:.58rem 1.5rem;background:var(--t);color:var(--bg);border-radius:5px;font-size:.82rem;font-weight:600;border:none;cursor:pointer;transition:all .16s;text-decoration:none;font-family:var(--font)}
.cta-p:hover{opacity:.88;transform:translateY(-1px)}
.cta-s{display:inline-flex;align-items:center;gap:.38rem;padding:.58rem 1.3rem;background:transparent;color:var(--t2);border:1px solid var(--sep2);border-radius:5px;font-size:.82rem;font-weight:500;cursor:pointer;transition:all .16s;text-decoration:none;font-family:var(--font)}
.cta-s:hover{color:var(--t);border-color:var(--sep3);background:var(--s2)}

@keyframes rise{from{opacity:0;transform:translateY(16px)}to{opacity:1;transform:none}}

.proof{display:flex;align-items:center;gap:.6rem;justify-content:center;font-size:.71rem;color:var(--t3);margin-bottom:3rem;animation:rise .5s .25s var(--spring) both;flex-wrap:wrap}
.pavs{display:flex}
.pav{width:24px;height:24px;border-radius:50%;border:2px solid var(--bg);display:flex;align-items:center;justify-content:center;font-size:.55rem;font-weight:700}
.pav:not(:first-child){margin-left:-6px}
.pstars{display:flex;gap:1px;color:#facc15}

/* ── Constructor de VM ── */
.vmb{width:100%;max-width:480px;background:var(--s1);border:1px solid var(--sep2);border-radius:var(--r-lg);overflow:hidden;box-shadow:var(--sh-xl);animation:rise .6s .3s var(--spring) both;position:relative;z-index:1;text-align:left}
.vmb-head{display:flex;align-items:center;justify-content:space-between;padding:1.1rem 1.4rem .7rem;border-bottom:1px solid var(--sep)}
.vmb-title{display:flex;align-items:center;gap:.4rem;font-size:.95rem;font-weight:600;color:var(--t)}
.vmb-title svg{color:var(--pur)}
.vmb-close{width:26px;height:26px;border-radius:5px;border:1px solid var(--sep2);background:transparent;color:var(--t3);cursor:pointer;display:flex;align-items:center;justify-content:center;transition:all .12s}
.vmb-close:hover{color:var(--t);background:var(--s2)}
.vmb-body{padding:1.1rem 1.4rem 1.2rem}
.vmb-field{margin-bottom:1rem}
.vmb-field-lbl{display:block;font-size:.72rem;font-weight:500;color:var(--t2);margin-bottom:.3rem}
.vmb-input{width:100%;padding:.5rem .7rem;background:var(--bg);border:1px solid var(--sep2);border-radius:var(--r-sm);color:var(--t);font-family:var(--font);font-size:.82rem;outline:none;transition:border-color .14s}
.vmb-input:focus{border-color:var(--pur)}
select.vmb-input{appearance:none;-webkit-appearance:none;background-image:url("data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' width='10' height='10' viewBox='0 0 24 24' fill='none' stroke='%23888' stroke-width='2.5' stroke-linecap='round'><polyline points='6 9 12 15 18 9'/></svg>");background-repeat:no-repeat;background-position:right .7rem center;padding-right:1.8rem}
.vmb-preset-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:.4rem}
.vmb-preset{border:1.5px solid var(--sep2);border-radius:var(--r-sm);padding:.55rem .45rem;cursor:pointer;text-align:center;transition:all .12s;background:transparent}
.vmb-preset:hover{border-color:var(--pur)}
.vmb-preset.on{border-color:var(--pur);background:var(--pur-d)}
.vmb-preset-name{font-size:.72rem;font-weight:600;color:var(--t)}
.vmb-preset-specs{font-size:.6rem;color:var(--t3);margin-top:.15rem}
.vmb-hint{font-size:.62rem;color:var(--t3);margin-top:.35rem}
.vmb-foot{display:flex;align-items:center;justify-content:flex-end;gap:.35rem;padding:0 1.4rem 1.1rem}
.vmb-btn-ghost{display:inline-flex;align-items:center;padding:.42rem .9rem;background:transparent;color:var(--t2);border:1px solid var(--sep2);border-radius:var(--r-sm);font-size:.78rem;font-weight:500;cursor:pointer;transition:all .14s;font-family:var(--font)}
.vmb-btn-ghost:hover{color:var(--t);border-color:var(--sep3);background:var(--s2)}
.vmb-btn{display:inline-flex;align-items:center;gap:.3rem;padding:.42rem 1rem;background:var(--t);color:var(--bg);border-radius:var(--r-sm);font-size:.78rem;font-weight:600;border:none;cursor:pointer;transition:all .14s;text-decoration:none;font-family:var(--font)}
.vmb-btn:hover{opacity:.85;transform:translateY(-1px)}

/* ── Diseño ── */
.w{max-width:1080px;margin:0 auto;padding:5rem 1.5rem}
.div{max-width:1080px;margin:0 auto;height:1px;background:linear-gradient(90deg,transparent,var(--sep),transparent)}
.ey{font-size:.61rem;font-weight:700;letter-spacing:.17em;text-transform:uppercase;color:var(--pur);margin-bottom:.55rem}
h2.sh{font-size:clamp(1.65rem,3.5vw,2.5rem);font-weight:800;letter-spacing:-.04em;line-height:1.07;margin-bottom:.7rem}
.ssub{font-size:.87rem;color:var(--t2);line-height:1.72;max-width:480px;margin-bottom:2.8rem}

/* ── Estadísticas ── */
.stats{display:grid;grid-template-columns:repeat(4,1fr);border:1px solid var(--sep);border-radius:var(--r-lg);overflow:hidden}
.stat{padding:1.6rem 1rem;text-align:center;position:relative}
.stat:not(:last-child)::after{content:'';position:absolute;top:15%;right:0;bottom:15%;width:1px;background:var(--sep)}
.stat-n{font-size:2rem;font-weight:800;letter-spacing:-.04em;margin-bottom:.18rem;font-family:var(--mono)}
.stat-l{font-size:.68rem;color:var(--t3);text-transform:uppercase;letter-spacing:.09em;font-weight:500}

/* ── Características ── */
.fg{display:grid;grid-template-columns:repeat(3,1fr);border:1px solid var(--sep);border-radius:var(--r-lg);overflow:hidden}
.fc{padding:1.5rem;position:relative;transition:background .16s}
.fc:hover{background:var(--s2)}
.fc:not(:nth-child(3n))::after{content:'';position:absolute;top:0;right:0;bottom:0;width:1px;background:var(--sep)}
.fc:not(:nth-child(n+4))::before{content:'';position:absolute;bottom:0;left:0;right:0;height:1px;background:var(--sep)}
.fi{width:32px;height:32px;border-radius:6px;display:flex;align-items:center;justify-content:center;margin-bottom:.85rem;background:var(--s2);border:1px solid var(--sep2);color:var(--t2)}
.ft{font-size:.83rem;font-weight:650;margin-bottom:.32rem;letter-spacing:-.01em}
.fd{font-size:.75rem;color:var(--t3);line-height:1.64}

/* ── API ── */
.api-grid{display:grid;grid-template-columns:1fr 1fr;gap:1.5rem;align-items:start}
.api-copy .ssub{margin-bottom:1.5rem}
.api-endpoints{display:flex;flex-direction:column;gap:.45rem}
.ep{display:flex;align-items:center;gap:.7rem;padding:.7rem .9rem;background:var(--s1);border:1px solid var(--sep);border-radius:var(--r);transition:border-color .14s}
.ep:hover{border-color:var(--sep3)}
.ep-method{font-size:.6rem;font-weight:700;font-family:var(--mono);padding:.1rem .38rem;border-radius:4px;flex-shrink:0;letter-spacing:.04em}
.get{background:rgba(74,222,128,.1);color:#4ade80;border:1px solid rgba(74,222,128,.2)}
.post{background:rgba(96,165,250,.1);color:#60a5fa;border:1px solid rgba(96,165,250,.2)}
.del{background:rgba(244,63,94,.1);color:#f43f5e;border:1px solid rgba(244,63,94,.2)}
.ep-path{font-family:var(--mono);font-size:.76rem;color:var(--t);flex:1}
.ep-desc{font-size:.68rem;color:var(--t3)}
.code-pill{display:inline-flex;align-items:center;gap:.35rem;font-size:.72rem;font-family:var(--mono);color:var(--pur);background:var(--pur-d);border:1px solid var(--pur-b);padding:.22rem .65rem;border-radius:var(--r-pill);margin-bottom:1rem}

/* ── Pasos ── */
.steps{display:grid;grid-template-columns:repeat(3,1fr);border:1px solid var(--sep);border-radius:var(--r-lg);overflow:hidden}
.sc{padding:1.7rem 1.4rem;position:relative;transition:background .16s}
.sc:hover{background:var(--s2)}
.sc:not(:last-child)::after{content:'';position:absolute;top:0;right:0;bottom:0;width:1px;background:var(--sep)}
.sn{font-size:2.2rem;font-weight:800;letter-spacing:-.05em;color:var(--sep3);line-height:1;margin-bottom:.85rem;font-family:var(--mono)}
.st{font-size:.85rem;font-weight:650;margin-bottom:.38rem;letter-spacing:-.01em}
.sd{font-size:.75rem;color:var(--t3);line-height:1.65}

/* ── Reseñas ── */
.rg{display:grid;grid-template-columns:repeat(3,1fr);gap:1px;background:var(--sep);border:1px solid var(--sep);border-radius:var(--r-lg);overflow:hidden}
.carousel-wrap{position:relative}
.carousel{display:flex;gap:1rem;transition:transform .45s cubic-bezier(.16,1,.3,1);will-change:transform}
.carousel .rc{flex:0 0 calc(33.333% - .67rem);background:var(--s1);border:1px solid var(--sep);border-radius:var(--r-lg);padding:1.4rem;transition:border-color .16s}
.carousel .rc:hover{border-color:var(--sep3)}
.carousel-dots{display:flex;gap:.4rem;justify-content:center;margin-top:1.25rem}
.cdot{width:6px;height:6px;border-radius:var(--r-pill);background:var(--sep3);border:none;cursor:pointer;padding:0;transition:all .2s}
.cdot.on{width:18px;background:var(--t)}
@media(max-width:860px){.carousel .rc{flex:0 0 calc(50% - .5rem)}}
@media(max-width:560px){.carousel .rc{flex:0 0 100%}}
.rc{background:var(--s1);padding:1.4rem;transition:background .16s}
.rc:hover{background:var(--s2)}
.rstars{display:flex;gap:1px;margin-bottom:.7rem}
.rstars svg{color:#facc15}
.rt{font-size:.8rem;color:var(--t2);line-height:1.7;margin-bottom:.9rem;font-style:italic}
.rt::before{content:open-quote}.rt::after{content:close-quote}
.ra{display:flex;align-items:center;gap:.55rem}
.rav{width:28px;height:28px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:.6rem;font-weight:700;flex-shrink:0;border:1px solid var(--sep2)}
.rn{font-size:.76rem;font-weight:600;margin-bottom:.06rem}
.rr{font-size:.66rem;color:var(--t3)}

/* ── CTA ── */
.cta{background:var(--t);color:var(--bg);padding:4.5rem 1.5rem;text-align:center;position:relative;overflow:hidden}
[data-theme="light"] .cta{background:#0a0a0a;color:#ededed}
.cta::before{content:'';position:absolute;inset:0;background-image:url("data:image/svg+xml,%3Csvg width='40' height='40' viewBox='0 0 40 40' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='%23ffffff' fill-opacity='0.025'%3E%3Cpath d='M0 0h20v20H0zm20 20h20v20H20z'/%3E%3C/g%3E%3C/svg%3E")}
.cta h2{font-size:clamp(1.7rem,4vw,2.7rem);font-weight:800;letter-spacing:-.04em;margin-bottom:.6rem;position:relative}
.cta p{font-size:.87rem;opacity:.6;margin-bottom:1.85rem;position:relative}
.cta-btns{display:flex;gap:.5rem;justify-content:center;flex-wrap:wrap;position:relative}
.cw{display:inline-flex;align-items:center;gap:.35rem;padding:.58rem 1.45rem;background:#fff;color:#000;border-radius:5px;font-size:.82rem;font-weight:600;border:none;cursor:pointer;transition:all .15s;text-decoration:none;font-family:var(--font)}
[data-theme="light"] .cw{background:#ededed;color:#0a0a0a}
.cw:hover{opacity:.88;transform:translateY(-1px)}
.cow{display:inline-flex;align-items:center;gap:.35rem;padding:.58rem 1.2rem;background:transparent;color:rgba(255,255,255,.65);border:1px solid rgba(255,255,255,.2);border-radius:5px;font-size:.82rem;font-weight:500;cursor:pointer;transition:all .15s;text-decoration:none;font-family:var(--font)}
.cow:hover{color:#fff;border-color:rgba(255,255,255,.4);background:rgba(255,255,255,.07)}

/* ── Pie de página ── */
footer{border-top:1px solid var(--sep);padding:1.4rem 2rem;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:.6rem}
.fl{font-size:.7rem;color:var(--t3);display:flex;align-items:center;gap:.45rem}
.fl strong{font-weight:700;letter-spacing:.08em;text-transform:uppercase;font-size:.68rem;color:var(--t2)}
.fr{font-size:.68rem;color:var(--t3)}

/* ── Responsivo ── */
@media(max-width:860px){
  .fg{grid-template-columns:1fr 1fr}
  .fc:nth-child(2n)::after{display:none}
  .stats{grid-template-columns:repeat(2,1fr)}
  .rg{grid-template-columns:1fr}
  .api-grid{grid-template-columns:1fr}
  .nav-mid{display:none}
}
@media(max-width:580px){
  .nav{padding:0 1rem}
  .hero{padding:5.5rem 1rem 3rem}
  h1.hh{font-size:2.6rem;letter-spacing:-.04em}
  .vmb-preset-grid{grid-template-columns:1fr}
  .fg{grid-template-columns:1fr}
  .fc::after,.fc::before{display:none}
  .steps{grid-template-columns:1fr}
  .sc::after{display:none}
  footer{flex-direction:column;text-align:center}
  .stats{grid-template-columns:1fr 1fr}
  .nb:not(.nb-p){display:none}
}
</style>
</head>
<body>

<!-- Navegación -->
<nav class="nav">
  <a href="index.php" class="nav-logo">
    <span class="logo-sq"><svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.8" stroke-linecap="round"><rect x="2" y="3" width="20" height="14" rx="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg></span>
    <?= APP_NAME ?>
  </a>
  <div class="nav-mid">
    <a href="#features" class="nav-a"><?= t('landing_features_label') ?></a>
    <a href="#api" class="nav-a">API</a>
    <a href="#how" class="nav-a"><?= t('landing_how_label') ?></a>
  </div>
  <div class="nav-r">
    <button class="tbtn" onclick="setTheme(document.documentElement.getAttribute('data-theme')==='dark'?'light':'dark')">
      <svg class="sun" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/></svg>
      <svg class="moon" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
    </button>
    <a href="login.php" class="nb"><?= t('sign_in') ?></a>
    <a href="register.php" class="nb nb-p"><?= t('landing_cta_start') ?></a>
  </div>
</nav>

<!-- Héroe -->
<div class="hero">
  <div class="hero-grid"></div>
  <div class="badge"><span class="bdot"></span><?= t('landing_eyebrow') ?></div>
  <h1 class="hh"><?= t('landing_title_1') ?><br><span class="acc"><?= t('landing_title_2') ?></span></h1>
  <p class="hsub"><?= t('landing_subtitle') ?></p>
  <div class="hctas">
    <a href="register.php" class="cta-p">
      <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
      <?= t('landing_cta_start') ?>
    </a>
    <a href="#api" class="cta-s">
      <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><polyline points="16 18 22 12 16 6"/><polyline points="8 6 2 12 8 18"/></svg>
      <?= t('landing_view_api') ?>
    </a>
  </div>
  <div class="proof">
    <div class="pavs">
      <div class="pav" style="background:#7c3aed;color:#fff">AL</div>
      <div class="pav" style="background:#0891b2;color:#fff">MR</div>
      <div class="pav" style="background:#059669;color:#fff">JD</div>
      <div class="pav" style="background:#b45309;color:#fff">PG</div>
    </div>
    <div class="pstars"><?php for($i=0;$i<5;$i++): ?><svg width="11" height="11" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg><?php endfor; ?></div>
    <span><?= t('landing_proof_text') ?></span>
  </div>
  <div class="vmb">
    <div class="vmb-head">
      <div class="vmb-title">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        <?= t('create_vm_title') ?>
      </div>
      <button type="button" class="vmb-close" aria-label="<?= htmlspecialchars(t('cancel')) ?>">
        <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
      </button>
    </div>
    <div class="vmb-body">
      <div class="vmb-field">
        <label class="vmb-field-lbl"><?= t('vm_name_label') ?></label>
        <input class="vmb-input" type="text" id="vname" placeholder="<?= htmlspecialchars(t('vm_name_placeholder')) ?>">
      </div>
      <div class="vmb-field">
        <label class="vmb-field-lbl"><?= t('boot_iso_label') ?></label>
        <select class="vmb-input" id="iso-select">
          <option value="Ubuntu 24.04">Ubuntu 24.04</option>
          <option value="Debian 12">Debian 12</option>
          <option value="Windows Server 2022">Windows Server 2022</option>
          <option value="Arch Linux">Arch Linux</option>
        </select>
      </div>
      <div class="vmb-field" style="margin-bottom:.2rem;">
        <label class="vmb-field-lbl"><?= t('vm_preset_label') ?></label>
        <div class="vmb-preset-grid">
          <div class="vmb-preset on" onclick="selPreset(this)">
            <div class="vmb-preset-name"><?= t('preset_basic') ?></div>
            <div class="vmb-preset-specs">2 GB · 2 CPU · 20 GB</div>
          </div>
          <div class="vmb-preset" onclick="selPreset(this)">
            <div class="vmb-preset-name"><?= t('preset_standard') ?></div>
            <div class="vmb-preset-specs">4 GB · 2 CPU · 40 GB</div>
          </div>
          <div class="vmb-preset" onclick="selPreset(this)">
            <div class="vmb-preset-name"><?= t('preset_performance') ?></div>
            <div class="vmb-preset-specs">8 GB · 4 CPU · 80 GB</div>
          </div>
        </div>
        <div class="vmb-hint"><?= t('vm_preset_hint') ?></div>
      </div>
    </div>
    <div class="vmb-foot">
      <button type="button" class="vmb-btn-ghost"><?= t('cancel') ?></button>
      <a href="register.php" class="vmb-btn">
        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        <?= t('create_vm_btn') ?>
      </a>
    </div>
  </div>
</div>

<div class="div"></div>

<!-- Estadísticas -->
<div class="w" style="padding-top:3rem;padding-bottom:3rem;">
  <div class="stats">
    <div class="stat"><div class="stat-n">∞</div><div class="stat-l"><?= t('landing_stat_vms') ?></div></div>
    <div class="stat"><div class="stat-n">REST</div><div class="stat-l"><?= t('landing_stat_api') ?></div></div>
    <div class="stat"><div class="stat-n">PVE</div><div class="stat-l"><?= t('landing_stat_backend') ?></div></div>
    <div class="stat"><div class="stat-n">0</div><div class="stat-l"><?= t('landing_stat_install') ?></div></div>
  </div>
</div>

<div class="div"></div>

<!-- Características -->
<section class="w" id="features">
  <div class="ey"><?= t('landing_features_label') ?></div>
  <h2 class="sh"><?= t('landing_features_title') ?></h2>
  <p class="ssub"><?= t('landing_subtitle') ?></p>
  <div class="fg">
    <?php
    $feats=[
      ['feat_deploy_title','feat_deploy_desc','<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><path d="M13 2L3 14h9l-1 8 10-12h-9l1-8z"/></svg>'],
      ['feat_templates_title','feat_templates_desc','<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/></svg>'],
      ['feat_groups_title','feat_groups_desc','<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>'],
      ['feat_isos_title','feat_isos_desc','<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><circle cx="12" cy="12" r="10"/><circle cx="12" cy="12" r="3"/></svg>'],
      ['feat_roles_title','feat_roles_desc','<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>'],
      ['feat_logs_title','feat_logs_desc','<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>'],
    ];
    foreach($feats as [$t,$d,$ic]):
    ?>
    <div class="fc"><div class="fi"><?= $ic ?></div><div class="ft"><?= t($t) ?></div><div class="fd"><?= t($d) ?></div></div>
    <?php endforeach; ?>
  </div>
</section>

<div class="div"></div>

<!-- API -->
<section class="w" id="api">
  <div class="api-grid">
    <div class="api-copy">
      <div class="ey">REST API</div>
      <h2 class="sh"><?= t('landing_api_title') ?></h2>
      <p class="ssub"><?= t('landing_api_desc') ?></p>
      <div class="code-pill">
        <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><polyline points="16 18 22 12 16 6"/><polyline points="8 6 2 12 8 18"/></svg>
        <?= t('landing_api_token_hint') ?>
      </div>
      <a href="register.php" class="cta-p" style="font-size:.78rem;padding:.48rem 1.1rem;"><?= t('landing_get_started') ?><svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg></a>
    </div>
    <div class="api-endpoints">
      <?php
      $eps=[
        ['GET','/api/me.php','landing_ep_me'],
        ['GET','/api/vms.php','landing_ep_vms'],
        ['POST','/api/vm_status.php','landing_ep_status'],
        ['POST','/api/courses.php?action=create_template','landing_ep_template'],
        ['POST','/api/courses.php?action=assign_student','landing_ep_assign'],
        ['POST','/api/courses.php?action=assign_course','landing_ep_batch'],
      ];
      foreach($eps as [$m,$p,$dk]):
      $cls=['GET'=>'get','POST'=>'post','DELETE'=>'del'][$m];
      ?>
      <div class="ep">
        <span class="ep-method <?= $cls ?>"><?= $m ?></span>
        <span class="ep-path"><?= htmlspecialchars($p) ?></span>
        <span class="ep-desc"><?= t($dk) ?></span>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<div class="div"></div>

<!-- Cómo funciona -->
<section class="w" id="how">
  <div class="ey"><?= t('landing_how_label') ?></div>
  <h2 class="sh"><?= t('landing_how_title') ?></h2>
  <div class="steps">
    <div class="sc"><div class="sn">01</div><div class="st"><?= t('how_step1_title') ?></div><div class="sd"><?= t('how_step1_desc') ?></div></div>
    <div class="sc"><div class="sn">02</div><div class="st"><?= t('how_step2_title') ?></div><div class="sd"><?= t('how_step2_desc') ?></div></div>
    <div class="sc"><div class="sn">03</div><div class="st"><?= t('how_step3_title') ?></div><div class="sd"><?= t('how_step3_desc') ?></div></div>
  </div>
</section>

<div class="div"></div>

<!-- Reseñas -->
<section class="w" style="overflow:hidden;">
  <div class="ey"><?= t('landing_reviews_label') ?></div>
  <h2 class="sh" style="margin-bottom:2rem;"><?= t('landing_reviews_title') ?></h2>
  <?php
  $reviews=[
    ['landing_review_1_text','landing_review_1_name','landing_review_1_role','AL','#555'],
    ['landing_review_2_text','landing_review_2_name','landing_review_2_role','MR','#444'],
    ['landing_review_3_text','landing_review_3_name','landing_review_3_role','JD','#333'],
    ['landing_review_4_text','landing_review_4_name','landing_review_4_role','PG','#666'],
    ['landing_review_5_text','landing_review_5_name','landing_review_5_role','SL','#555'],
  ];
  ?>
  <div class="carousel-wrap">
    <div class="carousel" id="carousel">
      <?php foreach($reviews as [$rt,$rn,$rr,$ri,$rc]): ?>
      <div class="rc">
        <div class="rstars"><?php for($i=0;$i<5;$i++): ?><svg width="11" height="11" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg><?php endfor; ?></div>
        <div class="rt"><?= t($rt) ?></div>
        <div class="ra"><div class="rav" style="background:var(--s3);color:var(--t2)"><?= $ri ?></div><div><div class="rn"><?= t($rn) ?></div><div class="rr"><?= t($rr) ?></div></div></div>
      </div>
      <?php endforeach; ?>
    </div>
    <div class="carousel-dots" id="cdots"></div>
  </div>
</section>

<!-- CTA -->
<div class="cta">
  <h2><?= t('landing_cta_title') ?></h2>
  <p><?= t('landing_cta_body') ?></p>
  <div class="cta-btns">
    <a href="register.php" class="cw"><?= t('landing_cta_start') ?><svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg></a>
    <a href="login.php" class="cow"><?= t('sign_in') ?></a>
  </div>
</div>

<footer>
  <div class="fl">
    <span class="logo-sq" style="width:18px;height:18px;border-radius:3px;background:var(--pur)"><svg width="9" height="9" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.8" stroke-linecap="round"><rect x="2" y="3" width="20" height="14" rx="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg></span>
    <strong><?= APP_NAME ?></strong>
    <span><?= t('footer_tagline') ?></span>
  </div>
  <div class="fr">&copy; <?= date('Y') ?> <?= APP_NAME ?>. <?= t('footer_rights') ?></div>
</footer>

<script>
function selPreset(card){
  document.querySelectorAll('.vmb-preset').forEach(function(c){c.classList.remove('on')});
  card.classList.add('on');
}
var io=new IntersectionObserver(function(e){e.forEach(function(x){if(x.isIntersecting){x.target.style.opacity='1';x.target.style.transform='none';io.unobserve(x.target)}})},{threshold:.1});
document.querySelectorAll('.fg,.steps,.rg,.stats,.api-endpoints,.api-copy').forEach(function(el){
  el.style.opacity='0';el.style.transform='translateY(18px)';
  el.style.transition='opacity .55s ease, transform .55s ease';
  io.observe(el);
});

// Carrusel de reseñas
(function(){
  var car = document.getElementById('carousel');
  var dotsWrap = document.getElementById('cdots');
  if(!car) return;
  var cards = car.querySelectorAll('.rc');
  var perView = window.innerWidth <= 560 ? 1 : window.innerWidth <= 860 ? 2 : 3;
  var total = cards.length;
  var pages = Math.ceil(total / perView);
  var cur = 0;
  for(var i=0;i<pages;i++){
    var d = document.createElement('button');
    d.className = 'cdot' + (i===0?' on':'');
    d.dataset.i = i;
    d.onclick = function(){ go(+this.dataset.i); };
    dotsWrap.appendChild(d);
  }
  function go(n){
    cur = (n + pages) % pages;
    var cardW = cards[0].getBoundingClientRect().width + 16;
    car.style.transform = 'translateX(-' + (cur * perView * cardW) + 'px)';
    dotsWrap.querySelectorAll('.cdot').forEach(function(d,i){ d.classList.toggle('on', i===cur); });
  }
  var timer = setInterval(function(){ go(cur+1); }, 4000);
  car.parentElement.addEventListener('mouseenter', function(){ clearInterval(timer); });
  car.parentElement.addEventListener('mouseleave', function(){ timer = setInterval(function(){ go(cur+1); }, 4000); });
  var tx = 0;
  car.addEventListener('touchstart', function(e){ tx = e.touches[0].clientX; }, {passive:true});
  car.addEventListener('touchend', function(e){ var dx = tx - e.changedTouches[0].clientX; if(Math.abs(dx)>40) go(cur+(dx>0?1:-1)); });
})();
</script>
</body>
</html>
<?php
exit;
endif;

// Panel (conectado)
$myRole    = (int)$user['role'];
$myId      = (int)$user['id'];
$myCenterId = $user['center_id'] ?? null;
$layoutPref = $user['layout_pref'] ?? 'list';

if (isset($_GET['layout']) && in_array($_GET['layout'], ['list', 'grid'])) {
    DB::updateLayoutPref($myId, $_GET['layout']);
    header('Location: index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'create_vm' && $myRole >= ROLE_TEACHER) {
        $vmName      = trim($_POST['vm_name'] ?? '');
        $ram         = (int)($_POST['ram_mb'] ?? 2048);
        $cpuCores    = (int)($_POST['cpu_cores'] ?? 2);
        $diskGb      = (int)($_POST['disk_gb'] ?? 20);
        $assignScope = $_POST['assign_scope'] ?? 'personal';
        $assignCenter = $_POST['assign_center'] ?? null;
        $isoVolid    = trim($_POST['iso_volid'] ?? '');
        $isoId       = (int)($_POST['iso_id'] ?? 0);

        $pve = getProxmox();

        if ($vmName === '') {
            setFlash('error', t('err_vm_name_required'));
            header('Location: index.php'); exit;
        }

        // Modo Proxmox
        if ($pve && $isoVolid) {
            $result = $pve->createAndStart($vmName, max(512,min($ram,65536)), max(1,min($cpuCores,32)), max(5,min($diskGb,2000)), $isoVolid, PROXMOX_STORAGE);
            if ($result['ok']) {
                $echoVm = DB::createVm(['name'=>$vmName,'iso_id'=>0,'iso_name'=>basename($isoVolid),'os_type'=>basename($isoVolid),'ram_mb'=>$ram,'cpu_cores'=>$cpuCores,'disk_gb'=>$diskGb,'assign_scope'=>$assignScope,'assign_center_id'=>$assignScope==='center'?($assignCenter?:$myCenterId):null,'assigned_students'=>[],'created_by'=>$myId,'node_key'=>null,'proxmox_vmid'=>$result['vmid'],'proxmox_node'=>PROXMOX_NODE,'status'=>'running']);
                setFlash('success', t('success_vm_created', ['name'=>$vmName]).' — Proxmox VMID: '.$result['vmid']);
            } else {
                setFlash('error', 'Proxmox error: '.$result['error']);
            }
            header('Location: index.php'); exit;
        }

        // Modo local
        elseif ($isoId > 0) {
            $iso = DB::getIsoById($isoId);
            if (!$iso) { setFlash('error', t('err_iso_not_found')); }
            else {
                DB::createVm(['name'=>$vmName,'iso_id'=>$isoId,'iso_name'=>$iso['name'],'os_type'=>$iso['os_type'],'ram_mb'=>max(512,min($ram,65536)),'cpu_cores'=>max(1,min($cpuCores,32)),'disk_gb'=>max(5,min($diskGb,2000)),'assign_scope'=>$assignScope,'assign_center_id'=>$assignScope==='center'?($assignCenter?:$myCenterId):null,'assigned_students'=>[],'created_by'=>$myId,'node_key'=>null]);
                setFlash('success', t('success_vm_created', ['name'=>$vmName]));
            }
            header('Location: index.php'); exit;
        } else {
            setFlash('error', t('err_select_iso'));
            header('Location: index.php'); exit;
        }
    }
    if ($_POST['action'] === 'delete_vm' && $myRole >= ROLE_TEACHER) {
        $vmId = (int)$_POST['vm_id'];
        $deleteProxmox = !empty($_POST['delete_proxmox']);
        $vm = DB::getVmById($vmId);
        if ($vm) {
            if ($deleteProxmox && !empty($vm['proxmox_vmid'])) {
                $pve = getProxmox();
                if ($pve) {
                    $pve->deleteVM((int)$vm['proxmox_vmid']);
                }
            }
            DB::deleteVm($vmId);
            setFlash('success', t('success_vm_deleted'));
        }
        header('Location: index.php'); exit;
    }
}

$canCreateVm   = ($myRole >= ROLE_TEACHER);
$isos          = $canCreateVm ? DB::getProxmoxIsosForUser($myId, $myRole, $myCenterId) : [];
$proxmoxIsos   = [];
$proxmoxEnabled = defined('PROXMOX_ENABLED') && PROXMOX_ENABLED;
$pveLoad       = $proxmoxEnabled ? getProxmox() : null;
if ($pveLoad && $canCreateVm) {
    $isoStorage  = defined('PROXMOX_ISO_STORAGE') ? PROXMOX_ISO_STORAGE : 'local';
    $proxmoxIsos = $pveLoad->getISOs($isoStorage);
    $proxyPort = defined('PROXMOX_PROXY_PORT') ? PROXMOX_PROXY_PORT : 8081;
    $pveLoad->writeProxyConfig(__DIR__ . '/proxy_config.json', $proxyPort);
}
$vms = DB::getVms($myId, $myRole, $myCenterId);
DB::stopInactiveVms(60);
$isStudent = ($myRole < ROLE_TEACHER);
if ($isStudent) {
    $displayVms = array_values(array_filter($vms, function($v) use ($myId) {
        return !empty($v['template_id']) && (int)($v['assigned_to'] ?? 0) === $myId;
    }));
} else {
    $displayVms = array_values(array_filter($vms, function($v) {
        return empty($v['template_id']);
    }));
}
$centers     = ($myRole >= ROLE_ADMIN) ? DB::getCenters() : [];
$pageTitle   = t('dashboard');
require_once __DIR__ . '/includes/header.php';
?>

<?php $proxmoxEnabled = defined('PROXMOX_ENABLED') && PROXMOX_ENABLED; ?>
<div class="dashboard-greeting">
    <h1><?= t('welcome_back', ['name' => htmlspecialchars($user['username'])]) ?></h1>
</div>

<div class="dash-bar">
    <div class="dash-bar-left">
        <span class="stat-chip"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="3" width="20" height="14" rx="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg><?= count($vms) ?> VM<?= count($vms)!==1?'s':'' ?></span>
        <?php if ($canCreateVm): ?><span class="stat-chip"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><circle cx="12" cy="12" r="3"/></svg><?= count($isos) ?> ISO<?= count($isos)!==1?'s':'' ?></span><?php endif; ?>
        <span class="stat-chip" style="color:var(--text-3);"><?= getRoleName($myRole) ?></span>
        <?php if ($proxmoxEnabled): ?>
        <span class="stat-chip" style="color:var(--green);border-color:rgba(16,185,129,.2);">
            <svg width="9" height="9" viewBox="0 0 24 24" fill="currentColor"><circle cx="12" cy="12" r="10"/></svg>
            Proxmox
        </span>
        <?php else: ?>
        <span class="stat-chip" style="color:var(--text-3);border-color:var(--sep);" title="Enable in config.php">
            <svg width="9" height="9" viewBox="0 0 24 24" fill="currentColor"><circle cx="12" cy="12" r="10"/></svg>
            No Proxmox
        </span>
        <?php endif; ?>
    </div>
    <div style="display:flex;gap:.5rem;align-items:center;">
        <div style="display:flex;background:var(--bg);border:1px solid var(--sep-bold);border-radius:var(--r-sm);overflow:hidden;">
            <a href="?layout=list" class="btn <?= $layoutPref === 'list' ? 'btn-primary' : 'lp-btn-ghost' ?>" style="border-radius:0;border:none;padding:.3rem .55rem;"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="8" y1="6" x2="21" y2="6"></line><line x1="8" y1="12" x2="21" y2="12"></line><line x1="8" y1="18" x2="21" y2="18"></line><line x1="3" y1="6" x2="3.01" y2="6"></line><line x1="3" y1="12" x2="3.01" y2="12"></line><line x1="3" y1="18" x2="3.01" y2="18"></line></svg></a>
            <a href="?layout=grid" class="btn <?= $layoutPref === 'grid' ? 'btn-primary' : 'lp-btn-ghost' ?>" style="border-radius:0;border:none;padding:.3rem .55rem;"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><rect x="3" y="3" width="7" height="7"></rect><rect x="14" y="3" width="7" height="7"></rect><rect x="14" y="14" width="7" height="7"></rect><rect x="3" y="14" width="7" height="7"></rect></svg></a>
        </div>
        <?php if ($canCreateVm): ?><button type="button" class="btn btn-sm" onclick="openCreateVm()"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg><?= t('new_vm') ?></button><?php endif; ?>
    </div>
</div>

<?php if (!empty($displayVms)): ?>
<?php if ($layoutPref === 'grid'): ?>
<div class="vm-grid">
    <?php foreach ($displayVms as $vm): ?>
    <?php
        $isPveTemplate = !empty($vm['is_pve_template']);
        $hasPveId      = !empty($vm['proxmox_vmid']);
        $vs            = $vm['status'] ?? 'stopped';
        $s             = $vs;
    ?>
    <?php if ($isPveTemplate): ?>
    <div class="vm-category-row" id="vm-card-<?= $vm['id'] ?>">
        <div class="vm-category-header" onclick="toggleCopiesPanel(<?= $vm['id'] ?>)">
            <div class="vm-category-header-left">
                <div class="vm-category-chevron" id="chevron-<?= $vm['id'] ?>">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><polyline points="6 9 12 15 18 9"/></svg>
                </div>
                <div class="vm-category-icon">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/></svg>
                </div>
                <div>
                    <div class="vm-category-name"><?= htmlspecialchars($vm['name']) ?></div>
                    <div class="vm-category-meta"><?= htmlspecialchars($vm['os_type']??'—') ?> · <?= $vm['cpu_cores']??0 ?> CPU · <?= ($vm['ram_mb']??0)>=1024?round($vm['ram_mb']/1024,1).' GB':($vm['ram_mb']??0).' MB' ?> · <?= $vm['disk_gb']??0 ?> GB</div>
                </div>
            </div>
            <div class="vm-category-actions">
                <span class="vm-category-badge">Category</span>
                <?php if ($hasPveId): ?><span class="vm-pve-chip">PVE #<?= (int)$vm['proxmox_vmid'] ?></span><?php endif; ?>
                <?php if ($canCreateVm): ?>
                <button type="button" class="icon-btn" title="Assign users"
                    style="color:var(--accent);border-color:var(--accent-border);"
                    onclick="event.stopPropagation();openAssignUsers(<?= $vm['id'] ?>,'<?= htmlspecialchars(addslashes($vm['name']),ENT_QUOTES) ?>')">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><line x1="23" y1="11" x2="17" y2="11"/><line x1="20" y1="8" x2="20" y2="14"/></svg>
                </button>
                <button type="button" class="icon-btn danger" title="Delete template"
                    onclick="event.stopPropagation();openDeleteVm(<?= $vm['id'] ?>,'<?= htmlspecialchars(addslashes($vm['name']),ENT_QUOTES) ?>')">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                </button>
                <?php endif; ?>
            </div>
        </div>
        <div class="vm-category-body" id="copies-panel-<?= $vm['id'] ?>" style="display:none;">
            <div class="vm-category-search-wrap">
                <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" style="color:var(--text-3);flex-shrink:0;"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                <input type="text" class="vm-category-search" placeholder="Search students…"
                    oninput="filterCopies(<?= $vm['id'] ?>,this.value)"
                    onclick="event.stopPropagation()">
                <span class="vm-category-count" id="copies-count-<?= $vm['id'] ?>"></span>
            </div>
            <div class="vm-copies-list" id="copies-list-<?= $vm['id'] ?>">
                <div style="text-align:center;padding:.75rem;color:var(--text-3);font-size:.72rem;">Loading…</div>
            </div>
        </div>
    </div>
    <?php else: ?>
    <div class="card vm-card" id="vm-card-<?= $vm['id'] ?>">
        <div class="vm-card-header">
            <div style="display:flex;align-items:center;gap:.4rem;min-width:0;">
                <div class="vm-card-title"><?= htmlspecialchars($vm['name']) ?></div>
                <?php if ($isStudent && !empty($vm['template_id'])): ?>
                <?php $tplVm = DB::getVmById((int)$vm['template_id']); if ($tplVm): ?>
                <span style="font-size:.58rem;color:var(--accent);background:var(--accent-bg);border:1px solid var(--accent-border);border-radius:var(--r-pill);padding:.04rem .32rem;"><?= htmlspecialchars($tplVm['name']) ?></span>
                <?php endif; endif; ?>
            </div>
            <span class="badge <?= $s==='running'?'badge-student':'badge-user' ?>" data-vm-id="<?= $vm['id'] ?>"><?= $s === 'running' ? t('vm_status_running') : t('vm_status_stopped') ?></span>
        </div>
        <div class="vm-card-body">
            <div style="display:flex;align-items:center;gap:.35rem;"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="3" width="20" height="14" rx="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg><?= htmlspecialchars($vm['os_type']??'—') ?></div>
            <div style="display:flex;align-items:center;gap:.35rem;"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg><?= $vm['cpu_cores']??0 ?> CPU · <?= ($vm['ram_mb']??0)>=1024?round($vm['ram_mb']/1024,1).' GB':($vm['ram_mb']??0).' MB' ?> · <?= $vm['disk_gb']??0 ?> GB</div>
            <?php if (!empty($vm['last_active'])): ?><div style="color:var(--text-3);font-size:.7rem;">Last active <?= htmlspecialchars($vm['last_active']) ?></div><?php endif; ?>
        </div>
        <div class="vm-card-footer">
            <div style="font-size:.7rem;color:var(--text-3);">#<?= $vm['id'] ?><?php if ($hasPveId): ?> <span style="color:var(--green);">PVE #<?= (int)$vm['proxmox_vmid'] ?></span><?php endif; ?></div>
            <div style="display:flex;gap:.3rem;align-items:center;">
                <button type="button" class="icon-btn" title="Open VM"
                    style="color:var(--green);border-color:rgba(16,185,129,.25);"
                    onclick="openVmViewer(<?= $vm['id'] ?>,'<?= htmlspecialchars(addslashes($vm['name']),ENT_QUOTES) ?>')">
                    <svg width="11" height="11" viewBox="0 0 24 24" fill="currentColor" stroke="none"><polygon points="5 3 19 12 5 21 5 3"/></svg>
                </button>
                <?php if ($canCreateVm): ?>
                <button type="button" class="icon-btn" title="Assign users"
                    style="color:var(--accent);border-color:var(--accent-border);"
                    onclick="openAssignUsers(<?= $vm['id'] ?>,'<?= htmlspecialchars(addslashes($vm['name']),ENT_QUOTES) ?>')">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><line x1="23" y1="11" x2="17" y2="11"/><line x1="20" y1="8" x2="20" y2="14"/></svg>
                </button>
                <?php if ($proxmoxEnabled && $hasPveId): ?>
                <button type="button" class="icon-btn" title="Convert to template"
                    style="color:var(--accent);border-color:var(--accent-border);"
                    onclick="convertToTemplate(<?= $vm['id'] ?>,<?= (int)$vm['proxmox_vmid'] ?>,'<?= htmlspecialchars(addslashes($vm['name']),ENT_QUOTES) ?>')">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/></svg>
                </button>
                <?php endif; ?>
                <?php endif; ?>
                <?php if ($vs === 'running'): ?>
                <button type="button" class="icon-btn" title="Kill VM"
                    style="color:var(--orange);border-color:rgba(245,158,11,.25);"
                    onclick="killVm(<?= $vm['id'] ?>,'<?= htmlspecialchars(addslashes($vm['name']),ENT_QUOTES) ?>',this)">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><circle cx="12" cy="12" r="10"/><line x1="4.93" y1="4.93" x2="19.07" y2="19.07"/></svg>
                </button>
                <?php else: ?>
                <button type="button" class="icon-btn danger" title="Delete VM"
                    onclick="openDeleteVm(<?= $vm['id'] ?>,'<?= htmlspecialchars(addslashes($vm['name']),ENT_QUOTES) ?>')">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                </button>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>
        <?php endforeach; ?>
</div>
<?php else: ?>
<div class="card" style="padding:0;overflow:hidden;">
    <div style="padding:.65rem .9rem;border-bottom:1px solid var(--sep);"><h3 style="font-size:.85rem;margin:0;display:flex;align-items:center;gap:.35rem;"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="var(--text-3)" stroke-width="2"><rect x="2" y="3" width="20" height="14" rx="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg><?= t('virtual_machines') ?></h3></div>
    <div class="table-wrap" style="border:none;border-radius:0;"><table>
        <thead><tr><th style="width:28px;"></th><th><?= t('col_name') ?></th><th class="hide-mobile"><?= t('col_os') ?></th><th class="hide-mobile"><?= t('col_resources') ?></th><th><?= t('col_status') ?></th><th class="hide-mobile"><?= t('col_scope') ?></th><?php if ($canCreateVm): ?><th style="width:72px;"></th><?php endif; ?></tr></thead>
        <tbody>
        <?php foreach ($displayVms as $vm):
            $isPveTemplate = !empty($vm['is_pve_template']);
            $hasPveId      = !empty($vm['proxmox_vmid']);
        ?>
                <?php if ($isPveTemplate): ?>
        <tr style="background:none;">
            <td colspan="<?= $canCreateVm ? 7 : 6 ?>" style="padding:0;">
                <div class="vm-category-row vm-category-row-inline" id="vm-card-<?= $vm['id'] ?>">
                    <div class="vm-category-header" onclick="toggleCopiesPanel(<?= $vm['id'] ?>)">
                        <div class="vm-category-header-left">
                            <div class="vm-category-chevron" id="chevron-<?= $vm['id'] ?>">
                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><polyline points="6 9 12 15 18 9"/></svg>
                            </div>
                            <div class="vm-category-icon">
                                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/></svg>
                            </div>
                            <div>
                                <div class="vm-category-name"><?= htmlspecialchars($vm['name']) ?></div>
                                <div class="vm-category-meta"><?= htmlspecialchars($vm['os_type']??'—') ?> · <?= $vm['cpu_cores']??0 ?> CPU · <?= ($vm['ram_mb']??0)>=1024?round($vm['ram_mb']/1024,1).' GB':($vm['ram_mb']??0).' MB' ?> · <?= $vm['disk_gb']??0 ?> GB</div>
                            </div>
                        </div>
                        <div class="vm-category-actions">
                            <span class="vm-category-badge">Category</span>
                            <?php if ($hasPveId): ?><span class="vm-pve-chip">PVE #<?= (int)$vm['proxmox_vmid'] ?></span><?php endif; ?>
                            <?php if ($canCreateVm): ?>
                            <button type="button" class="icon-btn" title="Assign users"
                                style="color:var(--accent);border-color:var(--accent-border);"
                                onclick="event.stopPropagation();openAssignUsers(<?= $vm['id'] ?>,'<?= htmlspecialchars(addslashes($vm['name']),ENT_QUOTES) ?>')">
                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><line x1="23" y1="11" x2="17" y2="11"/><line x1="20" y1="8" x2="20" y2="14"/></svg>
                            </button>
                            <button type="button" class="icon-btn danger" title="Delete template"
                                onclick="event.stopPropagation();openDeleteVm(<?= $vm['id'] ?>,'<?= htmlspecialchars(addslashes($vm['name']),ENT_QUOTES) ?>')">
                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                            </button>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="vm-category-body" id="copies-panel-<?= $vm['id'] ?>" style="display:none;">
                        <div class="vm-category-search-wrap">
                            <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" style="color:var(--text-3);flex-shrink:0;"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                            <input type="text" class="vm-category-search" placeholder="Search students…"
                                oninput="filterCopies(<?= $vm['id'] ?>,this.value)"
                                onclick="event.stopPropagation()">
                            <span class="vm-category-count" id="copies-count-<?= $vm['id'] ?>"></span>
                        </div>
                        <div class="vm-copies-list" id="copies-list-<?= $vm['id'] ?>">
                            <div style="text-align:center;padding:.75rem;color:var(--text-3);font-size:.72rem;">Loading…</div>
                        </div>
                    </div>
                </div>
            </td>
        </tr>
        <?php else: ?>
        <tr class="vm-list-row" onclick="openVmViewer(<?= $vm['id'] ?>,'<?= htmlspecialchars(addslashes($vm['name']),ENT_QUOTES) ?>')" style="cursor:pointer;">
            <td onclick="event.stopPropagation()" style="padding-left:.6rem;">
                <button type="button" class="vm-list-play-btn"
                    onclick="openVmViewer(<?= $vm['id'] ?>,'<?= htmlspecialchars(addslashes($vm['name']),ENT_QUOTES) ?>')" title="Launch VM">
                    <svg width="9" height="9" viewBox="0 0 24 24" fill="currentColor" stroke="none"><polygon points="5 3 19 12 5 21 5 3"/></svg>
                </button>
            </td>
            <td style="font-weight:500;"><?= htmlspecialchars($vm['name']) ?>
                <?php if ($isStudent && !empty($vm['template_id'])): ?>
                <?php $tplVm=DB::getVmById((int)$vm['template_id']); if ($tplVm): ?>
                <span style="font-size:.58rem;color:var(--accent);background:var(--accent-bg);border:1px solid var(--accent-border);border-radius:var(--r-pill);padding:.04rem .32rem;margin-left:.3rem;"><?= htmlspecialchars($tplVm['name']) ?></span>
                <?php endif; endif; ?>
            </td>
            <td class="hide-mobile"><span class="badge badge-user"><?= htmlspecialchars($vm['os_type']??'—') ?></span></td>
            <td class="hide-mobile" style="font-size:.75rem;color:var(--text-2);"><?= $vm['cpu_cores']??0 ?> CPU · <?= ($vm['ram_mb']??0)>=1024?round($vm['ram_mb']/1024,1).' GB':($vm['ram_mb']??0).' MB' ?> · <?= $vm['disk_gb']??0 ?> GB</td>
            <td><?php $s=$vm['status']??'stopped'; ?><span class="badge <?= $s==='running'?'badge-student':'badge-user' ?>" data-vm-id="<?= $vm['id'] ?>"><?= $s === 'running' ? t('vm_status_running') : t('vm_status_stopped') ?></span></td>
            <td class="hide-mobile" style="font-size:.75rem;color:var(--text-2);"><?= ucfirst(str_replace('_',' ',$vm['assign_scope']??'personal')) ?></td>
            <td onclick="event.stopPropagation()" style="display:flex;gap:.3rem;align-items:center;"><?php $vs=$vm['status']??'stopped'; ?>
                <?php if ($canCreateVm): ?>
                <button type="button" class="icon-btn" title="Assign users"
                    style="color:var(--accent);border-color:var(--accent-border);"
                    onclick="openAssignUsers(<?= $vm['id'] ?>,'<?= htmlspecialchars(addslashes($vm['name']),ENT_QUOTES) ?>')">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><line x1="23" y1="11" x2="17" y2="11"/><line x1="20" y1="8" x2="20" y2="14"/></svg>
                </button>
                <?php if ($proxmoxEnabled && $hasPveId): ?>
                <button type="button" class="icon-btn" title="Convert to template"
                    style="color:var(--accent);border-color:var(--accent-border);"
                    onclick="convertToTemplate(<?= $vm['id'] ?>,<?= (int)$vm['proxmox_vmid'] ?>,'<?= htmlspecialchars(addslashes($vm['name']),ENT_QUOTES) ?>')">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/></svg>
                </button>
                <?php endif; ?>
                <?php endif; ?>
                <?php if ($vs==='running'): ?>
                <button type="button" class="icon-btn" title="Kill VM"
                    style="color:var(--orange);border-color:rgba(245,158,11,.25);"
                    onclick="killVm(<?= $vm['id'] ?>,'<?= htmlspecialchars(addslashes($vm['name']),ENT_QUOTES) ?>',this)">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><circle cx="12" cy="12" r="10"/><line x1="4.93" y1="4.93" x2="19.07" y2="19.07"/></svg>
                </button>
                <?php else: ?>
                <button type="button" class="icon-btn danger" title="Delete VM"
                    onclick="openDeleteVm(<?= $vm['id'] ?>,'<?= htmlspecialchars(addslashes($vm['name']),ENT_QUOTES) ?>')">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                </button>
                <?php endif; ?>
            </td>
        </tr>
        <?php endif; ?>
                <?php endforeach; ?>
        </tbody>
    </table></div>
</div>
<?php endif; ?>
<?php elseif ($isStudent || !$canCreateVm): ?>
<div class="card" style="text-align:center;padding:2.5rem;"><svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="color:var(--text-3);margin-bottom:.65rem;"><rect x="2" y="3" width="20" height="14" rx="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg><h2 style="font-size:.92rem;margin-bottom:.3rem;"><?= t('no_vms_student') ?></h2><p style="color:var(--text-2);font-size:.78rem;"><?= t('no_vms_student_desc') ?></p></div>
<?php else: ?>
<div class="card" style="text-align:center;padding:2rem;"><p style="color:var(--text-3);font-size:.82rem;"><?= t('no_vms_teacher') ?></p></div>
<?php endif; ?>

<?php if ($canCreateVm): ?>
<div id="create-vm-modal" class="modal-overlay" style="display:none;">
    <div id="create-vm-card" class="card modal-card create-vm-card">

        <div id="vm-mode-picker">
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1.1rem;">
                <h2 style="font-size:.95rem;display:flex;align-items:center;gap:.35rem;">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="var(--accent)" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                    <?= t('create_vm_title') ?>
                </h2>
                <button type="button" class="icon-btn" onclick="closeCreateVm()" style="width:26px;height:26px;" title="<?= t('cancel') ?>">
                    <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                </button>
            </div>
            <p style="font-size:.78rem;color:var(--text-2);margin-bottom:1.1rem;"><?= t('vm_mode_desc') ?></p>
            <div class="vm-mode-options">
                <div class="vm-mode-opt" onclick="selectVmMode('quick')">
                    <div class="vm-mode-icon" style="background:var(--green-bg);border-color:rgba(16,185,129,.2);">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="var(--green)" stroke-width="1.8" stroke-linecap="round"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>
                    </div>
                    <div class="vm-mode-label"><?= t('vm_mode_quick') ?></div>
                    <div class="vm-mode-sub"><?= t('vm_mode_quick_desc') ?></div>
                </div>
                <div class="vm-mode-opt" onclick="selectVmMode('advanced')">
                    <div class="vm-mode-icon" style="background:var(--accent-bg);border-color:var(--accent-border);">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="var(--accent)" stroke-width="1.8" stroke-linecap="round"><circle cx="12" cy="12" r="3"/><path d="M19.07 4.93a10 10 0 0 1 0 14.14M4.93 4.93a10 10 0 0 0 0 14.14"/></svg>
                    </div>
                    <div class="vm-mode-label"><?= t('vm_mode_advanced') ?></div>
                    <div class="vm-mode-sub"><?= t('vm_mode_advanced_desc') ?></div>
                </div>
            </div>
        </div>

        <div id="vm-form-quick" style="display:none;">
            <div class="vm-form-header">
                <button type="button" class="vm-back-btn" onclick="backToModePicker()">
                    <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><polyline points="15 18 9 12 15 6"/></svg>
                    <?= t('back') ?>
                </button>
                <span style="font-size:.72rem;font-weight:600;color:var(--green);">
                    <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>
                    <?= t('vm_mode_quick') ?>
                </span>
                <button type="button" class="icon-btn" onclick="closeCreateVm()" style="width:26px;height:26px;">
                    <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                </button>
            </div>
            <form method="post" id="quick-vm-form">
                <input type="hidden" name="action" value="create_vm">
                <input type="hidden" name="ram_mb" value="2048">
                <input type="hidden" name="cpu_cores" value="2">
                <input type="hidden" name="disk_gb" value="20">
                <input type="hidden" name="assign_scope" value="personal">
                <input type="hidden" name="iso_volid" value="">

                <div style="margin-bottom:1rem;">
                    <label class="vm-field-label"><?= t('vm_name_label') ?></label>
                    <input type="text" name="vm_name" id="quick-vm-name"
                           placeholder="<?= htmlspecialchars(t('vm_name_placeholder')) ?>"
                           class="vm-field-input" required>
                </div>

                <div style="margin-bottom:1.1rem;">
                    <label class="vm-field-label"><?= t('boot_iso_label') ?></label>
                    <?php if ($proxmoxEnabled && !empty($proxmoxIsos)): ?>
                    <select name="iso_volid" id="quick-pve-iso" class="vm-field-input" required>
                        <option value=""><?= t('select_iso') ?></option>
                        <?php foreach ($proxmoxIsos as $pi): ?>
                        <option value="<?= htmlspecialchars($pi['volid']) ?>"><?= htmlspecialchars($pi['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <input type="hidden" name="iso_id" value="0">
                    <?php else: ?>
                    <select name="iso_id" id="quick-echo-iso" class="vm-field-input" required>
                        <option value=""><?= t('select_iso') ?></option>
                        <?php foreach ($isos as $iso): ?>
                        <option value="<?= $iso['id'] ?>"><?= htmlspecialchars($iso['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <input type="hidden" name="iso_volid" value="">
                    <?php endif; ?>
                </div>

                <div style="margin-bottom:1.1rem;">
                    <label class="vm-field-label"><?= t('vm_preset_label') ?></label>
                    <div class="vm-preset-grid">
                        <div class="vm-preset-card selected" data-ram="2048" data-cpu="2" data-disk="20" onclick="selectPreset(this)">
                            <div class="vm-preset-name"><?= t('preset_basic') ?></div>
                            <div class="vm-preset-specs">2 GB · 2 CPU · 20 GB</div>
                        </div>
                        <div class="vm-preset-card" data-ram="4096" data-cpu="2" data-disk="40" onclick="selectPreset(this)">
                            <div class="vm-preset-name"><?= t('preset_standard') ?></div>
                            <div class="vm-preset-specs">4 GB · 2 CPU · 40 GB</div>
                        </div>
                        <div class="vm-preset-card" data-ram="8192" data-cpu="4" data-disk="80" onclick="selectPreset(this)">
                            <div class="vm-preset-name"><?= t('preset_performance') ?></div>
                            <div class="vm-preset-specs">8 GB · 4 CPU · 80 GB</div>
                        </div>
                    </div>
                    <div style="font-size:.62rem;color:var(--text-3);margin-top:.35rem;"><?= t('vm_preset_hint') ?></div>
                </div>

                <div style="display:flex;gap:.35rem;justify-content:flex-end;margin-top:.5rem;">
                    <button type="button" class="btn btn-sm btn-outline" onclick="closeCreateVm()"><?= t('cancel') ?></button>
                    <button type="submit" class="btn btn-sm">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                        <?= t('create_vm_btn') ?>
                    </button>
                </div>
            </form>
        </div>

        <div id="vm-form-advanced" style="display:none;">
            <div class="vm-form-header">
                <button type="button" class="vm-back-btn" onclick="backToModePicker()">
                    <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><polyline points="15 18 9 12 15 6"/></svg>
                    <?= t('back') ?>
                </button>
                <span style="font-size:.72rem;font-weight:600;color:var(--accent);">
                    <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="3"/></svg>
                    <?= t('vm_mode_advanced') ?>
                </span>
                <button type="button" class="icon-btn" onclick="closeCreateVm()" style="width:26px;height:26px;">
                    <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                </button>
            </div>
            <form method="post" id="advanced-vm-form">
                <input type="hidden" name="action" value="create_vm">
                <div class="vm-modal-grid">
                    <div class="form-group"><label><?= t('vm_name_label') ?></label><input type="text" name="vm_name" placeholder="<?= htmlspecialchars(t('vm_name_placeholder')) ?>" required></div>
                    <div class="form-group">
                        <label><?= t('boot_iso_label') ?></label>
                        <?php if ($proxmoxEnabled && !empty($proxmoxIsos)): ?>
                        <select name="iso_volid" id="pve-iso-select" required>
                            <option value=""><?= t('select_iso') ?></option>
                            <?php foreach ($proxmoxIsos as $piso): ?>
                            <option value="<?= htmlspecialchars($piso['volid']) ?>"><?= htmlspecialchars($piso['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <input type="hidden" name="iso_id" value="0">
                        <?php else: ?>
                        <select name="iso_id" required>
                            <option value=""><?= t('select_iso') ?></option>
                            <?php foreach ($isos as $iso): ?>
                            <option value="<?= $iso['id'] ?>"><?= htmlspecialchars($iso['name']) ?> (<?= htmlspecialchars($iso['os_type']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                        <input type="hidden" name="iso_volid" value="">
                        <?php endif; ?>
                    </div>
                    <div class="form-group"><label><?= t('ram_label') ?></label><select name="ram_mb"><option value="512">512 MB</option><option value="1024">1 GB</option><option value="2048" selected>2 GB</option><option value="4096">4 GB</option><option value="8192">8 GB</option><option value="16384">16 GB</option><option value="32768">32 GB</option></select></div>
                    <div class="form-group"><label><?= t('cpu_label') ?></label><select name="cpu_cores"><option value="1">1 <?= t('core') ?></option><option value="2" selected>2 <?= t('cores') ?></option><option value="4">4 <?= t('cores') ?></option><option value="8">8 <?= t('cores') ?></option><option value="16">16 <?= t('cores') ?></option></select></div>
                    <div class="form-group"><label><?= t('disk_label') ?></label><input type="number" name="disk_gb" value="20" min="5" max="2000" required> <span style="font-size:.62rem;color:var(--text-3);">GB</span></div>
                    <div class="form-group"><label><?= t('assign_label') ?></label><select name="assign_scope" id="assign-scope" onchange="toggleCenterSelect()"><option value="personal"><?= t('assign_only_me') ?></option><option value="my_students"><?= t('assign_my_students') ?></option><option value="center"><?= t('assign_entire_group') ?></option><?php if ($myRole>=ROLE_ADMIN): ?><option value="all"><?= t('assign_all_users') ?></option><?php endif; ?></select></div>
                    <?php if ($myRole>=ROLE_ADMIN&&!empty($centers)): ?><div class="form-group" id="center-select-group" style="display:none;"><label><?= t('target_group_label') ?></label><select name="assign_center"><option value=""><?= t('my_group') ?></option><?php foreach ($centers as $c): ?><option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option><?php endforeach; ?></select></div><?php endif; ?>
                </div>
                <?php if ($proxmoxEnabled): ?>
                <label style="display:flex;align-items:flex-start;gap:.5rem;margin:.75rem 0 .25rem;cursor:pointer;font-size:.78rem;color:var(--text-2);">
                    <input type="checkbox" name="auto_template" value="1" style="margin-top:2px;accent-color:var(--accent);width:13px;height:13px;flex-shrink:0;">
                    <span>
                        <?= t('auto_template_label') ?>
                        <span style="display:block;font-size:.65rem;color:var(--text-3);margin-top:.1rem;"><?= t('auto_template_desc') ?></span>
                    </span>
                </label>
                <?php endif; ?>
                <div style="display:flex;gap:.35rem;justify-content:flex-end;margin-top:1rem;">
                    <button type="button" class="btn btn-sm btn-outline" onclick="closeCreateVm()"><?= t('cancel') ?></button>
                    <button type="submit" class="btn btn-sm">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                        <?= t('create_vm_btn') ?>
                    </button>
                </div>
            </form>
        </div>

    </div>
</div>
<div id="delete-vm-modal" class="modal-overlay" style="display:none;">
    <div class="card modal-card" style="padding:1.25rem;">
        <h2 style="color:var(--red);font-size:.95rem;margin-bottom:.35rem;"><?= t('delete_vm_title') ?></h2>
        <p style="color:var(--text-2);font-size:.8rem;margin-bottom:1rem;"><?= t('delete_vm_confirm') ?> <strong id="delete-vm-name" style="color:var(--text);"></strong>?</p>
        <form method="post">
            <input type="hidden" name="action" value="delete_vm">
            <input type="hidden" name="vm_id" id="delete-vm-id" value="">
            <div class="advanced-toggle" onclick="toggleAdvancedDelete()" style="cursor:pointer;display:flex;align-items:center;gap:.4rem;margin-bottom:.75rem;font-size:.75rem;color:var(--text-2);">
                <svg id="advanced-chevron" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" style="transition:transform .2s;"><polyline points="6 9 12 15 18 9"/></svg>
                <span>Advanced</span>
            </div>
            <div id="advanced-options" style="display:none;margin-bottom:1rem;">
                <label style="display:flex;align-items:center;gap:.5rem;cursor:pointer;font-size:.8rem;color:var(--text);">
                    <input type="checkbox" name="delete_proxmox" value="1" checked style="accent-color:var(--accent);width:16px;height:16px;">
                    <span>Delete VM from Proxmox server</span>
                </label>
            </div>
            <div style="display:flex;gap:.35rem;justify-content:flex-end;">
                <button type="button" class="btn btn-sm btn-outline" onclick="closeDeleteVm()"><?= t('cancel') ?></button>
                <button type="submit" class="btn btn-sm btn-danger"><?= t('delete') ?></button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<?php if ($canCreateVm): ?>
<div id="assign-users-modal" class="modal-overlay" style="display:none;">
    <div class="card modal-card" style="padding:0;max-width:500px;overflow:hidden;width:100%;">
        <div style="padding:.9rem 1.1rem;border-bottom:1px solid var(--sep);display:flex;align-items:center;justify-content:space-between;">
            <div style="display:flex;align-items:center;gap:.5rem;">
                <div style="width:28px;height:28px;background:var(--accent-bg);border:1px solid var(--accent-border);border-radius:7px;display:flex;align-items:center;justify-content:center;color:var(--accent);">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><line x1="23" y1="11" x2="17" y2="11"/><line x1="20" y1="8" x2="20" y2="14"/></svg>
                </div>
                <div>
                    <div style="font-size:.88rem;font-weight:600;">Assign VM</div>
                    <div id="assign-vm-subtitle" style="font-size:.68rem;color:var(--text-3);"></div>
                </div>
            </div>
            <button type="button" class="icon-btn" onclick="closeAssignUsers()">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>
        <div style="padding:.75rem 1.1rem;border-bottom:1px solid var(--sep);background:rgba(124,92,252,.03);">
            <div style="font-size:.66rem;font-weight:700;letter-spacing:.06em;text-transform:uppercase;color:var(--text-3);margin-bottom:.45rem;display:flex;align-items:center;gap:.3rem;">
                <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                Assign entire group
            </div>
            <div style="display:flex;gap:.5rem;align-items:center;">
                <select id="assign-group-select" style="flex:1;padding:.35rem .55rem;font-size:.77rem;background:var(--surface2);border:1px solid var(--sep-bold);border-radius:var(--r-sm);color:var(--text);font-family:var(--font);">
                    <option value="">Select a group…</option>
                </select>
                <button type="button" class="btn btn-sm" id="assign-group-btn" onclick="assignGroupToVm()">
                    Assign Group
                </button>
            </div>
            <div style="font-size:.62rem;color:var(--text-3);margin-top:.3rem;">Creates individual VM clones for every user in the group.</div>
        </div>
        <div style="display:flex;align-items:center;gap:.6rem;padding:.4rem 1.1rem;font-size:.65rem;color:var(--text-3);">
            <span style="flex:1;height:1px;background:var(--sep-bold);"></span>or assign individual users<span style="flex:1;height:1px;background:var(--sep-bold);"></span>
        </div>
        <div style="display:flex;align-items:center;gap:.4rem;padding:.4rem .9rem;border-bottom:1px solid var(--sep);">
            <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" style="color:var(--text-3);flex-shrink:0;"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
            <input type="text" id="assign-search" class="proc-search" placeholder="Search users…" oninput="filterAssignUsers(this.value)" style="flex:1;border:none;background:transparent;padding:0;">
        </div>
        <div id="assign-user-list" style="max-height:260px;overflow-y:auto;padding:.25rem 0;">
            <div style="text-align:center;padding:1.5rem;color:var(--text-3);font-size:.78rem;">Loading…</div>
        </div>
        <div style="padding:.7rem 1rem;border-top:1px solid var(--sep);display:flex;align-items:center;justify-content:space-between;">
            <span id="assign-count-label" style="font-size:.7rem;color:var(--text-3);"></span>
            <div style="display:flex;gap:.4rem;">
                <button type="button" class="btn btn-sm btn-outline" onclick="closeAssignUsers()">Cancel</button>
                <button type="button" class="btn btn-sm" id="assign-save-btn" onclick="saveAssignUsers()">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><polyline points="20 6 9 17 4 12"/></svg>
                    Save
                </button>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>
<style>
.dash-bar{display:flex;align-items:center;justify-content:space-between;margin:.75rem 0 1.25rem;flex-wrap:wrap;gap:.5rem}
.vm-grid { display:grid; grid-template-columns:repeat(auto-fill, minmax(280px, 1fr)); gap:1rem; }
.vm-card { padding:1.25rem; display:flex; flex-direction:column; gap:.75rem; transition:transform .2s, border-color .2s; height:100%; border:1px solid var(--sep-bold); }
.vm-card:hover { transform:translateY(-2px); border-color:var(--accent-border); box-shadow:0 8px 24px var(--accent-bg); }
.vm-card-template { border-color:var(--accent-border) !important; background:linear-gradient(135deg,var(--surface) 0%,rgba(124,92,252,.03) 100%); }
.vm-card-template:hover { box-shadow:0 8px 24px rgba(124,92,252,.15); }
.vm-card-header { display:flex; align-items:center; justify-content:space-between; }
.vm-card-title { font-size:1rem; font-weight:600; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.vm-card-body { font-size:.78rem; color:var(--text-2); display:flex; flex-direction:column; gap:.4rem; margin-bottom:.5rem; }
.vm-card-footer { border-top:1px solid var(--sep); padding-top:.75rem; margin-top:auto; display:flex; justify-content:space-between; align-items:center; }
.vm-copies-panel { border-top:1px solid var(--sep); margin:-.75rem -1.25rem 0; overflow:hidden; }
.vm-copies-list { max-height:220px; overflow-y:auto; }
.vm-copies-list::-webkit-scrollbar{width:3px;}.vm-copies-list::-webkit-scrollbar-thumb{background:var(--sep-bold);border-radius:2px;}
.vm-copy-row { display:flex;align-items:center;gap:.55rem;padding:.4rem .7rem;border-bottom:.5px solid var(--sep);font-size:.75rem; }
.vm-copy-row:last-child{border-bottom:none;}
.vm-copy-row.hidden{display:none;}
.vm-copy-avatar{width:24px;height:24px;border-radius:50%;background:var(--accent-bg);border:1px solid var(--accent-border);display:flex;align-items:center;justify-content:center;font-size:.55rem;font-weight:700;color:var(--accent);flex-shrink:0;}
.vm-copy-name{flex:1;font-weight:500;min-width:0;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
.vm-copy-status{font-size:.6rem;font-weight:600;padding:.08rem .35rem;border-radius:var(--r-pill);}
.vm-copy-status.running{background:var(--green-bg);color:var(--green);}
.vm-copy-status.stopped{background:var(--surface3);color:var(--text-3);}
.vm-copy-actions{display:flex;gap:2px;flex-shrink:0;}
.dash-bar-left{display:flex;align-items:center;gap:.35rem;flex-wrap:wrap}
.vm-modal-grid{display:grid;grid-template-columns:1fr 1fr;gap:.6rem}
.show-mobile{display:none}
@media(max-width:640px){.vm-modal-grid{grid-template-columns:1fr}.hide-mobile{display:none!important}.show-mobile{display:block!important}}
.assign-user-item{display:flex;align-items:center;gap:.65rem;padding:.5rem 1rem;cursor:pointer;transition:background .12s;border-bottom:.5px solid var(--sep);}
.assign-user-item:last-child{border-bottom:none;}
.assign-user-item:hover{background:rgba(255,255,255,.025);}
.assign-user-item.checked{background:var(--accent-bg);}
.assign-avatar{width:28px;height:28px;border-radius:50%;background:var(--accent-bg);border:1px solid var(--accent-border);display:flex;align-items:center;justify-content:center;font-size:.6rem;font-weight:700;color:var(--accent);flex-shrink:0;overflow:hidden;}
.assign-avatar img{width:100%;height:100%;object-fit:cover;border-radius:50%;}
.assign-user-info{flex:1;min-width:0;}
.assign-user-name{font-size:.78rem;font-weight:500;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
.assign-user-role{font-size:.6rem;color:var(--text-3);margin-top:1px;}
.assign-checkbox{width:15px;height:15px;accent-color:var(--accent);cursor:pointer;flex-shrink:0;}
#assign-user-list::-webkit-scrollbar{width:3px;}
#assign-user-list::-webkit-scrollbar-thumb{background:rgba(255,255,255,.08);border-radius:2px;}
.proc-search{flex:1;min-width:100px;padding:.28rem .5rem;background:rgba(255,255,255,.03);border:.5px solid var(--sep-bold);border-radius:var(--r-sm);color:var(--text);font-size:.7rem;font-family:var(--font);outline:none;transition:border-color .15s;}
.proc-search::placeholder{color:var(--text-3);}.proc-search:focus{border-color:var(--accent);}

.advanced-toggle:hover { color: var(--text); }

.vm-list-row:hover td { background:rgba(16,185,129,.04) !important; }
.vm-list-row:hover { box-shadow: inset 3px 0 0 var(--green); }
.vm-list-play-btn {
    width: 24px; height: 24px;
    border-radius: 50%;
    background: rgba(16,185,129,.12);
    border: 1px solid rgba(16,185,129,.25);
    color: var(--green);
    display: flex; align-items: center; justify-content: center;
    cursor: pointer;
    transition: all .15s;
    padding: 0;
    flex-shrink: 0;
}
.vm-list-play-btn:hover, .vm-list-row:hover .vm-list-play-btn {
    background: var(--green);
    color: #fff;
    border-color: var(--green);
    transform: scale(1.1);
    box-shadow: 0 2px 8px rgba(16,185,129,.35);
}
.vm-list-play-btn svg { margin-left: 1px; }

.vm-launch-modal{position:fixed;inset:0;z-index:9100;display:flex;align-items:center;justify-content:center;background:rgba(0,0,0,.6);backdrop-filter:blur(6px);}
.vm-launch-card{background:var(--surface);border:1px solid var(--sep-bold);border-radius:14px;padding:1.75rem;width:100%;max-width:380px;box-shadow:0 24px 64px rgba(0,0,0,.5);}
.vm-launch-opts{display:grid;grid-template-columns:1fr 1fr;gap:.65rem;margin-top:1.2rem;}
.vm-launch-opt{background:var(--surface2);border:1px solid var(--sep-bold);border-radius:10px;padding:1.1rem .9rem;cursor:pointer;transition:all .18s;text-align:center;display:flex;flex-direction:column;align-items:center;gap:.55rem;}
.vm-launch-opt:hover{border-color:var(--accent-border);background:var(--accent-bg);transform:translateY(-2px);box-shadow:0 8px 24px rgba(124,92,252,.12);}
.vm-launch-opt-icon{width:40px;height:40px;border-radius:10px;background:var(--accent-bg);border:1px solid var(--accent-border);display:flex;align-items:center;justify-content:center;color:var(--accent);}
.vm-launch-opt-title{font-size:.82rem;font-weight:600;color:var(--text);}
.vm-launch-opt-desc{font-size:.65rem;color:var(--text-3);line-height:1.45;}

#vm-wm-layer{position:fixed;inset:0;pointer-events:none;z-index:8000;}
.vm-win{position:absolute;background:#141418;border:1px solid rgba(255,255,255,.12);border-radius:10px;overflow:hidden;box-shadow:0 24px 60px rgba(0,0,0,.7);display:flex;flex-direction:column;pointer-events:all;min-width:480px;min-height:320px;transition:box-shadow .15s;}
.vm-win.active{border-color:rgba(124,92,252,.4);box-shadow:0 28px 70px rgba(0,0,0,.8),0 0 0 1px rgba(124,92,252,.2);}
.vm-win.minimized{display:none;}
.vm-win.maximized{border-radius:0!important;border:none!important;}
.vm-win-tb{display:flex;align-items:center;justify-content:space-between;padding:0 .7rem;height:40px;background:var(--surface);border-bottom:1px solid var(--sep-bold);cursor:move;user-select:none;flex-shrink:0;gap:.5rem;}
.vm-win-tb-left{display:flex;align-items:center;gap:.5rem;min-width:0;}
.vm-win-tb-icon{width:22px;height:22px;border-radius:5px;background:var(--accent-bg);border:1px solid var(--accent-border);display:flex;align-items:center;justify-content:center;color:var(--accent);flex-shrink:0;}
.vm-win-tb-title{font-size:.72rem;font-weight:600;color:var(--text);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:200px;}
.vm-win-tb-status{display:inline-flex;align-items:center;gap:.3rem;padding:.1rem .45rem;border-radius:var(--r-pill);font-size:.58rem;font-weight:600;flex-shrink:0;}
.vm-win-tb-status.running{background:var(--green-bg);color:var(--green);border:1px solid rgba(16,185,129,.2);}
.vm-win-tb-status.stopped{background:var(--surface2);color:var(--text-3);border:1px solid var(--sep-bold);}
.vm-win-tb-status-dot{width:5px;height:5px;border-radius:50%;background:currentColor;}
.vm-win-tb-right{display:flex;align-items:center;gap:3px;flex-shrink:0;}
.vm-win-tb-btn{height:26px;border-radius:var(--r-sm);border:1px solid var(--sep-bold);background:var(--surface2);color:var(--text-3);cursor:pointer;display:inline-flex;align-items:center;gap:.28rem;padding:0 .5rem;font-size:.6rem;font-weight:600;font-family:var(--font);transition:all .12s;white-space:nowrap;}
.vm-win-tb-btn:hover{background:var(--surface3);color:var(--text);border-color:var(--text-3);}
.vm-win-tb-btn svg{flex-shrink:0;}
.vm-win-tb-btn.minimise:hover{border-color:var(--orange);color:var(--orange);background:rgba(245,158,11,.08);}
.vm-win-tb-btn.maximise:hover{border-color:var(--accent);color:var(--accent);background:var(--accent-bg);}
.vm-win-tb-btn.detach:hover{border-color:var(--green);color:var(--green);background:var(--green-bg);}
.vm-win-tb-sep{width:1px;height:16px;background:var(--sep-bold);margin:0 1px;}
.vm-win-screen{flex:1;background:#000;position:relative;overflow:hidden;}
.vm-resize{position:absolute;z-index:10;}
.vm-resize-e{top:8px;right:0;width:5px;bottom:8px;cursor:e-resize;}
.vm-resize-s{bottom:0;left:8px;right:8px;height:5px;cursor:s-resize;}
.vm-resize-se{bottom:0;right:0;width:12px;height:12px;cursor:se-resize;}
.vm-resize-w{top:8px;left:0;width:5px;bottom:8px;cursor:w-resize;}
.vm-resize-n{top:0;left:8px;right:8px;height:5px;cursor:n-resize;}
.vm-resize-sw{bottom:0;left:0;width:12px;height:12px;cursor:sw-resize;}
.vm-resize-ne{top:0;right:0;width:12px;height:12px;cursor:ne-resize;}
.vm-resize-nw{top:0;left:0;width:12px;height:12px;cursor:nw-resize;}

#vm-taskbar{position:fixed;bottom:0;left:0;right:0;height:44px;background:rgba(11,11,15,.9);backdrop-filter:blur(20px);border-top:1px solid rgba(255,255,255,.07);z-index:8500;display:none;align-items:center;padding:0 .75rem;gap:.4rem;pointer-events:all;}
#vm-taskbar.visible{display:flex;}
.vm-tb-item{display:inline-flex;align-items:center;gap:.4rem;padding:.18rem .6rem;border-radius:var(--r-sm);background:var(--surface2);border:1px solid var(--sep-bold);cursor:pointer;transition:all .12s;max-width:180px;font-size:.65rem;font-weight:500;color:var(--text-2);font-family:var(--font);}
.vm-tb-item:hover{background:var(--surface3);color:var(--text);border-color:var(--text-3);}
.vm-tb-item.active-win{background:var(--accent-bg);border-color:var(--accent-border);color:var(--accent);}
.vm-tb-item.minimized-win{opacity:.55;}
.vm-tb-dot{width:6px;height:6px;border-radius:50%;background:var(--green);flex-shrink:0;}
.vm-tb-dot.stopped{background:var(--text-3);}
#vm-tb-close-all{margin-left:auto;font-size:.6rem;font-weight:600;color:var(--text-3);cursor:pointer;padding:.18rem .55rem;border-radius:var(--r-sm);border:1px solid var(--sep-bold);transition:all .12s;background:var(--surface2);font-family:var(--font);display:inline-flex;align-items:center;gap:.3rem;white-space:nowrap;}
#vm-tb-close-all:hover{color:var(--red);border-color:rgba(244,63,94,.25);background:rgba(244,63,94,.07);}

.win-screen{position:absolute;inset:0;transition:opacity .4s ease;}
.win-bios{background:#000;color:#c8c8c8;font-family:var(--mono);font-size:.72rem;padding:1.4rem;text-align:left;display:flex;flex-direction:column;gap:.12rem;line-height:1.55;overflow:hidden;}
.win-logo-screen{background:#000;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:2rem;}
.win-logo-dots{display:flex;gap:8px;}
.win-logo-dot{width:10px;height:10px;border-radius:50%;background:#0078d4;animation:winDot 1.4s ease-in-out infinite;}
.win-logo-dot:nth-child(2){animation-delay:.16s;}
.win-logo-dot:nth-child(3){animation-delay:.32s;}
.win-logo-dot:nth-child(4){animation-delay:.48s;}
.win-logo-dot:nth-child(5){animation-delay:.64s;}
@keyframes winDot{0%,100%{opacity:.2;transform:scale(.7)}50%{opacity:1;transform:scale(1)}}
.win-desktop{background:linear-gradient(135deg,#0a2a6e 0%,#1a5fb4 60%,#0d47a1 100%);position:relative;overflow:hidden;}
.win-desktop-wallpaper{position:absolute;inset:0;background:radial-gradient(ellipse at 30% 70%,rgba(255,255,255,.06) 0%,transparent 60%);}
.win-taskbar{position:absolute;bottom:0;left:0;right:0;height:40px;background:rgba(0,0,0,.75);backdrop-filter:blur(20px);border-top:1px solid rgba(255,255,255,.1);display:flex;align-items:center;justify-content:center;gap:.5rem;}
.win-start-btn{width:32px;height:32px;background:transparent;border:none;cursor:pointer;display:flex;align-items:center;justify-content:center;border-radius:4px;}
.win-taskbar-icon{width:28px;height:28px;display:flex;align-items:center;justify-content:center;border-radius:4px;cursor:pointer;}
.win-clock{position:absolute;right:12px;font-size:.6rem;color:rgba(255,255,255,.85);text-align:center;line-height:1.4;}
.win-desktop-icon{position:absolute;display:flex;flex-direction:column;align-items:center;gap:4px;cursor:pointer;padding:6px;border-radius:4px;}
.win-icon-img{width:36px;height:36px;border-radius:4px;display:flex;align-items:center;justify-content:center;}
.win-icon-label{font-size:.55rem;color:#fff;text-shadow:0 1px 3px rgba(0,0,0,.8);text-align:center;max-width:64px;}
.win-connecting-badge{position:absolute;top:12px;right:12px;background:rgba(0,0,0,.5);border:1px solid rgba(255,255,255,.15);border-radius:20px;padding:4px 12px;font-size:.6rem;color:rgba(255,255,255,.7);display:flex;align-items:center;gap:6px;}
.win-ping-dot{width:6px;height:6px;border-radius:50%;background:#10b981;animation:winDot 1.5s ease-in-out infinite;}
.vm-category-row {
    grid-column: 1 / -1;
    background: var(--surface);
    border: 1px solid var(--accent-border);
    border-radius: var(--r);
    overflow: hidden;
    transition: box-shadow .2s;
}
.vm-category-row:hover { box-shadow: 0 4px 20px rgba(124,92,252,.12); }
.vm-category-row.open  { border-color: var(--accent); }
.vm-category-row-inline { border-radius: 0; border-left: none; border-right: none; }
.vm-category-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: .65rem 1rem;
    cursor: pointer;
    user-select: none;
    background: linear-gradient(135deg,var(--surface) 0%,rgba(124,92,252,.04) 100%);
    transition: background .15s;
}
.vm-category-header:hover { background: linear-gradient(135deg,var(--surface2) 0%,rgba(124,92,252,.07) 100%); }
.vm-category-header-left  { display:flex;align-items:center;gap:.55rem;min-width:0; }
.vm-category-chevron {
    width:20px;height:20px;display:flex;align-items:center;justify-content:center;
    color:var(--text-3);transition:transform .2s;flex-shrink:0;
}
.vm-category-row.open .vm-category-chevron { transform:rotate(180deg);color:var(--accent); }
.vm-category-icon {
    width:28px;height:28px;background:var(--accent-bg);border:1px solid var(--accent-border);
    border-radius:7px;display:flex;align-items:center;justify-content:center;color:var(--accent);flex-shrink:0;
}
.vm-category-name { font-size:.87rem;font-weight:600;color:var(--text); }
.vm-category-meta { font-size:.64rem;color:var(--text-3);margin-top:.06rem; }
.vm-category-actions { display:flex;align-items:center;gap:.4rem;flex-shrink:0; }
.vm-category-badge {
    font-size:.55rem;font-weight:700;letter-spacing:.06em;text-transform:uppercase;
    background:var(--accent-bg);color:var(--accent);border:1px solid var(--accent-border);
    border-radius:var(--r-pill);padding:.08rem .42rem;
}
.vm-pve-chip {
    font-size:.58rem;color:var(--green);font-family:var(--mono);
    background:var(--green-bg);border:1px solid rgba(16,185,129,.2);
    border-radius:var(--r-pill);padding:.05rem .35rem;
}
.vm-category-body { border-top:1px solid var(--sep);background:var(--bg); }
.vm-category-search-wrap {
    display:flex;align-items:center;gap:.4rem;padding:.45rem .85rem;
    border-bottom:1px solid var(--sep);background:var(--surface);
}
.vm-category-search {
    flex:1;background:transparent;border:none;outline:none;
    color:var(--text);font-size:.73rem;font-family:var(--font);
}
.vm-category-search::placeholder { color:var(--text-3); }
.vm-category-count {
    font-size:.6rem;color:var(--text-3);background:var(--surface3);
    border-radius:var(--r-pill);padding:.05rem .28rem;font-weight:600;white-space:nowrap;
}
.create-vm-card { padding:1.4rem; max-width:480px; width:100%; }
.vm-mode-options { display:grid; grid-template-columns:1fr 1fr; gap:.6rem; margin-bottom:.2rem; }
.vm-mode-opt { border:1.5px solid var(--sep-bold);border-radius:var(--r);padding:1rem .9rem;cursor:pointer;transition:all .15s;display:flex;flex-direction:column;align-items:center;gap:.5rem;text-align:center; }
.vm-mode-opt:hover { border-color:var(--accent);background:var(--accent-bg); }
.vm-mode-icon { width:44px;height:44px;border-radius:10px;border:1px solid;display:flex;align-items:center;justify-content:center; }
.vm-mode-label { font-size:.82rem;font-weight:600;color:var(--text); }
.vm-mode-sub { font-size:.68rem;color:var(--text-3);line-height:1.4; }
.vm-form-header { display:flex;align-items:center;justify-content:space-between;margin-bottom:1rem;padding-bottom:.7rem;border-bottom:1px solid var(--sep); }
.vm-back-btn { display:inline-flex;align-items:center;gap:.3rem;font-size:.72rem;color:var(--text-3);background:none;border:none;cursor:pointer;font-family:var(--font);padding:.2rem .35rem;border-radius:var(--r-sm);transition:all .12s; }
.vm-back-btn:hover { color:var(--text);background:var(--surface2); }
.vm-field-label { display:block;font-size:.72rem;font-weight:500;color:var(--text-2);margin-bottom:.3rem; }
.vm-field-input { width:100%;padding:.45rem .65rem;background:var(--bg);border:1px solid var(--sep-bold);border-radius:var(--r-sm);color:var(--text);font-family:var(--font);font-size:.82rem;transition:border-color .15s; }
.vm-field-input:focus { outline:none;border-color:var(--accent); }
.vm-preset-grid { display:grid;grid-template-columns:repeat(3,1fr);gap:.4rem; }
.vm-preset-card { border:1.5px solid var(--sep-bold);border-radius:var(--r-sm);padding:.55rem .45rem;cursor:pointer;text-align:center;transition:all .12s; }
.vm-preset-card:hover { border-color:var(--accent); }
.vm-preset-card.selected { border-color:var(--accent);background:var(--accent-bg); }
.vm-preset-name { font-size:.72rem;font-weight:600;color:var(--text); }
.vm-preset-specs { font-size:.6rem;color:var(--text-3);margin-top:.15rem; }
.modal-card { resize:both;overflow:auto;min-width:280px; }


</style>

<div id="vm-launch-modal" class="vm-launch-modal" style="display:none;">
  <div class="vm-launch-card">
    <div style="display:flex;align-items:center;gap:.6rem;margin-bottom:.25rem;">
      <div style="width:32px;height:32px;background:var(--accent-bg);border:1px solid var(--accent-border);border-radius:8px;display:flex;align-items:center;justify-content:center;color:var(--accent);flex-shrink:0;">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="3" width="20" height="14" rx="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg>
      </div>
      <div>
        <div style="font-size:.9rem;font-weight:700;" id="vm-launch-name">Launch VM</div>
        <div style="font-size:.65rem;color:var(--text-3);">How would you like to open it?</div>
      </div>
    </div>
    <div class="vm-launch-opts">
      <div class="vm-launch-opt" onclick="launchAsWindow()">
        <div class="vm-launch-opt-icon">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><rect x="2" y="3" width="20" height="14" rx="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg>
        </div>
        <div class="vm-launch-opt-title">Window</div>
        <div class="vm-launch-opt-desc">Floating window inside the dashboard. Drag, resize, minimise.</div>
      </div>
      <div class="vm-launch-opt" onclick="launchAsTab()">
        <div class="vm-launch-opt-icon">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>
        </div>
        <div class="vm-launch-opt-title">New Tab</div>
        <div class="vm-launch-opt-desc">Opens the VM in a full dedicated browser tab.</div>
      </div>
    </div>
    <button onclick="closeLaunchModal()" style="width:100%;margin-top:.9rem;padding:.38rem;background:transparent;border:1px solid var(--sep-bold);border-radius:7px;color:var(--text-3);font-size:.72rem;cursor:pointer;font-family:var(--font);transition:all .12s;" onmouseover="this.style.background='var(--surface2)'" onmouseout="this.style.background='transparent'">Cancel</button>
  </div>
</div>

<div id="vm-wm-layer"></div>

<div id="vm-taskbar">
  <span style="font-size:.6rem;color:rgba(232,232,238,.25);font-family:var(--mono);padding-right:.4rem;white-space:nowrap;">VMs</span>
  <div id="vm-tb-items" style="display:flex;align-items:center;gap:.4rem;flex:1;overflow-x:auto;"></div>
  <button id="vm-tb-close-all" onclick="closeAllVmWindows()" title="Close all VM windows (VMs keep running)">
    <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
    Close all windows
  </button>
</div>

<div id="confirm-modal" style="position:fixed;inset:0;z-index:99999;display:none;align-items:center;justify-content:center;background:rgba(0,0,0,.6);backdrop-filter:blur(6px);">
    <div id="confirm-dialog" style="background:var(--surface);border:1px solid var(--sep-bold);border-radius:12px;padding:1.4rem 1.5rem;max-width:380px;width:90%;box-shadow:0 24px 60px rgba(0,0,0,.6);animation:confirmIn .15s ease;cursor:default;">
        <div style="display:flex;align-items:flex-start;gap:.75rem;margin-bottom:1rem;">
            <div id="confirm-icon" style="width:34px;height:34px;border-radius:9px;display:flex;align-items:center;justify-content:center;flex-shrink:0;"></div>
            <div>
                <div id="confirm-title" style="font-size:.9rem;font-weight:600;color:var(--text);margin-bottom:.3rem;"></div>
                <div id="confirm-body" style="font-size:.78rem;color:var(--text-2);line-height:1.5;"></div>
            </div>
        </div>
        <div style="display:flex;gap:.5rem;justify-content:flex-end;">
            <button id="confirm-cancel" class="btn btn-sm btn-outline" onclick="echoConfirmReject()">Cancel</button>
            <button id="confirm-ok" class="btn btn-sm" onclick="echoConfirmResolve()">Confirm</button>
        </div>
    </div>
</div>
<style>
@keyframes confirmIn { from { opacity:0; transform:scale(.93) translateY(-6px); } to { opacity:1; transform:none; } }
@keyframes spin { to { transform:rotate(360deg); } }
</style>

<script>
function openCreateVm() {
    var modal = document.getElementById('create-vm-modal');
    if (!modal) return;
    document.getElementById('vm-mode-picker').style.display  = 'block';
    document.getElementById('vm-form-quick').style.display    = 'none';
    document.getElementById('vm-form-advanced').style.display = 'none';
    modal.style.display = 'flex';
}
function closeCreateVm() {
    var logWrap = document.getElementById('vm-create-log-wrap');
    if (logWrap) logWrap.style.display = 'none';
    ['quick-vm-form','advanced-vm-form'].forEach(function(id){
        var f = document.getElementById(id);
        if (f) f.reset();
    });
    var modal = document.getElementById('create-vm-modal');
    if (modal) modal.style.display = 'none';
}
function selectVmMode(mode) {
    document.getElementById('vm-mode-picker').style.display  = 'none';
    document.getElementById('vm-form-quick').style.display    = mode === 'quick'    ? 'block' : 'none';
    document.getElementById('vm-form-advanced').style.display = mode === 'advanced' ? 'block' : 'none';
}
function backToModePicker() {
    document.getElementById('vm-mode-picker').style.display  = 'block';
    document.getElementById('vm-form-quick').style.display    = 'none';
    document.getElementById('vm-form-advanced').style.display = 'none';
}
function selectPreset(card) {
    document.querySelectorAll('.vm-preset-card').forEach(function(c){ c.classList.remove('selected'); });
    card.classList.add('selected');
    var form = document.getElementById('quick-vm-form');
    if (form) {
        form.querySelector('[name=ram_mb]').value   = card.dataset.ram;
        form.querySelector('[name=cpu_cores]').value = card.dataset.cpu;
        form.querySelector('[name=disk_gb]').value   = card.dataset.disk;
    }
}

function toggleAdvancedDelete() {
    var panel = document.getElementById('advanced-options');
    var chev = document.getElementById('advanced-chevron');
    if (panel.style.display === 'none') {
        panel.style.display = 'block';
        chev.style.transform = 'rotate(180deg)';
    } else {
        panel.style.display = 'none';
        chev.style.transform = '';
    }
}

(function() {
    var PVE_ENABLED = <?= $proxmoxEnabled ? 'true' : 'false' ?>;
    if (!PVE_ENABLED) return;

    window.addEventListener('DOMContentLoaded', function() {
        var modal = document.getElementById('create-vm-modal');
        if (!modal) return;

        var logWrap = document.createElement('div');
        logWrap.id = 'vm-create-log-wrap';
        logWrap.style.cssText = 'display:none;padding:.75rem 1.25rem 1rem;border-top:1px solid var(--sep);';
        logWrap.innerHTML =
            '<div style="font-size:.6rem;font-weight:600;color:var(--text-3);text-transform:uppercase;letter-spacing:.06em;margin-bottom:.3rem;">Creating in Proxmox</div>' +
            '<div id="vm-create-log" style="background:var(--bg);border:1px solid var(--sep-bold);border-radius:var(--r-sm);padding:.45rem .65rem;height:120px;overflow-y:auto;font-family:var(--mono);font-size:.62rem;color:var(--green);line-height:1.7;"></div>' +
            '<div style="height:3px;background:var(--sep-bold);border-radius:2px;margin-top:.4rem;overflow:hidden;">' +
            '<div id="vm-create-bar" style="height:100%;background:var(--accent);width:0%;transition:width .4s;border-radius:2px;"></div></div>';
        modal.querySelector('.modal-card').appendChild(logWrap);

        ['quick-vm-form', 'advanced-vm-form'].forEach(function(fid) {
            var form = document.getElementById(fid);
            if (!form) return;
            form.addEventListener('submit', function(e) {
            var isoVolid = form.querySelector('[name=iso_volid]');
            if (!isoVolid || !isoVolid.value) return;
            e.preventDefault();

            var existingBar = document.getElementById('vm-create-topbar');
            if (!existingBar) {
                existingBar = document.createElement('div');
                existingBar.id = 'vm-create-topbar';
                existingBar.style.cssText = 'position:absolute;top:0;left:0;right:0;height:3px;background:var(--surface3);border-radius:var(--r) var(--r) 0 0;overflow:hidden;z-index:10;';
                existingBar.innerHTML = '<div id="vm-create-topbar-fill" style="height:100%;width:0%;background:var(--accent);transition:width .4s;border-radius:inherit;"></div>';
                var mc = document.querySelector('#create-vm-modal .modal-card');
                if (mc) { mc.style.position='relative'; mc.appendChild(existingBar); }
            }
            document.getElementById('vm-create-topbar').style.display = 'block';
            var submitBtn = form.querySelector('[type=submit]');
            if (submitBtn) { submitBtn.disabled = true; submitBtn.innerHTML = '<span class="btn-spinner"></span>'; }
            logWrap.style.display = 'none';

            var log = document.getElementById('vm-create-log');
            var bar = document.getElementById('vm-create-bar');
            var progress = 5;

            function addLog(line) {
                line = line.trim();
                if (!line) return;
                line.split('\n').forEach(function(l) {
                    if (!l.trim()) return;
                    var d = document.createElement('div'); d.textContent = l;
                    log.appendChild(d); log.scrollTop = log.scrollHeight;
                });
            }

            var barTimer = setInterval(function() {
                if (progress < 88) { progress += 1.5;
                    var fill = document.getElementById('vm-create-topbar-fill');
                    if (fill) fill.style.width = progress + '%';
                    bar.style.width = progress + '%'; }
            }, 1000);

            var fd  = new FormData(form);
            var xhr = new XMLHttpRequest();
            xhr.open('POST', 'api/vm_create_stream.php', true);
            var seen = 0;

            xhr.onreadystatechange = function() {
                if (xhr.readyState >= 3) {
                    var chunk = xhr.responseText.substring(seen);
                    seen = xhr.responseText.length;
                    if (!chunk) return;

                    if (chunk.indexOf('__RESULT__:') !== -1) {
                        var parts = chunk.split('__RESULT__:');
                        if (parts[0].trim()) addLog(parts[0]);
                        clearInterval(barTimer);
                        bar.style.width = '100%'; var fill=document.getElementById('vm-create-topbar-fill'); if(fill) fill.style.width='100%';
                        try {
                            var result = JSON.parse(parts[1].trim());
                            if (result.ok && result.ticket) {
                                addLog('[OK] Console opening...');
                                sessionStorage.setItem('vnc_pending', JSON.stringify(result));
                                setTimeout(function() {
                                    closeCreateVm();
                                    openVncFromResult(result);
                                }, 700);
                            } else if (result.ok) {
                                addLog('[OK] VM created. Reloading...');
                                setTimeout(function() { location.reload(); }, 1500);
                            } else {
                                addLog('[ERROR] ' + (result.error || 'Unknown error'));
                                clearInterval(barTimer);
                                setTimeout(function() {
                                    var tb=document.getElementById('vm-create-topbar'); if(tb) tb.style.display='none';
                                    var sb=form.querySelector('[type=submit]'); if(sb){sb.disabled=false;sb.innerHTML='<?= addslashes(t("create_vm_btn")) ?>';}
                                }, 3000);
                            }
                        } catch(err) { addLog('[parse error] ' + err.message); }
                    } else {
                        addLog(chunk);
                    }
                }
            };
            xhr.send(fd);
        }); // submit listener
        }); // forEach forms
    });
})();

function openVncFromResult(data) {
    sessionStorage.setItem('vnc_data', JSON.stringify(data));
    var url = 'vm_viewer.php?id=' + (data.echo_vm_id || 0) + '&mode=vnc';
    window.open(url, '_blank');
}
function toggleCenterSelect() {
    var scope = document.getElementById('assign-scope');
    var group = document.getElementById('center-select-group');
    if (scope && group) group.style.display = (scope.value === 'center') ? 'block' : 'none';
}

function openDeleteVm(id, name) {
    var idInput = document.getElementById('delete-vm-id');
    var nameLabel = document.getElementById('delete-vm-name');
    var modal = document.getElementById('delete-vm-modal');
    if (idInput) idInput.value = id;
    if (nameLabel) nameLabel.textContent = name || 'this VM';
    if (modal) modal.style.display = 'flex';
}
function closeDeleteVm() {
    var modal = document.getElementById('delete-vm-modal');
    if (modal) modal.style.display = 'none';
}

var _assignVmId = null;
var _assignUsers = [];
var _assignRoles = {0: 'Banned', 1: 'User', 2: 'Student', 3: 'Teacher', 4: 'Center Admin', 5: 'Admin'};

function openAssignUsers(vmId, vmName) {
    _assignVmId = vmId;
    document.getElementById('assign-vm-subtitle').textContent = vmName || '';
    document.getElementById('assign-users-modal').style.display = 'flex';
    document.getElementById('assign-search').value = '';
    loadAssignUsers(vmId);
    loadGroupsForAssign(vmId);
}

function loadGroupsForAssign(vmId) {
    fetch('api/vm_assign.php?vm_id=' + vmId)
        .then(r => r.json())
        .then(data => {
            if (data.ok && data.groups) {
                var sel = document.getElementById('assign-group-select');
                sel.innerHTML = '<option value="">Select a group…</option>';
                data.groups.forEach(g => {
                    var opt = document.createElement('option');
                    opt.value = g.id;
                    opt.textContent = g.name + ' (' + (g.type || 'group') + ')';
                    sel.appendChild(opt);
                });
            }
        });
}

function assignGroupToVm() {
    var sel = document.getElementById('assign-group-select');
    var groupId = sel.value;
    if (!groupId) { showToast('error', 'Please select a group'); return; }
    var btn = document.getElementById('assign-group-btn');
    btn.disabled = true;
    fetch('api/vm_assign.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({ action: 'assign_group', vm_id: _assignVmId, group_id: parseInt(groupId) })
    })
    .then(r => r.json())
    .then(data => {
        btn.disabled = false;
        if (data.ok) {
            showToast('success', 'Group assigned. ' + data.added + ' clones created.');
            closeAssignUsers();
            // Optionally refresh dashboard
            location.reload();
        } else {
            showToast('error', data.error || 'Failed to assign group');
        }
    })
    .catch(e => { btn.disabled = false; showToast('error', 'Network error'); });
}

function closeAssignUsers() {
    var modal = document.getElementById('assign-users-modal');
    if (modal) modal.style.display = 'none';
}
function loadAssignUsers(vmId) {
    var list = document.getElementById('assign-user-list');
    if (!list) return;
    list.innerHTML = '<div style="text-align:center;padding:1.5rem;color:var(--text-3);font-size:.78rem;">Loading…</div>';
    fetch('api/vm_assign.php?vm_id=' + vmId)
    .then(r => r.json())
    .then(data => {
        if (!data.ok) throw new Error(data.error);
        var assignedMap = {};
        (data.assigned || []).forEach(id => assignedMap[id] = true);
        _assignUsers = (data.users || []).map(u => ({
            id: u.id, username: u.username, role: u.role, avatar: u.avatar, assigned: !!assignedMap[u.id]
        }));
        renderAssignUsers('');
    })
    .catch(e => {
        list.innerHTML = '<div style="text-align:center;padding:1.5rem;color:var(--red);font-size:.78rem;">Error: ' + e.message + '</div>';
    });
}
function renderAssignUsers(filter) {
    filter = (filter || '').toLowerCase();
    var list = document.getElementById('assign-user-list');
    if (!list) return;
    list.innerHTML = '';
    var count = 0;
    _assignUsers.forEach(u => {
        if (filter && !u.username.toLowerCase().includes(filter)) return;
        count++;
        var item = document.createElement('div');
        item.className = 'assign-user-item' + (u.assigned ? ' checked' : '');
        item.onclick = function() {
            u.assigned = !u.assigned;
            item.className = 'assign-user-item' + (u.assigned ? ' checked' : '');
            updateAssignCount();
            var cb = item.querySelector('.assign-checkbox');
            if (cb) cb.checked = u.assigned;
        };
        var initial = u.username.charAt(0).toUpperCase();
        var roleName = _assignRoles[u.role] || 'User';
        var avHtml = u.avatar ? '<img src="data/avatars/' + escHtml(u.avatar) + '">' : initial;
        item.innerHTML = 
            '<div class="assign-avatar">' + avHtml + '</div>' +
            '<div class="assign-user-info">' +
                '<div class="assign-user-name">' + escHtml(u.username) + '</div>' +
                '<div class="assign-user-role">' + roleName + '</div>' +
            '</div>' +
            '<input type="checkbox" class="assign-checkbox" ' + (u.assigned ? 'checked' : '') + 
            ' onclick="event.stopPropagation(); this.parentElement.click();">';
        list.appendChild(item);
    });
    if (count === 0) list.innerHTML = '<div style="text-align:center;padding:1.5rem;color:var(--text-3);font-size:.78rem;">No users found</div>';
    updateAssignCount();
}
function updateAssignCount() {
    var c = document.getElementById('assign-count-label');
    if (!c) return;
    var amt = _assignUsers.filter(u => u.assigned).length;
    c.textContent = amt + ' user' + (amt !== 1 ? 's' : '') + ' selected';
}
function filterAssignUsers(val) {
    renderAssignUsers(val);
}
function saveAssignUsers() {
    var btn = document.getElementById('assign-save-btn');
    if (btn) btn.disabled = true;
    var userIds = _assignUsers.filter(u => u.assigned).map(u => u.id);
    fetch('api/vm_assign.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({ vm_id: _assignVmId, user_ids: userIds })
    })
    .then(r => r.json())
    .then(data => {
        if (btn) btn.disabled = false;
        if (data.ok) {
            closeAssignUsers();
            showToast('success', 'Assignment saved');
        } else {
            showToast('error', data.error || 'Failed to save');
        }
    })
    .catch(e => {
        if (btn) btn.disabled = false;
        showToast('error', 'Network error');
    });
}

// Gestor de ventanas de VM

var _pendingVmId   = null;
var _pendingVmName = null;
var _vmWindows     = {};
var _zCounter      = 100;
var _dragState     = null;
var _resizeState   = null;
var _heartbeatTimer = null;

// Opciones de lanzamiento
function openVmViewer(vmId, vmName) {
    _pendingVmId   = vmId;
    _pendingVmName = vmName;
    document.getElementById('vm-launch-name').textContent = vmName;
    document.getElementById('vm-launch-modal').style.display = 'flex';
}
function closeLaunchModal() {
    document.getElementById('vm-launch-modal').style.display = 'none';
}
function launchAsTab() {
    closeLaunchModal();
    vmApiAction(_pendingVmId, 'start');
    window.open('vm_viewer.php?id=' + _pendingVmId + '&name=' + encodeURIComponent(_pendingVmName), '_blank');
}
function launchAsWindow() {
    closeLaunchModal();
    createVmWindow(_pendingVmId, _pendingVmName);
}

// Helpers de estado de VM
function vmApiAction(vmId, action) {
    return fetch('api/vm_status.php', {
        method: 'POST',
        headers: {'Content-Type':'application/json'},
        body: JSON.stringify({vm_id: vmId, action: action})
    }).then(function(r){ return r.json(); }).catch(function(){});
}

function sendHeartbeats() {
    Object.keys(_vmWindows).forEach(function(id) {
        var w = _vmWindows[id];
        if (!w.minimized) vmApiAction(w.vmId, 'heartbeat');
    });
}

function startHeartbeatLoop() {
    if (_heartbeatTimer) return;
    _heartbeatTimer = setInterval(sendHeartbeats, 30000);
}
function stopHeartbeatLoop() {
    if (_heartbeatTimer) { clearInterval(_heartbeatTimer); _heartbeatTimer = null; }
}

// Sincronización automática de VM
(function() {
    var vmIds = [];
    document.querySelectorAll('[data-vm-id]').forEach(function(el) {
        var id = parseInt(el.getAttribute('data-vm-id'));
        if (id && vmIds.indexOf(id) === -1) vmIds.push(id);
    });
    if (!vmIds.length) return;

    function syncStatuses() {
        vmIds.forEach(function(vmId) {
            fetch('api/vm_status.php?vm_id=' + vmId)
                .then(function(r) { return r.json(); })
                .then(function(d) {
                    if (d && d.ok) refreshDashboardStatus(vmId, d.status);
                })
                .catch(function() {});
        });
    }
    setTimeout(syncStatuses, 8000);
    setInterval(syncStatuses, 30000);
})();

function setWinStatus(winId, status) {
    var w = _vmWindows[winId];
    if (!w) return;
    w.currentStatus = status;
    var badge = w.el.querySelector('.vm-win-tb-status');
    if (!badge) return;
    badge.className = 'vm-win-tb-status ' + status;
    badge.innerHTML = '<div class="vm-win-tb-status-dot"></div>' + (status === 'running' ? '<?= t("vm_status_running") ?>' : '<?= t("vm_status_stopped") ?>');
}

function refreshDashboardStatus(vmId, status) {
    document.querySelectorAll('[data-vm-id="' + vmId + '"]').forEach(function(el) {
        el.textContent = status.charAt(0).toUpperCase() + status.slice(1);
        el.className = 'badge ' + (status === 'running' ? 'badge-student' : 'badge-user');
    });
    document.querySelectorAll('[data-action-btn="' + vmId + '"]').forEach(function(btn) {
        if (status === 'running') {
            btn.className = 'icon-btn';
            btn.title = 'Kill VM';
            btn.style.color = 'var(--orange)';
            btn.style.borderColor = 'rgba(245,158,11,.25)';
            btn.style.background = '';
            btn.onclick = function() { killVm(vmId, btn.closest('tr,div.card')?.querySelector('.vm-card-title,td[style*="font-weight"]')?.textContent || 'VM', btn); };
            btn.innerHTML = '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><circle cx="12" cy="12" r="10"/><line x1="4.93" y1="4.93" x2="19.07" y2="19.07"/></svg>';
        } else {
            btn.className = 'icon-btn danger';
            btn.title = 'Delete VM';
            btn.style.color = '';
            btn.style.borderColor = '';
            btn.style.background = '';
            var nameEl = btn.closest('tr,div.card')?.querySelector('.vm-card-title,td[style*="font-weight"]');
            var vname = nameEl ? nameEl.textContent.trim() : 'VM';
            btn.onclick = function() { openDeleteVm(vmId, vname); };
            btn.innerHTML = '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>';
        }
    });
}

// Detener VM
function killVm(vmId, vmName, btn) {
    echoConfirm(
        'Force stop VM',
        'Stop <b>' + escHtml(vmName) + '</b>? The VM will be stopped but not deleted. You can restart it later.'
    ).then(function(ok) {
        if (!ok) return;
        if (btn) { btn.disabled = true; btn.style.opacity = '.4'; }
    vmApiAction(vmId, 'stop').then(function(d) {
        if (btn) { btn.disabled = false; btn.style.opacity = ''; }
        if (d && d.ok) {
            refreshDashboardStatus(vmId, 'stopped');
            Object.keys(_vmWindows).forEach(function(id) {
                if (_vmWindows[id].vmId === vmId) setWinStatus(id, 'stopped');
            });
            showToast('success', '"' + vmName + '" stopped.');
        }
    });
    });
}

// Creación de ventana
function createVmWindow(vmId, vmName) {
    var winId  = 'vm-win-' + Date.now();
    var layer  = document.getElementById('vm-wm-layer');
    var offset = Object.keys(_vmWindows).length * 28;
    var vw = window.innerWidth, vh = window.innerHeight;
    var w = Math.min(860, vw - 80), h = Math.min(560, vh - 120);
    var x = Math.max(20, (vw - w) / 2 + offset);
    var y = Math.max(20, (vh - h) / 2 + offset - 60);

    var win = document.createElement('div');
    win.id = winId;
    win.className = 'vm-win active';
    win.style.cssText = 'left:' + x + 'px;top:' + y + 'px;width:' + w + 'px;height:' + h + 'px;';

    // Barra de título
    var tb = document.createElement('div');
    tb.className = 'vm-win-tb';
    tb.innerHTML =
        '<div class="vm-win-tb-left">' +
            '<div class="vm-win-tb-icon">' +
                '<svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><rect x="2" y="3" width="20" height="14" rx="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg>' +
            '</div>' +
            '<span class="vm-win-tb-title">' + escHtml(vmName) + '</span>' +
            '<span class="vm-win-tb-status stopped"><div class="vm-win-tb-status-dot"></div><?= t("vm_status_stopped") ?></span>' +
        '</div>' +
        '<div class="vm-win-tb-right">' +
            '<button class="vm-win-tb-btn detach" title="Open in new tab" onclick="detachToTab(\'' + winId + '\')">' +
                '<svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>' +
                'New Tab' +
            '</button>' +
            '<div class="vm-win-tb-sep"></div>' +
            '<button class="vm-win-tb-btn minimise" title="Minimise" onclick="minimiseVmWindow(\'' + winId + '\')">' +
                '<svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="5" y1="12" x2="19" y2="12"/></svg>' +
            '</button>' +
            '<button class="vm-win-tb-btn maximise" title="Maximise / Restore" onclick="maximiseVmWindow(\'' + winId + '\')">' +
                '<svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/></svg>' +
            '</button>' +
            '<div class="vm-win-tb-sep"></div>' +
            '<button class="vm-win-tb-btn close-win" title="Close window" onclick="closeVmWindowPrompt(\'' + winId + '\', ' + vmId + ', \'' + escHtml(vmName) + '\')" style="color:var(--red);opacity:.7;">' +
                '<svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>' +
            '</button>' +
        '</div>';
    win.appendChild(tb);

    // Pantalla
    var screen = document.createElement('div');
    screen.className = 'vm-win-screen';
    screen.style.position = 'relative';
    var loadPlaceholder = document.createElement('div');
    loadPlaceholder.id = winId + '-placeholder';
    loadPlaceholder.style.cssText = 'position:absolute;inset:0;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:.75rem;background:#0b0b0f;';
    loadPlaceholder.innerHTML =
        '<div style="width:36px;height:36px;border:2.5px solid rgba(124,92,252,.2);border-top-color:var(--accent);border-radius:50%;animation:spin .7s linear infinite;"></div>' +
        '<div style="font-size:.75rem;color:var(--text-3);font-family:var(--mono);">Starting VM…</div>' +
        '<div style="font-size:.62rem;color:var(--text-3);opacity:.6;" id="' + winId + '-status-msg">Waiting for Proxmox…</div>';
    screen.appendChild(loadPlaceholder);
    win.appendChild(screen);

    ['n','s','e','w','ne','nw','se','sw'].forEach(function(dir) {
        var rh = document.createElement('div');
        rh.className = 'vm-resize vm-resize-' + dir;
        rh.addEventListener('mousedown', function(e) { startResize(e, winId, dir); });
        win.appendChild(rh);
    });

    tb.addEventListener('mousedown', function(e) {
        if (e.target.closest('.vm-win-tb-btn')) return;
        startDrag(e, winId);
    });
    win.addEventListener('mousedown', function() { focusVmWindow(winId); });

    layer.appendChild(win);
    _vmWindows[winId] = {
        el: win, vmId: vmId, vmName: vmName,
        minimized: false, maximized: false,
        prevRect: null, currentStatus: 'stopped'
    };

    win.style.opacity = '0'; win.style.transform = 'scale(.96)';
    win.style.transition = 'opacity .18s, transform .18s';
    requestAnimationFrame(function() { win.style.opacity='1'; win.style.transform='scale(1)'; });

    focusVmWindow(winId);
    updateTaskbar();
    startHeartbeatLoop();

    vmApiAction(vmId, 'start').then(function(d) {
        setWinStatus(winId, 'running');
        refreshDashboardStatus(vmId, 'running');
        var placeholder = document.getElementById(winId + '-placeholder');
        var iframe = document.createElement('iframe');
        iframe.src = 'vm_viewer.php?id=' + vmId + '&name=' + encodeURIComponent(vmName) + '&embedded=1';
        iframe.style.cssText = 'width:100%;height:100%;border:none;display:block;position:absolute;inset:0;';
        iframe.allow = 'fullscreen';
        if (placeholder) {
            var statusMsg = placeholder.querySelector('#' + winId + '-status-msg');
            if (statusMsg) statusMsg.textContent = 'Connecting console...';
            setTimeout(function() {
                if (placeholder) placeholder.style.transition = 'opacity .3s';
                if (placeholder) placeholder.style.opacity = '0';
                setTimeout(function() {
                    if (placeholder) placeholder.remove();
                }, 300);
            }, 800);
        }
        screen.appendChild(iframe);
    });
}

// Desacoplar ventana
function detachToTab(winId) {
    var w = _vmWindows[winId];
    if (!w) return;
    window.open('vm_viewer.php?id=' + w.vmId + '&name=' + encodeURIComponent(w.vmName), '_blank');
    closeVmWindow(winId);
}

// Controles de ventana
function focusVmWindow(winId) {
    Object.keys(_vmWindows).forEach(function(id) { _vmWindows[id].el.classList.remove('active'); });
    var w = _vmWindows[winId];
    if (!w) return;
    w.el.classList.add('active');
    w.el.style.zIndex = ++_zCounter;
    updateTaskbar();
}

function closeVmWindowPrompt(winId, vmId, vmName) {
    var w = _vmWindows[winId];
    if (!w) return;
    var isRunning = w.el.querySelector('.vm-win-tb-status') &&
                    w.el.querySelector('.vm-win-tb-status').classList.contains('running');

    if (!isRunning) {
        closeVmWindow(winId);
        return;
    }

    var existing = document.getElementById('close-prompt-' + winId);
    if (existing) { existing.remove(); return; }

    var prompt = document.createElement('div');
    prompt.id = 'close-prompt-' + winId;
    prompt.style.cssText = 'position:absolute;top:100%;right:0;background:var(--surface);border:1px solid var(--sep-bold);border-radius:var(--r-sm);padding:.5rem .65rem;z-index:999;white-space:nowrap;box-shadow:var(--shadow-lg);min-width:190px;';
    prompt.innerHTML =
        '<div style="font-size:.72rem;font-weight:600;color:var(--text);margin-bottom:.4rem;"><?= t("win_close_title") ?></div>' +
        '<label style="display:flex;align-items:center;gap:.4rem;font-size:.7rem;color:var(--text-2);cursor:pointer;margin-bottom:.5rem;">' +
            '<input type="checkbox" id="stop-on-close-' + winId + '" style="accent-color:var(--accent);width:12px;height:12px;">' +
            '<?= t("win_close_stop_label") ?>' +
        '</label>' +
        '<div style="display:flex;gap:.3rem;">' +
            '<button class="btn btn-sm" style="font-size:.68rem;flex:1;" onclick="doCloseWindow(\'' + winId + '\', ' + vmId + ')"><?= t("win_close_confirm") ?></button>' +
            '<button class="btn btn-sm btn-outline" style="font-size:.68rem;" onclick="document.getElementById(\'close-prompt-' + winId + '\').remove()"><?= t("cancel") ?></button>' +
        '</div>';

    var tb = w.el.querySelector('.vm-win-tb');
    tb.style.position = 'relative';
    tb.appendChild(prompt);

    setTimeout(function() {
        document.addEventListener('click', function onOutside(ev) {
            if (!prompt.contains(ev.target)) {
                prompt.remove();
                document.removeEventListener('click', onOutside);
            }
        });
    }, 10);
}

function doCloseWindow(winId, vmId) {
    var stopChk = document.getElementById('stop-on-close-' + winId);
    var shouldStop = stopChk && stopChk.checked;
    var w = _vmWindows[winId];
    if (!w) return;
    var vmName = w.el.querySelector('.vm-win-tb-title') ? w.el.querySelector('.vm-win-tb-title').textContent : 'VM';

    if (shouldStop) {
        vmApiAction(vmId, 'stop').then(function(d) {
            if (d && d.ok) {
                refreshDashboardStatus(vmId, 'stopped');
                showToast('success', '"' + vmName + '" <?= t("vm_stopped_toast") ?>');
            }
        });
    }
    closeVmWindow(winId);
}

function closeVmWindow(winId) {
    var w = _vmWindows[winId];
    if (!w) return;
    var el = w.el;
    el.style.transition = 'opacity .15s, transform .15s';
    el.style.opacity = '0'; el.style.transform = 'scale(.95)';
    setTimeout(function() {
        el.remove();
        delete _vmWindows[winId];
        if (Object.keys(_vmWindows).length === 0) stopHeartbeatLoop();
        updateTaskbar();
    }, 150);
}

function minimiseVmWindow(winId) {
    var w = _vmWindows[winId];
    if (!w) return;
    w.minimized = true;
    w.el.classList.add('minimized');
    updateTaskbar();
}

function restoreVmWindow(winId) {
    var w = _vmWindows[winId];
    if (!w) return;
    w.minimized = false;
    w.el.classList.remove('minimized');
    focusVmWindow(winId);
}

function maximiseVmWindow(winId) {
    var w = _vmWindows[winId];
    if (!w) return;
    var el = w.el;
    if (w.maximized) {
        var r = w.prevRect;
        el.style.left=r.left+'px'; el.style.top=r.top+'px';
        el.style.width=r.width+'px'; el.style.height=r.height+'px';
        el.style.borderRadius=''; el.classList.remove('maximized');
        w.maximized = false;
    } else {
        w.prevRect = {left:el.offsetLeft, top:el.offsetTop, width:el.offsetWidth, height:el.offsetHeight};
        el.style.left='0'; el.style.top='0';
        el.style.width='100vw'; el.style.height=(window.innerHeight-44)+'px';
        el.classList.add('maximized'); w.maximized=true;
    }
}

function closeAllVmWindows() {
    Object.keys(_vmWindows).forEach(function(id) { closeVmWindow(id); });
}

// Barra de tareas
function updateTaskbar() {
    var keys = Object.keys(_vmWindows);
    var tb   = document.getElementById('vm-taskbar');
    var items = document.getElementById('vm-tb-items');
    if (keys.length === 0) {
        tb.classList.remove('visible');
        document.body.style.paddingBottom = '';
        return;
    }
    tb.classList.add('visible');
    document.body.style.paddingBottom = '44px';
    items.innerHTML = '';
    keys.forEach(function(id) {
        var w = _vmWindows[id];
        var isActive = w.el.classList.contains('active');
        var item = document.createElement('div');
        item.className = 'vm-tb-item' + (isActive ? ' active-win' : '') + (w.minimized ? ' minimized-win' : '');
        item.innerHTML =
            '<div class="vm-tb-dot' + (w.currentStatus !== 'running' ? ' stopped' : '') + '"></div>' +
            '<span style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">' + escHtml(w.vmName.length > 18 ? w.vmName.substring(0,17)+'\u2026' : w.vmName) + '</span>';
        item.addEventListener('click', function() {
            if (w.minimized) restoreVmWindow(id); else focusVmWindow(id);
        });
        items.appendChild(item);
    });
}

// Arrastrar
function startDrag(e, winId) {
    e.preventDefault();
    var w = _vmWindows[winId];
    if (!w || w.maximized) return;
    focusVmWindow(winId);
    _dragState = {winId:winId, startX:e.clientX, startY:e.clientY, origLeft:w.el.offsetLeft, origTop:w.el.offsetTop};
}
document.addEventListener('mousemove', function(e) {
    if (_dragState) {
        var s=_dragState, w=_vmWindows[s.winId];
        if (!w){_dragState=null;return;}
        w.el.style.left = Math.max(0, s.origLeft + e.clientX - s.startX) + 'px';
        w.el.style.top  = Math.max(0, s.origTop  + e.clientY - s.startY) + 'px';
    }
    if (_resizeState) doResize(e);
});
document.addEventListener('mouseup', function() { _dragState=null; _resizeState=null; });

// Redimensionar
function startResize(e, winId, dir) {
    e.preventDefault(); e.stopPropagation();
    var w=_vmWindows[winId];
    if (!w||w.maximized) return;
    focusVmWindow(winId);
    _resizeState={winId:winId,dir:dir,startX:e.clientX,startY:e.clientY,origLeft:w.el.offsetLeft,origTop:w.el.offsetTop,origW:w.el.offsetWidth,origH:w.el.offsetHeight};
}
function doResize(e) {
    var s=_resizeState, w=_vmWindows[s.winId];
    if (!w) return;
    var dx=e.clientX-s.startX, dy=e.clientY-s.startY, minW=480, minH=320;
    var L=s.origLeft, T=s.origTop, W=s.origW, H=s.origH;
    if (s.dir.indexOf('e')>=0){W=Math.max(minW,s.origW+dx);}
    if (s.dir.indexOf('s')>=0){H=Math.max(minH,s.origH+dy);}
    if (s.dir.indexOf('w')>=0){var nw=Math.max(minW,s.origW-dx);L=s.origLeft+(s.origW-nw);W=nw;}
    if (s.dir.indexOf('n')>=0){var nh=Math.max(minH,s.origH-dy);T=s.origTop+(s.origH-nh);H=nh;}
    var el=w.el;
    el.style.left=L+'px'; el.style.top=T+'px'; el.style.width=W+'px'; el.style.height=H+'px';
}

document.addEventListener('keydown', function(e) { if (e.key==='Escape') closeLaunchModal(); });
document.getElementById('vm-launch-modal').addEventListener('click', function(e) { if (e.target===this) closeLaunchModal(); });

var _confirmResolve = null;
function echoConfirm(title, body, opts) {
    opts = opts || {};
    var modal   = document.getElementById('confirm-modal');
    var iconEl  = document.getElementById('confirm-icon');
    var titleEl = document.getElementById('confirm-title');
    var bodyEl  = document.getElementById('confirm-body');
    var okBtn   = document.getElementById('confirm-ok');
    var cancelBtn = document.getElementById('confirm-cancel');
    var isDanger = opts.danger !== false;

    titleEl.textContent = title;
    bodyEl.innerHTML    = body;
    okBtn.textContent   = opts.okLabel   || 'Confirm';
    cancelBtn.textContent = opts.cancelLabel || 'Cancel';

    if (isDanger) {
        okBtn.className = 'btn btn-sm btn-danger';
        iconEl.style.background = 'var(--red-bg)';
        iconEl.style.border = '1px solid rgba(244,63,94,.2)';
        iconEl.innerHTML = '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="var(--red)" stroke-width="2.2" stroke-linecap="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>';
    } else {
        okBtn.className = 'btn btn-sm';
        iconEl.style.background = 'var(--accent-bg)';
        iconEl.style.border = '1px solid var(--accent-border)';
        iconEl.innerHTML = '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="var(--accent)" stroke-width="2.2" stroke-linecap="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>';
    }

    modal.style.display = 'flex';
    return new Promise(function(resolve) { _confirmResolve = resolve; });
}
function echoConfirmResolve() {
    document.getElementById('confirm-modal').style.display = 'none';
    if (_confirmResolve) { _confirmResolve(true); _confirmResolve = null; }
}
function echoConfirmReject() {
    document.getElementById('confirm-modal').style.display = 'none';
    if (_confirmResolve) { _confirmResolve(false); _confirmResolve = null; }
}
document.addEventListener('DOMContentLoaded', function() {
    var modal = document.getElementById('confirm-modal');
    if (modal) modal.addEventListener('click', function(e) { if (e.target === modal) echoConfirmReject(); });

    var dlg = document.getElementById('confirm-dialog');
    if (!dlg) return;
    var dragging = false, dx = 0, dy = 0, ix = 0, iy = 0;
    dlg.style.position = 'relative';
    dlg.addEventListener('mousedown', function(e) {
        if (e.target.closest('button,input,select,textarea')) return;
        dragging = true;
        ix = e.clientX - (parseInt(dlg.style.left) || 0);
        iy = e.clientY - (parseInt(dlg.style.top)  || 0);
        dlg.style.userSelect = 'none';
        dlg.style.cursor = 'grabbing';
    });
    document.addEventListener('mousemove', function(e) {
        if (!dragging) return;
        dlg.style.left = (e.clientX - ix) + 'px';
        dlg.style.top  = (e.clientY - iy) + 'px';
    });
    document.addEventListener('mouseup', function() {
        if (dragging) { dragging = false; dlg.style.cursor = ''; dlg.style.userSelect = ''; }
    });
});

(function() {
    function makeDraggable(overlay) {
        var card = overlay.querySelector('.modal-card');
        if (!card || card._draggable) return;
        card._draggable = true;

        var dragging = false, startX, startY, origLeft, origTop;

        card.addEventListener('mousedown', function(e) {
            if (e.target.closest('button,input,select,textarea,a,.vm-mode-opt,.vm-preset-card')) return;
            dragging = true;
            card.style.cursor = 'grabbing';
            var rect = card.getBoundingClientRect();
            card.style.position  = 'fixed';
            card.style.margin    = '0';
            card.style.left      = rect.left + 'px';
            card.style.top       = rect.top  + 'px';
            startX = e.clientX; startY = e.clientY;
            origLeft = rect.left; origTop = rect.top;
            e.preventDefault();
        });

        document.addEventListener('mousemove', function(e) {
            if (!dragging) return;
            var nx = origLeft + e.clientX - startX;
            var ny = origTop  + e.clientY - startY;
            nx = Math.max(0, Math.min(nx, window.innerWidth  - card.offsetWidth));
            ny = Math.max(0, Math.min(ny, window.innerHeight - card.offsetHeight));
            card.style.left = nx + 'px';
            card.style.top  = ny + 'px';
        });

        document.addEventListener('mouseup', function() {
            if (dragging) { dragging = false; card.style.cursor = ''; }
        });
    }

    function resetCardPosition(overlay) {
        var card = overlay.querySelector('.modal-card');
        if (!card) return;
        card.style.position = '';
        card.style.left     = '';
        card.style.top      = '';
        card.style.margin   = '';
        card.style.cursor   = '';
    }

    var _origOpen = {
        create:    window.openCreateVm,
        deleteVm:  window.openDeleteVm,
        assign:    window.openAssignUsers,
        launch:    window.openVmViewer,
    };

    function wrapOpen(fnName, overlayId) {
        var orig = window[fnName];
        if (!orig) return;
        window[fnName] = function() {
            orig.apply(this, arguments);
            var ov = document.getElementById(overlayId);
            if (ov) { resetCardPosition(ov); makeDraggable(ov); }
        };
    }

    document.addEventListener('DOMContentLoaded', function() {
        wrapOpen('openCreateVm',    'create-vm-modal');
        wrapOpen('openDeleteVm',    'delete-vm-modal');
        wrapOpen('openAssignUsers', 'assign-users-modal');

        document.querySelectorAll('.modal-overlay').forEach(function(ov) {
            makeDraggable(ov);
        });

        document.querySelectorAll('.modal-overlay').forEach(function(ov) {
            var moved = false;
            ov.addEventListener('mousedown', function(e) { moved = false; });
            ov.addEventListener('mousemove', function()  { moved = true; });
            ov.addEventListener('click', function(e) {
                if (!moved && e.target === ov) {
                    var id = ov.id;
                    if      (id === 'create-vm-modal')    closeCreateVm();
                    else if (id === 'delete-vm-modal')    closeDeleteVm();
                    else if (id === 'assign-users-modal') closeAssignUsers();
                }
            });
        });
    });
})();

function escHtml(s) { var d=document.createElement('div'); d.textContent=s||''; return d.innerHTML; }

// Convertir a plantilla
function convertToTemplate(echoVmId, pxVmid, vmName) {
    echoConfirm(
        'Convert to Template',
        'Convert <b>' + escHtml(vmName) + '</b> to a Proxmox template?<br><br>After this the VM itself cannot be started — it becomes a base for student clones. <b>This cannot be undone.</b>',
        { okLabel: 'Convert', danger: true }
    ).then(function(ok) { if (!ok) return;
    showToast('success', 'Converting to template…');
    fetch('api/vm_proxmox.php', {
        method: 'POST', headers: {'Content-Type':'application/json'},
        body: JSON.stringify({action:'make_template', proxmox_vmid: pxVmid, echo_vm_id: echoVmId})
    }).then(r => r.json()).then(d => {
        if (d.ok) { showToast('success', '"' + vmName + '" is now a template.'); setTimeout(() => location.reload(), 1200); }
        else showToast('error', d.error || 'Conversion failed');
    }).catch(() => showToast('error', 'Network error'));
    }); // echoConfirm
}

function proxmoxCreate(vmId) {
    echoConfirm('Create VM in Proxmox', 'Register this VM in Proxmox and assign a VMID?', { okLabel: 'Create', danger: false }).then(function(ok) { if (!ok) return;
    fetch('api/vm_proxmox.php', {
        method: 'POST', headers: {'Content-Type':'application/json'},
        body: JSON.stringify({action:'create', vm_id: vmId})
    }).then(r => r.json()).then(d => {
        if (d.ok) { showToast('success', 'Created in Proxmox — VMID: ' + d.vmid); setTimeout(() => location.reload(), 1500); }
        else showToast('error', d.error || 'Proxmox error');
    }).catch(() => showToast('error', 'Network error'));
    }); // echoConfirm
}

// Panel de copias
var _copiesCache = {};
var _copiesOpen = {};

function toggleCopiesPanel(vmId) {
    var panel   = document.getElementById('copies-panel-' + vmId);
    var row     = document.getElementById('vm-card-' + vmId);
    if (!panel) return;
    var isOpen = _copiesOpen[vmId];
    _copiesOpen[vmId] = !isOpen;
    if (isOpen) {
        panel.style.display = 'none';
        if (row) row.classList.remove('open');
    } else {
        panel.style.display = 'block';
        if (row) row.classList.add('open');
        loadCopies(vmId);
    }
}

function loadCopies(vmId) {
    var list = document.getElementById('copies-list-' + vmId);
    if (!list) return;
    list.innerHTML = '<div style="text-align:center;padding:.75rem;color:var(--text-3);font-size:.72rem;">Loading…</div>';
    
    fetch('api/vm_clones.php?template_id=' + vmId)
        .then(function(r) { return r.json(); })
        .then(function(d) {
            _copiesCache[vmId] = d.clones || [];
            renderCopies(vmId);
        })
        .catch(function() {
            list.innerHTML = '<div style="text-align:center;padding:.75rem;color:var(--red);font-size:.72rem;">Failed to load</div>';
        });
}

function renderCopies(vmId) {
    var list   = document.getElementById('copies-list-' + vmId);
    var clones = _copiesCache[vmId] || [];
    if (!list) return;
    var countEl = document.getElementById('copies-count-' + vmId);
    if (countEl) countEl.textContent = clones.length ? clones.length + ' student' + (clones.length !== 1 ? 's' : '') : '';
    if (clones.length === 0) {
        list.innerHTML = '<div style="text-align:center;padding:1rem;color:var(--text-3);font-size:.72rem;">No students assigned yet — use the assign button above.</div>';
        return;
    }
    var html = '';
    for (var i = 0; i < clones.length; i++) {
        var c = clones[i];
        var initials    = (c.username || '?').slice(0,2).toUpperCase();
        var isRunning   = c.status === 'running';
        var statusCls   = isRunning ? 'running' : 'stopped';
        var statusLbl   = isRunning ? '<?= t("vm_status_running") ?>' : '<?= t("vm_status_stopped") ?>';
        var pveChip     = c.proxmox_vmid ? '<span style="font-size:.52rem;font-family:var(--mono);color:var(--green);background:var(--green-bg);border:1px solid rgba(16,185,129,.2);border-radius:var(--r-pill);padding:.03rem .28rem;">PVE#' + c.proxmox_vmid + '</span>' : '';
        var roleChip    = c.role_label ? '<span style="font-size:.52rem;color:var(--text-3);background:var(--surface3);border-radius:var(--r-pill);padding:.03rem .28rem;">' + escHtml(c.role_label) + '</span>' : '';
        var meChip      = c.is_me ? '<span style="font-size:.52rem;font-weight:700;color:var(--accent);background:var(--accent-bg);border:1px solid var(--accent-border);border-radius:var(--r-pill);padding:.03rem .28rem;">You</span>' : '';
        var launchBtn   = '<button class="icon-btn" style="width:22px;height:22px;color:var(--green);border-color:rgba(16,185,129,.25);" title="Open VM" onclick="openVmViewer(' + c.id + ',\'' + escHtml(c.username) + '\')"><svg width="8" height="8" viewBox="0 0 24 24" fill="currentColor" stroke="none"><polygon points="5 3 19 12 5 21 5 3"/></svg></button>';
        var killBtn     = isRunning ? '<button class="icon-btn" style="width:22px;height:22px;color:var(--orange);border-color:rgba(245,158,11,.25);" title="Kill" onclick="killStudentVm(' + vmId + ',' + c.id + ',' + (c.proxmox_vmid || 0) + ',this)"><svg width="8" height="8" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><circle cx="12" cy="12" r="10"/><line x1="4.93" y1="4.93" x2="19.07" y2="19.07"/></svg></button>' : '';
        var removeBtn   = '<button class="icon-btn danger" style="width:22px;height:22px;" title="Remove assignment" onclick="removeStudentAssignment(' + vmId + ',' + c.id + ',this)"><svg width="8" height="8" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button>';
        html += '<div class="vm-copy-row" id="clone-row-' + c.id + '" data-username="' + escHtml(c.username).toLowerCase() + '">' +
            '<div class="vm-copy-avatar" style="' + (c.is_me ? 'background:var(--accent-bg);border-color:var(--accent-border);color:var(--accent);' : '') + '">' + initials + '</div>' +
            '<div class="vm-copy-name">' + escHtml(c.username) + '</div>' +
            '<div style="display:flex;gap:2px;align-items:center;">' + meChip + roleChip + pveChip + '</div>' +
            '<span class="vm-copy-status ' + statusCls + '">' + statusLbl + '</span>' +
            '<div class="vm-copy-actions" style="gap:2px;">' + launchBtn + killBtn + removeBtn + '</div>' +
        '</div>';
    }
    list.innerHTML = html;
}

function removeStudentAssignment(templateId, cloneId, btn) {
    echoConfirm('Remove Assignment', 'Delete this student\'s VM clone? This cannot be undone.', { okLabel: 'Delete', danger: true }).then(function(ok) { if (!ok) return;
    if (btn) { btn.disabled = true; btn.style.opacity = '.4'; }
    fetch('api/vm_clones.php', {
        method: 'POST', headers: {'Content-Type':'application/json'},
        body: JSON.stringify({template_id: templateId, clone_id: cloneId, action: 'delete'})
    }).then(function(r){return r.json();}).then(function(d){
        if (d.ok) {
            _copiesCache[templateId] = (_copiesCache[templateId]||[]).filter(function(c){return c.id!==cloneId;});
            var row = document.getElementById('clone-row-'+cloneId);
            if (row) { row.style.opacity='0'; row.style.transition='opacity .2s'; setTimeout(function(){renderCopies(templateId);},220); }
            else renderCopies(templateId);
            showToast('success','Assignment removed.');
        } else {
            if (btn){btn.disabled=false;btn.style.opacity='';}
            showToast('error',d.error||'Failed');
        }
    }).catch(function(){ if(btn){btn.disabled=false;btn.style.opacity='';}showToast('error','Network error'); });
    }); // echoConfirm
}

function filterCopies(vmId, query) {
    var rows = document.querySelectorAll('#copies-list-' + vmId + ' .vm-copy-row');
    var q = query.toLowerCase().trim();
    for (var i = 0; i < rows.length; i++) {
        var row = rows[i];
        var username = row.dataset.username || '';
        if (q === '' || username.indexOf(q) !== -1) {
            row.classList.remove('hidden');
        } else {
            row.classList.add('hidden');
        }
    }
}

function killStudentVm(templateId, cloneId, pxVmid, btn) {
    if (btn) { btn.disabled = true; btn.style.opacity = '.4'; }
    fetch('api/vm_clones.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({template_id: templateId, clone_id: cloneId, action: 'kill', proxmox_vmid: pxVmid})
    }).then(function(r) { return r.json(); }).then(function(d) {
        if (d.ok) {
            var clones = _copiesCache[templateId] || [];
            for (var i = 0; i < clones.length; i++) {
                if (clones[i].id === cloneId) {
                    clones[i].status = 'stopped';
                    break;
                }
            }
            renderCopies(templateId);
            showToast('success', 'VM stopped.');
        } else {
            if (btn) { btn.disabled = false; btn.style.opacity = ''; }
            showToast('error', d.error || 'Failed');
        }
    }).catch(function() {
        if (btn) { btn.disabled = false; btn.style.opacity = ''; }
        showToast('error', 'Network error');
    });
}

</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
