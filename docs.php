<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/lang.php';
require_once __DIR__ . '/api/token_auth.php';

$currentLang = getCurrentLang();
$me          = currentUser();

// Solo profesores (rol >= 3) y superiores pueden acceder a los documentos de la API
if (!$me || (int)$me['role'] < ROLE_TEACHER) {
    http_response_code(403);
    $pageTitle = t('docs_title');
    require_once __DIR__ . '/includes/header.php';
    echo '<div class="main-content" style="display:flex;align-items:center;justify-content:center;min-height:60vh;">';
    echo '<div style="text-align:center;">';
    echo '<div style="font-size:2.5rem;margin-bottom:.75rem;">🔒</div>';
    echo '<h2 style="font-size:1.1rem;margin-bottom:.5rem;">' . t('docs_access_denied') . '</h2>';
    echo '<p style="color:var(--text-3);font-size:.85rem;margin-bottom:1.25rem;">' . t('docs_access_denied_desc') . '</p>';
    echo '<a href="index.php" class="btn btn-sm btn-outline">' . t('nav_dashboard') . '</a>';
    echo '</div></div>';
    require_once __DIR__ . '/includes/footer.php';
    exit;
}

$myTokens    = getUserApiTokens((int)$me['id']);
$pageTitle   = t('docs_title');
$wideLayout  = true;
require_once __DIR__ . '/includes/header.php';

$base = rtrim(APP_URL, '/');
?>

<style>
/* Docs layout */
.docs-wrap {
    display: grid;
    grid-template-columns: 220px 1fr;
    gap: 2.5rem;
    align-items: start;
    max-width: 1100px;
    margin: 0 auto;
    padding: 2rem 1.5rem 4rem;
}
/* Sidebar */
.docs-sidebar {
    position: sticky;
    top: 72px;
    background: var(--surface);
    border: 1px solid var(--sep);
    border-radius: var(--r);
    padding: .85rem;
    font-size: .78rem;
}
.docs-sidebar-title {
    font-size: .6rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .08em;
    color: var(--text-3);
    padding: .25rem .45rem .55rem;
    margin-bottom: .2rem;
}
.docs-nav-link {
    display: block;
    padding: .35rem .55rem;
    border-radius: var(--r-sm);
    color: var(--text-2);
    text-decoration: none;
    transition: all .12s;
    font-weight: 500;
    line-height: 1.4;
}
.docs-nav-link:hover { background: var(--surface2); color: var(--text); }
.docs-nav-link.active { background: var(--accent-bg); color: var(--accent); }
.docs-nav-section { margin-top: .6rem; }
.docs-nav-section-label {
    font-size: .6rem; font-weight: 700; text-transform: uppercase;
    letter-spacing: .07em; color: var(--text-4); padding: .3rem .55rem .1rem;
}
/* Content */
.docs-content { min-width: 0; }
.docs-section { margin-bottom: 3.5rem; scroll-margin-top: 80px; }
.docs-section h2 {
    font-size: 1.3rem; font-weight: 700; letter-spacing: -.025em;
    color: var(--text); margin-bottom: .4rem; padding-bottom: .6rem;
    border-bottom: 1px solid var(--sep);
}
.docs-section h3 {
    font-size: .95rem; font-weight: 700; color: var(--text);
    margin: 1.4rem 0 .5rem;
}
.docs-section h4 {
    font-size: .82rem; font-weight: 600; color: var(--text-2);
    margin: 1rem 0 .35rem; text-transform: uppercase; letter-spacing: .05em; font-size: .68rem;
}
.docs-section p { font-size: .88rem; color: var(--text-2); line-height: 1.75; margin-bottom: .75rem; }
.docs-section ul { padding-left: 1.4rem; margin-bottom: .75rem; }
.docs-section li { font-size: .88rem; color: var(--text-2); line-height: 1.75; margin-bottom: .2rem; }
.docs-section li strong { color: var(--text); }
/* Code blocks */
.docs-pre {
    background: var(--surface);
    border: 1px solid var(--sep-bold);
    border-radius: var(--r-sm);
    padding: 1rem 1.1rem;
    overflow-x: auto;
    margin: .6rem 0 1rem;
    position: relative;
}
.docs-pre code {
    font-family: var(--mono);
    font-size: .8rem;
    color: var(--text-2);
    background: none;
    border: none;
    padding: 0;
    white-space: pre;
    line-height: 1.7;
}
.docs-pre .hl-key   { color: var(--accent); }
.docs-pre .hl-str   { color: var(--green); }
.docs-pre .hl-num   { color: var(--orange); }
.docs-pre .hl-cmt   { color: var(--text-3); font-style: italic; }
.docs-pre .hl-met   { color: #60a5fa; }
.docs-lang {
    position: absolute; top: .55rem; right: .7rem;
    font-size: .55rem; font-weight: 700; letter-spacing: .06em;
    text-transform: uppercase; color: var(--text-4);
    font-family: var(--mono);
}
.docs-copy {
    position: absolute; top: .45rem; right: 3.5rem;
    font-size: .62rem; padding: .15rem .4rem;
    background: var(--surface2); border: 1px solid var(--sep);
    border-radius: 4px; color: var(--text-3); cursor: pointer;
    font-family: var(--font); transition: all .12s;
}
.docs-copy:hover { color: var(--text); border-color: var(--sep-bold); }
/* Method badges */
.method {
    display: inline-block;
    font-size: .65rem; font-weight: 700; letter-spacing: .05em;
    padding: .14rem .42rem; border-radius: 4px; margin-right: .45rem;
    font-family: var(--mono);
}
.method-get  { background: rgba(34,201,126,.12); color: var(--green); border: 1px solid rgba(34,201,126,.2); }
.method-post { background: rgba(96,165,250,.12); color: #60a5fa; border: 1px solid rgba(96,165,250,.2); }
.method-del  { background: var(--red-bg); color: var(--red); border: 1px solid rgba(240,74,110,.2); }
/* Endpoint card */
.endpoint {
    background: var(--surface);
    border: 1px solid var(--sep);
    border-radius: var(--r);
    margin-bottom: 1.2rem;
    overflow: hidden;
}
.endpoint-header {
    display: flex; align-items: center; gap: .6rem;
    padding: .75rem 1rem;
    background: var(--surface2);
    border-bottom: 1px solid var(--sep);
    font-family: var(--mono); font-size: .82rem;
}
.endpoint-path { color: var(--text); font-weight: 500; }
.endpoint-desc { color: var(--text-3); font-size: .75rem; margin-left: auto; }
.endpoint-body { padding: .9rem 1rem; }
.endpoint-body p { font-size: .83rem; color: var(--text-2); margin-bottom: .5rem; }
/* Params table */
.params-table { width: 100%; border-collapse: collapse; font-size: .8rem; margin: .5rem 0; }
.params-table th { text-align: left; padding: .35rem .65rem; background: var(--surface3); color: var(--text-3); font-size: .62rem; text-transform: uppercase; letter-spacing: .06em; font-weight: 600; }
.params-table td { padding: .4rem .65rem; border-bottom: 1px solid var(--sep); color: var(--text-2); vertical-align: top; }
.params-table td:first-child { color: var(--accent); font-family: var(--mono); font-size: .75rem; font-weight: 500; }
.params-table td:last-child { color: var(--text-3); font-size: .77rem; }
.params-table tr:last-child td { border-bottom: none; }
/* Alert boxes */
.docs-alert {
    display: flex; gap: .65rem; align-items: flex-start;
    padding: .75rem .9rem; border-radius: var(--r-sm); margin: .75rem 0;
    font-size: .82rem; line-height: 1.6;
}
.docs-alert-info  { background: var(--accent-bg); border: 1px solid var(--accent-border); color: var(--text-2); }
.docs-alert-warn  { background: var(--orange-bg); border: 1px solid rgba(245,158,11,.2); color: var(--text-2); }
.docs-alert-ok    { background: var(--green-bg);  border: 1px solid rgba(34,201,126,.18); color: var(--text-2); }
.docs-alert svg   { flex-shrink: 0; margin-top: .1rem; }
/* Role table */
.role-table { width: 100%; border-collapse: collapse; font-size: .82rem; margin: .5rem 0 1rem; }
.role-table th { text-align: left; padding: .38rem .65rem; background: var(--surface3); color: var(--text-3); font-size: .62rem; text-transform: uppercase; letter-spacing: .06em; }
.role-table td { padding: .42rem .65rem; border-bottom: 1px solid var(--sep); color: var(--text-2); }
.role-table tr:last-child td { border-bottom: none; }
/* Token manager */
.token-manager { background: var(--surface); border: 1px solid var(--sep); border-radius: var(--r); overflow: hidden; margin-top: 1rem; }
.token-manager-header { padding: .75rem 1rem; background: var(--surface2); border-bottom: 1px solid var(--sep); display: flex; align-items: center; justify-content: space-between; }
.token-manager-header h4 { font-size: .82rem; font-weight: 600; margin: 0; color: var(--text); }
.token-item { display: flex; align-items: center; gap: .75rem; padding: .65rem 1rem; border-bottom: 1px solid var(--sep); }
.token-item:last-child { border-bottom: none; }
.token-name { font-size: .82rem; font-weight: 500; color: var(--text); flex: 1; }
.token-date { font-size: .68rem; color: var(--text-3); }
.token-val { font-family: var(--mono); font-size: .7rem; color: var(--accent); background: var(--accent-bg); padding: .15rem .5rem; border-radius: 4px; border: 1px solid var(--accent-border); letter-spacing: .03em; }
.token-empty { padding: 1.2rem; text-align: center; color: var(--text-3); font-size: .82rem; }
.new-token-flash { background: var(--green-bg); border: 1px solid rgba(34,201,126,.2); border-radius: var(--r-sm); padding: .75rem 1rem; margin: .75rem 0; font-size: .82rem; }
.new-token-flash code { color: var(--green); font-size: .82rem; word-break: break-all; }
.new-token-flash p { color: var(--text-2); margin-bottom: .35rem; }
/* Responsive */
@media(max-width: 800px) {
    .docs-wrap { grid-template-columns: 1fr; }
    .docs-sidebar { position: static; }
}
</style>

<div class="docs-wrap">

    <!-- ── Sidebar ── -->
    <aside class="docs-sidebar">
        <div class="docs-sidebar-title"><?= t('docs_nav_title') ?></div>

        <div class="docs-nav-section">
            <div class="docs-nav-section-label"><?= t('docs_nav_start') ?></div>
            <a href="#intro"  class="docs-nav-link"><?= t('docs_nav_intro') ?></a>
            <a href="#auth"   class="docs-nav-link"><?= t('docs_nav_auth') ?></a>
            <a href="#tokens" class="docs-nav-link"><?= t('docs_nav_tokens') ?></a>
            <a href="#errors" class="docs-nav-link"><?= t('docs_nav_errors') ?></a>
        </div>

        <div class="docs-nav-section">
            <div class="docs-nav-section-label"><?= t('docs_nav_endpoints') ?></div>
            <a href="#ep-me"     class="docs-nav-link">GET /api/me</a>
            <a href="#ep-vms"    class="docs-nav-link">GET /api/vms</a>
            <a href="#ep-status" class="docs-nav-link">POST /api/vm_status</a>
            <a href="#ep-clones" class="docs-nav-link">GET /api/vm_clones</a>
        </div>

        <div class="docs-nav-section">
            <div class="docs-nav-section-label"><?= t('docs_nav_guides') ?></div>
            <a href="#guide-php"  class="docs-nav-link">PHP</a>
            <a href="#guide-js"   class="docs-nav-link">JavaScript / Fetch</a>
            <a href="#guide-cors" class="docs-nav-link">CORS</a>
        </div>

        <?php if ($me): ?>
        <div class="docs-nav-section" style="margin-top:1rem;padding-top:.75rem;border-top:1px solid var(--sep);">
            <a href="#my-tokens" class="docs-nav-link" style="color:var(--accent);font-weight:600;">
                🔑 <?= t('docs_nav_my_tokens') ?>
            </a>
            <a href="echo_api_demo.php" download class="docs-nav-link" style="font-size:.73rem;color:var(--green);">
                ↓ <?= t('docs_download_demo') ?>
            </a>
        </div>
        <?php endif; ?>
    </aside>

    <!-- Main content -->
    <main class="docs-content">

        <!-- Intro -->
        <section class="docs-section" id="intro">
            <h2><?= t('docs_intro_title') ?></h2>
            <p><?= t('docs_intro_p1') ?></p>
            <p><?= t('docs_intro_p2') ?></p>

            <div class="docs-alert docs-alert-info">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="var(--accent)" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                <span><?= t('docs_intro_base_label') ?> <code><?= htmlspecialchars($base) ?></code></span>
            </div>

            <h3><?= t('docs_formats_title') ?></h3>
            <p><?= t('docs_formats_desc') ?></p>
            <div class="docs-pre">
                <span class="docs-lang">JSON</span>
                <code><span class="hl-cmt">// Respuesta exitosa</span>
{ <span class="hl-str">"ok"</span>: <span class="hl-num">true</span>, <span class="hl-str">"data"</span>: { ... } }

<span class="hl-cmt">// Respuesta de error</span>
{ <span class="hl-str">"ok"</span>: <span class="hl-num">false</span>, <span class="hl-str">"error"</span>: <span class="hl-str">"Descripción del error"</span> }</code>
            </div>
        </section>

        <!-- Auth -->
        <section class="docs-section" id="auth">
            <h2><?= t('docs_auth_title') ?></h2>
            <p><?= t('docs_auth_p1') ?></p>

            <h3><?= t('docs_auth_method1') ?></h3>
            <p><?= t('docs_auth_method1_desc') ?></p>
            <div class="docs-pre">
                <span class="docs-lang">HTTP</span>
                <code><span class="hl-key">Authorization</span>: Bearer <span class="hl-str">tu_token_aqui</span></code>
            </div>

            <h3><?= t('docs_auth_method2') ?></h3>
            <p><?= t('docs_auth_method2_desc') ?></p>
            <div class="docs-pre">
                <span class="docs-lang">URL</span>
                <code><?= htmlspecialchars($base) ?>/api/me.php<span class="hl-key">?token=</span><span class="hl-str">tu_token_aqui</span></code>
            </div>

            <h3><?= t('docs_auth_method3') ?></h3>
            <p><?= t('docs_auth_method3_desc') ?></p>
            <div class="docs-pre">
                <span class="docs-lang">JSON body</span>
                <code>{
  <span class="hl-str">"token"</span>: <span class="hl-str">"tu_token_aqui"</span>,
  <span class="hl-str">"vm_id"</span>: <span class="hl-num">5</span>,
  <span class="hl-str">"action"</span>: <span class="hl-str">"start"</span>
}</code>
            </div>

            <h3><?= t('docs_roles_title') ?></h3>
            <p><?= t('docs_roles_desc') ?></p>
            <table class="role-table">
                <thead><tr><th><?= t('docs_roles_col_val') ?></th><th><?= t('docs_roles_col_name') ?></th><th><?= t('docs_roles_col_desc') ?></th></tr></thead>
                <tbody>
                    <tr><td>0</td><td><code>ROLE_BANNED</code></td><td><?= t('role_banned_desc') ?></td></tr>
                    <tr><td>1</td><td><code>ROLE_USER</code></td><td><?= t('role_user_desc') ?></td></tr>
                    <tr><td>2</td><td><code>ROLE_STUDENT</code></td><td><?= t('role_student_desc') ?></td></tr>
                    <tr><td>3</td><td><code>ROLE_TEACHER</code></td><td><?= t('role_teacher_desc') ?></td></tr>
                    <tr><td>4</td><td><code>ROLE_CENTER_ADMIN</code></td><td><?= t('role_center_desc') ?></td></tr>
                    <tr><td>5</td><td><code>ROLE_ADMIN</code></td><td><?= t('role_admin_desc') ?></td></tr>
                </tbody>
            </table>
        </section>

        <!-- Tokens -->
        <section class="docs-section" id="tokens">
            <h2><?= t('docs_tokens_title') ?></h2>
            <p><?= t('docs_tokens_p1') ?></p>

            <div class="docs-alert docs-alert-warn">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="var(--orange)" stroke-width="2" stroke-linecap="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                <span><?= t('docs_tokens_warn') ?></span>
            </div>

            <h3><?= t('docs_tokens_create_title') ?></h3>
            <p><?= t('docs_tokens_create_desc') ?></p>

            <div class="endpoint">
                <div class="endpoint-header">
                    <span class="method method-post">POST</span>
                    <span class="endpoint-path">/api/tokens.php</span>
                    <span class="endpoint-desc"><?= t('docs_tokens_create_endpoint_desc') ?></span>
                </div>
                <div class="endpoint-body">
                    <div class="docs-pre">
                        <span class="docs-lang">JSON</span>
                        <code>{ <span class="hl-str">"action"</span>: <span class="hl-str">"create"</span>, <span class="hl-str">"name"</span>: <span class="hl-str">"Mi Sitio de Cursos"</span> }</code>
                    </div>
                    <p><?= t('docs_tokens_response') ?>:</p>
                    <div class="docs-pre">
                        <code>{ <span class="hl-str">"ok"</span>: <span class="hl-num">true</span>, <span class="hl-str">"token"</span>: <span class="hl-str">"a3f2bc..."</span>, <span class="hl-str">"name"</span>: <span class="hl-str">"Mi Sitio de Cursos"</span> }</code>
                    </div>
                </div>
            </div>
        </section>

        <!-- Errors -->
        <section class="docs-section" id="errors">
            <h2><?= t('docs_errors_title') ?></h2>
            <p><?= t('docs_errors_desc') ?></p>
            <table class="params-table">
                <thead><tr><th><?= t('docs_errors_col_code') ?></th><th><?= t('docs_errors_col_meaning') ?></th></tr></thead>
                <tbody>
                    <tr><td>200</td><td><?= t('docs_err_200') ?></td></tr>
                    <tr><td>401</td><td><?= t('docs_err_401') ?></td></tr>
                    <tr><td>403</td><td><?= t('docs_err_403') ?></td></tr>
                    <tr><td>405</td><td><?= t('docs_err_405') ?></td></tr>
                </tbody>
            </table>
        </section>

        <!-- Endpoint: me -->
        <section class="docs-section" id="ep-me">
            <h2><?= t('docs_ep_me_title') ?></h2>
            <p><?= t('docs_ep_me_desc') ?></p>

            <div class="endpoint">
                <div class="endpoint-header">
                    <span class="method method-get">GET</span>
                    <span class="endpoint-path">/api/me.php</span>
                    <span class="endpoint-desc"><?= t('docs_ep_me_short') ?></span>
                </div>
                <div class="endpoint-body">
                    <p><?= t('docs_ep_me_body') ?></p>
                    <div class="docs-pre">
                        <span class="docs-lang">Response</span>
                        <code>{
  <span class="hl-str">"ok"</span>:        <span class="hl-num">true</span>,
  <span class="hl-str">"id"</span>:        <span class="hl-num">7</span>,
  <span class="hl-str">"username"</span>:  <span class="hl-str">"maria"</span>,
  <span class="hl-str">"email"</span>:     <span class="hl-str">"maria@echo.dev"</span>,
  <span class="hl-str">"role"</span>:      <span class="hl-num">3</span>,
  <span class="hl-str">"role_name"</span>: <span class="hl-str">"Teacher"</span>,
  <span class="hl-str">"center_id"</span>: <span class="hl-num">2</span>,
  <span class="hl-str">"lang"</span>:      <span class="hl-str">"es"</span>
}</code>
                    </div>
                </div>
            </div>
        </section>

        <!-- Endpoint: vms -->
        <section class="docs-section" id="ep-vms">
            <h2><?= t('docs_ep_vms_title') ?></h2>
            <p><?= t('docs_ep_vms_desc') ?></p>

            <div class="endpoint">
                <div class="endpoint-header">
                    <span class="method method-get">GET</span>
                    <span class="endpoint-path">/api/vms.php</span>
                    <span class="endpoint-desc"><?= t('docs_ep_vms_short') ?></span>
                </div>
                <div class="endpoint-body">
                    <p><?= t('docs_ep_vms_body') ?></p>
                    <div class="docs-pre">
                        <span class="docs-lang">Response</span>
                        <code>{
  <span class="hl-str">"ok"</span>: <span class="hl-num">true</span>,
  <span class="hl-str">"count"</span>: <span class="hl-num">2</span>,
  <span class="hl-str">"vms"</span>: [
    {
      <span class="hl-str">"id"</span>:          <span class="hl-num">3</span>,
      <span class="hl-str">"name"</span>:        <span class="hl-str">"Ubuntu 24.04"</span>,
      <span class="hl-str">"status"</span>:      <span class="hl-str">"running"</span>,
      <span class="hl-str">"os_type"</span>:     <span class="hl-str">"ubuntu-24.04.iso"</span>,
      <span class="hl-str">"ram_mb"</span>:      <span class="hl-num">4096</span>,
      <span class="hl-str">"cpu_cores"</span>:   <span class="hl-num">2</span>,
      <span class="hl-str">"disk_gb"</span>:     <span class="hl-num">40</span>,
      <span class="hl-str">"is_template"</span>: <span class="hl-num">false</span>,
      <span class="hl-str">"template_id"</span>: <span class="hl-num">1</span>,
      <span class="hl-str">"assigned_to"</span>: <span class="hl-num">7</span>,
      <span class="hl-str">"last_active"</span>: <span class="hl-str">"2024-01-15 10:23:00"</span>
    }
  ]
}</code>
                    </div>
                </div>
            </div>
        </section>

        <!-- Endpoint: vm_status -->
        <section class="docs-section" id="ep-status">
            <h2><?= t('docs_ep_status_title') ?></h2>
            <p><?= t('docs_ep_status_desc') ?></p>

            <div class="endpoint">
                <div class="endpoint-header">
                    <span class="method method-get">GET</span>
                    <span class="endpoint-path">/api/vm_status.php?vm_id={id}</span>
                    <span class="endpoint-desc"><?= t('docs_ep_status_get_short') ?></span>
                </div>
                <div class="endpoint-body">
                    <div class="docs-pre">
                        <code>{ <span class="hl-str">"ok"</span>: <span class="hl-num">true</span>, <span class="hl-str">"status"</span>: <span class="hl-str">"stopped"</span>, <span class="hl-str">"last_active"</span>: <span class="hl-str">"2024-01-15 10:00:00"</span> }</code>
                    </div>
                </div>
            </div>

            <div class="endpoint" style="margin-top:.75rem;">
                <div class="endpoint-header">
                    <span class="method method-post">POST</span>
                    <span class="endpoint-path">/api/vm_status.php</span>
                    <span class="endpoint-desc"><?= t('docs_ep_status_post_short') ?></span>
                </div>
                <div class="endpoint-body">
                    <table class="params-table">
                        <thead><tr><th><?= t('docs_param') ?></th><th><?= t('docs_type') ?></th><th><?= t('docs_description') ?></th></tr></thead>
                        <tbody>
                            <tr><td>vm_id</td><td>integer</td><td><?= t('docs_status_param_vmid') ?></td></tr>
                            <tr><td>action</td><td>string</td><td><code>start</code> | <code>stop</code> | <code>heartbeat</code></td></tr>
                            <tr><td>token</td><td>string</td><td><?= t('docs_param_token_optional') ?></td></tr>
                        </tbody>
                    </table>
                    <div class="docs-pre">
                        <span class="docs-lang">JSON</span>
                        <code>{ <span class="hl-str">"vm_id"</span>: <span class="hl-num">3</span>, <span class="hl-str">"action"</span>: <span class="hl-str">"start"</span>, <span class="hl-str">"token"</span>: <span class="hl-str">"a3f2bc..."</span> }</code>
                    </div>
                    <div class="docs-pre">
                        <span class="docs-lang">Response</span>
                        <code>{ <span class="hl-str">"ok"</span>: <span class="hl-num">true</span>, <span class="hl-str">"status"</span>: <span class="hl-str">"running"</span> }</code>
                    </div>
                    <div class="docs-alert docs-alert-warn">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="var(--orange)" stroke-width="2" stroke-linecap="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                        <span><?= t('docs_status_warn_perms') ?></span>
                    </div>
                </div>
            </div>
        </section>

        <!-- Endpoint: clones -->
        <section class="docs-section" id="ep-clones">
            <h2><?= t('docs_ep_clones_title') ?></h2>
            <p><?= t('docs_ep_clones_desc') ?></p>

            <div class="endpoint">
                <div class="endpoint-header">
                    <span class="method method-get">GET</span>
                    <span class="endpoint-path">/api/vm_clones.php?template_id={id}</span>
                    <span class="endpoint-desc"><?= t('docs_ep_clones_short') ?></span>
                </div>
                <div class="endpoint-body">
                    <p><?= t('docs_ep_clones_body') ?></p>
                    <div class="docs-pre">
                        <span class="docs-lang">Response</span>
                        <code>{
  <span class="hl-str">"ok"</span>: <span class="hl-num">true</span>,
  <span class="hl-str">"template_name"</span>: <span class="hl-str">"Ubuntu 24.04"</span>,
  <span class="hl-str">"clones"</span>: [
    {
      <span class="hl-str">"id"</span>:          <span class="hl-num">5</span>,
      <span class="hl-str">"username"</span>:   <span class="hl-str">"alumno1"</span>,
      <span class="hl-str">"role_label"</span>: <span class="hl-str">"Student"</span>,
      <span class="hl-str">"status"</span>:     <span class="hl-str">"stopped"</span>,
      <span class="hl-str">"is_me"</span>:      <span class="hl-num">false</span>
    }
  ]
}</code>
                    </div>
                </div>
            </div>
        </section>

        <!-- Guide: PHP -->
        <section class="docs-section" id="guide-php">
            <h2><?= t('docs_guide_php_title') ?></h2>
            <p><?= t('docs_guide_php_desc') ?></p>

            <h3><?= t('docs_guide_php_step1') ?></h3>
            <div class="docs-pre">
                <span class="docs-lang">PHP</span>
                <button class="docs-copy" onclick="copyCode(this)"><?= t('docs_copy') ?></button>
                <code><span class="hl-cmt">// echo_api.php — Helpers para conectar con echo</span>

<span class="hl-key">define</span>(<span class="hl-str">'ECHO_BASE'</span>, <span class="hl-str">'<?= htmlspecialchars($base) ?>'</span>);
<span class="hl-key">define</span>(<span class="hl-str">'ECHO_TOKEN'</span>, <span class="hl-str">'tu_token_aqui'</span>);

<span class="hl-key">function</span> <span class="hl-met">echo_request</span>(string $endpoint, array $params = [], string $method = <span class="hl-str">'GET'</span>): ?array {
    $url = ECHO_BASE . $endpoint;
    $opts = [
        <span class="hl-str">'http'</span> => [
            <span class="hl-str">'method'</span>  => $method,
            <span class="hl-str">'header'</span>  => <span class="hl-str">'Content-Type: application/json\r\nAuthorization: Bearer '</span> . ECHO_TOKEN,
            <span class="hl-str">'content'</span> => $method !== <span class="hl-str">'GET'</span> ? json_encode($params) : <span class="hl-str">''</span>,
            <span class="hl-str">'timeout'</span> => <span class="hl-num">8</span>,
        ]
    ];
    if ($method === <span class="hl-str">'GET'</span> && $params) $url .= <span class="hl-str">'?'</span> . http_build_query($params);
    $res = @file_get_contents($url, false, stream_context_create($opts));
    return $res ? json_decode($res, true) : null;
}

<span class="hl-cmt">// Ejemplos de uso:</span>

<span class="hl-cmt">// Verificar usuario</span>
$user = <span class="hl-met">echo_request</span>(<span class="hl-str">'/api/me.php'</span>);
if ($user && $user[<span class="hl-str">'ok'</span>]) {
    echo <span class="hl-str">"Hola, "</span> . $user[<span class="hl-str">'username'</span>] . <span class="hl-str">" (rol: "</span> . $user[<span class="hl-str">'role'</span>] . <span class="hl-str">")"</span>;
}

<span class="hl-cmt">// Listar mis VMs</span>
$data = <span class="hl-met">echo_request</span>(<span class="hl-str">'/api/vms.php'</span>);
foreach ($data[<span class="hl-str">'vms'</span>] as $vm) {
    echo $vm[<span class="hl-str">'name'</span>] . <span class="hl-str">" — "</span> . $vm[<span class="hl-str">'status'</span>];
}

<span class="hl-cmt">// Iniciar una VM</span>
$res = <span class="hl-met">echo_request</span>(<span class="hl-str">'/api/vm_status.php'</span>, [
    <span class="hl-str">'vm_id'</span>  => <span class="hl-num">3</span>,
    <span class="hl-str">'action'</span> => <span class="hl-str">'start'</span>,
], <span class="hl-str">'POST'</span>);</code>
            </div>

            <h3><?= t('docs_guide_php_gate_title') ?></h3>
            <p><?= t('docs_guide_php_gate_desc') ?></p>
            <div class="docs-pre">
                <span class="docs-lang">PHP</span>
                <button class="docs-copy" onclick="copyCode(this)"><?= t('docs_copy') ?></button>
                <code><span class="hl-cmt">// Al inicio de cualquier página protegida de tu sitio:</span>

$echoUser = <span class="hl-met">echo_request</span>(<span class="hl-str">'/api/me.php'</span>);

if (!$echoUser || !$echoUser[<span class="hl-str">'ok'</span>]) {
    <span class="hl-cmt">// No autenticado en echo → redirigir al login de echo</span>
    header(<span class="hl-str">'Location: <?= htmlspecialchars($base) ?>/login.php'</span>);
    exit;
}

if ($echoUser[<span class="hl-str">'role'</span>] < <span class="hl-num">2</span>) {
    <span class="hl-cmt">// Rol insuficiente (se necesita al menos Estudiante)</span>
    die(<span class="hl-str">'Acceso denegado.'</span>);
}

<span class="hl-cmt">// ✓ Usuario válido — continuar con tu lógica</span>
$nombre = $echoUser[<span class="hl-str">'username'</span>];</code>
            </div>
        </section>

        <!-- Guide: JavaScript -->
        <section class="docs-section" id="guide-js">
            <h2><?= t('docs_guide_js_title') ?></h2>
            <p><?= t('docs_guide_js_desc') ?></p>

            <div class="docs-pre">
                <span class="docs-lang">JavaScript</span>
                <button class="docs-copy" onclick="copyCode(this)"><?= t('docs_copy') ?></button>
                <code><span class="hl-cmt">// echo.js — Cliente para la API de echo</span>

<span class="hl-key">const</span> ECHO_BASE  = <span class="hl-str">'<?= htmlspecialchars($base) ?>'</span>;
<span class="hl-key">const</span> ECHO_TOKEN = <span class="hl-str">'tu_token_aqui'</span>;

<span class="hl-key">async function</span> <span class="hl-met">echoApi</span>(path, body = null) {
    <span class="hl-key">const</span> res = <span class="hl-key">await</span> fetch(ECHO_BASE + path, {
        method:  body ? <span class="hl-str">'POST'</span> : <span class="hl-str">'GET'</span>,
        headers: {
            <span class="hl-str">'Authorization'</span>: <span class="hl-str">`Bearer ${ECHO_TOKEN}`</span>,
            <span class="hl-str">'Content-Type'</span>:  <span class="hl-str">'application/json'</span>,
        },
        body: body ? JSON.stringify(body) : undefined,
    });
    <span class="hl-key">return</span> res.json();
}

<span class="hl-cmt">// Verificar sesión</span>
<span class="hl-key">const</span> user = <span class="hl-key">await</span> <span class="hl-met">echoApi</span>(<span class="hl-str">'/api/me.php'</span>);
console.log(user.username, user.role_name);

<span class="hl-cmt">// Listar VMs</span>
<span class="hl-key">const</span> { vms } = <span class="hl-key">await</span> <span class="hl-met">echoApi</span>(<span class="hl-str">'/api/vms.php'</span>);

<span class="hl-cmt">// Iniciar una VM</span>
<span class="hl-key">await</span> <span class="hl-met">echoApi</span>(<span class="hl-str">'/api/vm_status.php'</span>, { vm_id: <span class="hl-num">3</span>, action: <span class="hl-str">'start'</span> });</code>
            </div>
        </section>

        <!-- Guide: CORS -->
        <section class="docs-section" id="guide-cors">
            <h2><?= t('docs_guide_cors_title') ?></h2>
            <p><?= t('docs_guide_cors_p1') ?></p>
            <div class="docs-alert docs-alert-ok">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="var(--green)" stroke-width="2" stroke-linecap="round"><polyline points="20 6 9 17 4 12"/></svg>
                <span><?= t('docs_guide_cors_ok') ?></span>
            </div>
            <p><?= t('docs_guide_cors_p2') ?></p>
            <div class="docs-pre">
                <span class="docs-lang">PHP</span>
                <code><span class="hl-cmt">// Ya incluido en los endpoints públicos de echo:</span>
header(<span class="hl-str">'Access-Control-Allow-Origin: *'</span>);
header(<span class="hl-str">'Access-Control-Allow-Headers: Authorization, Content-Type'</span>);</code>
            </div>
            <div class="docs-alert docs-alert-warn">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="var(--orange)" stroke-width="2" stroke-linecap="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                <span><?= t('docs_guide_cors_warn') ?></span>
            </div>
        </section>

        <!-- Mis tokens -->
        <?php if ($me): ?>
        <section class="docs-section" id="my-tokens">
            <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:1rem;flex-wrap:wrap;margin-bottom:.75rem;">
                <div>
                    <h2 style="margin-bottom:.3rem;"><?= t('docs_my_tokens_title') ?></h2>
                    <p style="margin-bottom:0;"><?= t('docs_my_tokens_desc') ?></p>
                </div>
                <a href="echo_api_demo.php" download class="btn btn-sm btn-outline" style="flex-shrink:0;margin-top:.15rem;">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                    <?= t('docs_download_demo') ?>
                </a>
            </div>

            <div id="new-token-result"></div>

            <div style="display:flex;gap:.5rem;margin-bottom:1rem;flex-wrap:wrap;">
                <input type="text" id="token-name-input"
                       placeholder="<?= htmlspecialchars(t('docs_token_name_placeholder')) ?>"
                       style="flex:1;min-width:180px;padding:.45rem .7rem;background:var(--bg);border:1px solid var(--sep-bold);border-radius:var(--r-sm);color:var(--text);font-family:var(--font);font-size:.82rem;outline:none;">
                <button class="btn btn-sm" onclick="createToken()">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                    <?= t('docs_create_token_btn') ?>
                </button>
            </div>

            <div class="token-manager">
                <div class="token-manager-header">
                    <h4><?= t('docs_my_tokens_list') ?></h4>
                    <span style="font-size:.7rem;color:var(--text-3);"><?= count($myTokens) ?> <?= t('docs_tokens_count') ?></span>
                </div>
                <div id="tokens-list">
                <?php if (empty($myTokens)): ?>
                    <div class="token-empty"><?= t('docs_no_tokens') ?></div>
                <?php else: ?>
                    <?php foreach ($myTokens as $tok): ?>
                    <div class="token-item" id="tok-<?= (int)$tok['id'] ?>">
                        <div>
                            <div class="token-name"><?= htmlspecialchars($tok['name']) ?></div>
                            <div class="token-date"><?= t('docs_token_created') ?>: <?= htmlspecialchars(substr($tok['created_at'], 0, 10)) ?><?php if ($tok['last_used']): ?> &nbsp;·&nbsp; <?= t('docs_token_last_used') ?>: <?= htmlspecialchars(substr($tok['last_used'], 0, 10)) ?><?php endif; ?></div>
                        </div>
                        <button class="btn btn-sm btn-outline btn-danger" onclick="revokeToken(<?= (int)$tok['id'] ?>, this)" style="margin-left:auto;font-size:.68rem;">
                            <?= t('docs_revoke_token') ?>
                        </button>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
                </div>
            </div>
        </section>
        <?php else: ?>
        <section class="docs-section" id="my-tokens">
            <h2><?= t('docs_my_tokens_title') ?></h2>
            <div class="docs-alert docs-alert-info">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="var(--accent)" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                <span><?= t('docs_tokens_login_required') ?> <a href="login.php"><?= t('sign_in') ?></a></span>
            </div>
        </section>
        <?php endif; ?>

    </main>
</div>

<script>
const sections = document.querySelectorAll('.docs-section');
const navLinks  = document.querySelectorAll('.docs-nav-link');
const observer  = new IntersectionObserver(entries => {
    entries.forEach(e => {
        if (e.isIntersecting) {
            navLinks.forEach(l => l.classList.remove('active'));
            const active = document.querySelector('.docs-nav-link[href="#' + e.target.id + '"]');
            if (active) active.classList.add('active');
        }
    });
}, { rootMargin: '-20% 0px -70% 0px' });
sections.forEach(s => observer.observe(s));

function copyCode(btn) {
    const pre  = btn.closest('.docs-pre');
    const code = pre.querySelector('code').innerText;
    navigator.clipboard.writeText(code).then(() => {
        const orig = btn.textContent;
        btn.textContent = '✓';
        setTimeout(() => btn.textContent = orig, 1400);
    });
}

// Crear token
function createToken() {
    const nameEl = document.getElementById('token-name-input');
    const name   = nameEl.value.trim() || '<?= addslashes(t("docs_token_default_name")) ?>';
    fetch('api/tokens.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'create', name })
    })
    .then(r => r.json())
    .then(d => {
        if (!d.ok) { showToast('error', d.error || '<?= addslashes(t("network_error")) ?>'); return; }
        const box = document.getElementById('new-token-result');
        box.innerHTML = `
            <div class="new-token-flash">
                <p><strong><?= addslashes(t("docs_token_created_msg")) ?></strong> <?= addslashes(t("docs_token_save_warn")) ?></p>
                <div class="token-val-box" style="margin:.35rem 0;">
                    <span class="token-val" style="font-size:.76rem;">${d.token}</span>
                    <button class="btn btn-sm btn-outline" style="font-size:.65rem;flex-shrink:0;"
                            onclick="navigator.clipboard.writeText('${d.token}');this.textContent='✓';">
                        <?= addslashes(t("docs_copy")) ?>
                    </button>
                </div>
            </div>`;
        nameEl.value = '';
        const list = document.getElementById('tokens-list');
        const empty = list.querySelector('.token-empty');
        if (empty) empty.remove();
        const now = new Date().toISOString().slice(0,10);
        const row = document.createElement('div');
        row.className = 'token-item';
        row.id = 'tok-' + d.id;
        row.innerHTML = `
            <div class="token-item-main">
                <div>
                    <div class="token-name">${d.name}</div>
                    <div class="token-date"><?= addslashes(t("docs_token_created")) ?>: ${now}</div>
                </div>
            </div>
            <div class="token-item-actions">
                <button class="btn btn-sm btn-outline" style="font-size:.68rem;"
                        onclick="toggleToken(${d.id}, this)" data-token="${d.token}">
                    <?= addslashes(t("docs_show_token")) ?>
                </button>
                <button class="btn btn-sm btn-outline" style="font-size:.68rem;"
                        onclick="copyTokenById(${d.id}, '${d.token}', this)">
                    <?= addslashes(t("docs_copy")) ?>
                </button>
                <button class="btn btn-sm btn-outline" style="color:var(--red);border-color:rgba(240,74,110,.25);font-size:.68rem;"
                        onclick="revokeToken(${d.id}, this)">
                    <?= addslashes(t("docs_revoke_token")) ?>
                </button>
            </div>
            <div class="token-reveal-wrap" id="reveal-${d.id}">
                <div class="token-val-box">
                    <span class="token-val">${d.token}</span>
                    <button class="btn btn-sm btn-outline" style="font-size:.65rem;flex-shrink:0;"
                            onclick="copyTokenById(${d.id}, '${d.token}', this)">
                        <?= addslashes(t("docs_copy")) ?>
                    </button>
                </div>
            </div>`;
        list.prepend(row);
    });
}

function toggleToken(id, btn) {
    const wrap = document.getElementById('reveal-' + id);
    if (!wrap) return;
    const isOpen = wrap.classList.contains('open');
    wrap.classList.toggle('open', !isOpen);
    btn.textContent = isOpen ? '<?= addslashes(t("docs_show_token")) ?>' : '<?= addslashes(t("docs_hide_token")) ?>';
}

function copyTokenById(id, token, btn) {
    navigator.clipboard.writeText(token).then(() => {
        const orig = btn.textContent;
        btn.textContent = '✓ <?= addslashes(t("docs_copied")) ?>';
        setTimeout(() => btn.textContent = orig, 1500);
    });
}

function revokeToken(id, btn) {
    if (!confirm('<?= addslashes(t('docs_revoke_confirm')) ?>')) return;
    btn.disabled = true;
    fetch('api/tokens.php', {
        method: 'DELETE',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id })
    })
    .then(r => r.json())
    .then(d => {
        if (d.ok) {
            const row = document.getElementById('tok-' + id);
            if (row) { row.style.opacity = '0'; row.style.transition = 'opacity .2s'; setTimeout(() => row.remove(), 220); }
            showToast('success', '<?= addslashes(t('docs_token_revoked')) ?>');
        }
    });
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
