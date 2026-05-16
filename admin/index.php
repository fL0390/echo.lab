<?php
require_once __DIR__ . '/../lang.php';

require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../includes/logger.php';
require_once __DIR__ . '/../includes/mailer.php';
requirePermission('admin_panel');

$me     = currentUser();
$myRole = (int)$me['role'];
define('ROOT_ADMIN_ID', 1);

$canViewAll = can('view_all_users');
$canImport  = can('import_users');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_user_manual' && $myRole >= ROLE_TEACHER) {
    $newUsername = trim($_POST['new_username'] ?? '');
    $newEmail    = trim($_POST['new_email']    ?? '');
    $newRole     = (int)($_POST['new_role'] ?? ROLE_STUDENT);
    if ($newRole >= $myRole && $myRole < ROLE_ADMIN) $newRole = $myRole - 1;
    if ($newRole < ROLE_USER) $newRole = ROLE_USER;
    $err = null;
    if (strlen($newUsername) < 3 || strlen($newUsername) > 32 || !preg_match('/^[a-zA-Z0-9_]+$/', $newUsername)) {
        $err = t('profile_invalid_username');
    } elseif (isOffensiveUsername($newUsername)) {
        $err = t('profile_username_blocked');
    } elseif (DB::usernameExists($newUsername)) {
        $err = t('profile_username_taken');
    } elseif ($newEmail !== '' && DB::emailExists($newEmail)) {
        $err = t('profile_email_taken');
    }
    if ($err) {
        setFlash('error', $err);
    } else {
        $tempPass = bin2hex(random_bytes(16));
        $cid      = ($myRole === ROLE_CENTER_ADMIN) ? $me['center_id'] : null;
        $newUser  = DB::createUserWithRole($newUsername, $newEmail, password_hash($tempPass, PASSWORD_DEFAULT), $newRole, (int)$me['id'], $cid);
        $sent = false;
        if ($newEmail !== '') {
            $token   = bin2hex(random_bytes(32));
            $expires = date('Y-m-d H:i:s', time() + 48 * 3600);
            DB::setResetToken((int)$newUser['id'], $token, $expires);
            $link = APP_URL . '/reset.php?token=' . $token;
            $sent = sendWelcomeEmail($newUser, $link, getCurrentLang());
        }
        $msg = $sent
            ? t('manual_user_created_email', ['user' => $newUsername, 'email' => $newEmail])
            : t('manual_user_created_nomail', ['user' => $newUsername]);
        setFlash('success', $msg);
    }
    header('Location: index.php'); exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && ($canViewAll || $canImport)) {
    $targetId = (int)($_POST['user_id'] ?? 0);
    $target   = DB::findUserById($targetId);

    if (!$target) {
        setFlash('error', t('user_not_found'));
    } elseif ($targetId === ROOT_ADMIN_ID && (int)$me['id'] !== ROOT_ADMIN_ID) {
        setFlash('error', t('admin_protected'));
    } elseif ($targetId === (int)$me['id']) {
        setFlash('error', t('admin_self_modify'));
    } elseif ((int)$target['role'] >= $myRole && $myRole < ROLE_ADMIN) {
        setFlash('error', t('admin_insufficient'));
    } else {
        $a = $_POST['action'];

        if ($a === 'change_role' && can('change_roles')) {
            $nr = (int)($_POST['new_role'] ?? ROLE_USER);
            if ($nr >= $myRole && $myRole < ROLE_ADMIN) {
                setFlash('error', t('admin_cannot_assign_role'));
            } elseif ($nr === ROLE_BANNED) {
                DB::banUser($targetId, '', (int)$me['id']);
                setFlash('success', t('banned_msg', ['name' => $target['username']]));
            } else {
                DB::updateUserRole($targetId, $nr);
                setFlash('success', t('role_updated'));
            }

        } elseif ($a === 'change_center' && can('change_roles')) {
            $nc = $_POST['new_center'] === '' ? null : (int)$_POST['new_center'];
            DB::updateUserCenter($targetId, $nc);
            setFlash('success', t('group_updated'));

        } elseif ($a === 'ban' && can('ban_users')) {
            DB::banUser($targetId, trim($_POST['ban_message'] ?? ''), (int)$me['id']);
            setFlash('success', t('banned_msg', ['name' => $target['username']]));

        } elseif ($a === 'unban' && can('ban_users')) {
            DB::updateUserRole($targetId, ROLE_USER);
            setFlash('success', t('unbanned_msg', ['name' => $target['username']]));

        } elseif ($a === 'reset_password' && $myRole >= ROLE_ADMIN) {
            $token = bin2hex(random_bytes(32));
            $expires = date('Y-m-d H:i:s', time() + 3600);
            DB::setResetToken($targetId, $token, $expires);
            
            $link = APP_URL . '/reset.php?token=' . $token;
            $lang = function_exists('getCurrentLang') ? getCurrentLang() : 'en';
            if (!empty($target['email']) && sendResetEmail($target, $link, $lang)) {
                setFlash('success', t('reset_email_sent', ['email' => $target['email']]));
            } else {
                setFlash('error', t('reset_email_failed'));
            }
        }
    }
    header('Location: index.php');
    exit;
}

$results  = [];
$imported = 0;
$skipped  = 0;
$showImport = false;

function parseRole(string $val): int {
    $val = strtolower(trim($val));
    if ($val === '') return ROLE_STUDENT;
    $map = [
        'banned' => ROLE_BANNED, '0' => ROLE_BANNED,
        'user'   => ROLE_USER,   '1' => ROLE_USER,
        'student'=> ROLE_STUDENT,'2' => ROLE_STUDENT,
        'teacher'=> ROLE_TEACHER,'3' => ROLE_TEACHER,
        'center_admin' => ROLE_CENTER_ADMIN, '4' => ROLE_CENTER_ADMIN,
        'center admin' => ROLE_CENTER_ADMIN,
        'admin'  => ROLE_ADMIN,  '5' => ROLE_ADMIN,
    ];
    return $map[$val] ?? ROLE_STUDENT;
}

function generatePassword(int $len = 10): string {
    $chars = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKMNPQRSTUVWXYZ23456789';
    $out = '';
    for ($i = 0; $i < $len; $i++) {
        $out .= $chars[random_int(0, strlen($chars) - 1)];
    }
    return $out;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv_file']) && can('import_users')) {
    $showImport = true;
    $file = $_FILES['csv_file'];

    if ($file['error'] !== UPLOAD_ERR_OK) {
        setFlash('error', t('csv_upload_failed'));
        header('Location: index.php');
        exit;
    }

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['csv', 'txt'])) {
        setFlash('error', t('csv_invalid_ext'));
        header('Location: index.php');
        exit;
    }

    $handle = fopen($file['tmp_name'], 'r');
    if (!$handle) {
        setFlash('error', t('csv_read_error'));
        header('Location: index.php');
        exit;
    }

    $header = fgetcsv($handle, 0, ',', '"', "");
    if (!$header) {
        setFlash('error', t('csv_empty'));
        fclose($handle);
        header('Location: index.php');
        exit;
    }

    $header = array_map(fn($h) => strtolower(trim($h)), $header);
    $colIdx = array_flip($header);

    if (!isset($colIdx['username'])) {
        setFlash('error', t('csv_no_username_col'));
        fclose($handle);
        header('Location: index.php');
        exit;
    }

    $row = 1;
    while (($data = fgetcsv($handle, 0, ',', '"', "")) !== false) {
        $row++;
        $username = trim($data[$colIdx['username']] ?? '');
        $email    = trim($data[$colIdx['email']    ?? -1] ?? '');
        $password = trim($data[$colIdx['password'] ?? -1] ?? '');
        $roleRaw  = trim($data[$colIdx['role']     ?? -1] ?? '');

        if ($username === '') {
            $results[] = ['row' => $row, 'status' => 'err', 'msg' => 'Row ' . $row . ': username is empty — skipped.'];
            $skipped++;
            continue;
        }
        if (strlen($username) < 3 || strlen($username) > 32) {
            $results[] = ['row' => $row, 'status' => 'err', 'msg' => "Row $row ($username): username must be 3–32 characters — skipped."];
            $skipped++;
            continue;
        }
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
            $results[] = ['row' => $row, 'status' => 'err', 'msg' => "Row $row ($username): invalid characters in username — skipped."];
            $skipped++;
            continue;
        }
        if (isOffensiveUsername($username)) {
            $results[] = ['row' => $row, 'status' => 'err', 'msg' => "Row $row ($username): username not allowed — skipped."];
            $skipped++;
            continue;
        }
        if (DB::usernameExists($username)) {
            $results[] = ['row' => $row, 'status' => 'warn', 'msg' => "Row $row ($username): username already exists — skipped."];
            $skipped++;
            continue;
        }
        if ($email !== '' && DB::emailExists($email)) {
            $results[] = ['row' => $row, 'status' => 'warn', 'msg' => "Row $row ($username): email already in use — skipped."];
            $skipped++;
            continue;
        }

        $generated = false;
        if ($password === '') {
            $generated = true;
            $password = bin2hex(random_bytes(16));
        }

        $role = parseRole($roleRaw);
        if ($role >= $myRole && $myRole < ROLE_ADMIN) {
            $role = $myRole - 1;
        }

        $applyCenter = ($myRole === ROLE_CENTER_ADMIN) ? $me['center_id'] : null;

        $newUser = DB::createUserWithRole($username, $email, password_hash($password, PASSWORD_DEFAULT), $role, (int)$me['id'], $applyCenter);

        $note = '';
        if ($generated && $email !== '') {
            $token   = bin2hex(random_bytes(32));
            $expires = date('Y-m-d H:i:s', time() + 48 * 3600);
            DB::setResetToken((int)$newUser['id'], $token, $expires);
            $link = APP_URL . '/reset.php?token=' . $token;
            $lang = function_exists('getCurrentLang') ? getCurrentLang() : 'en';
            if (sendWelcomeEmail($newUser, $link, $lang)) {
                $note = t('csv_email_sent');
            } else {
                $note = t('csv_email_failed');
            }
        } elseif ($generated) {
            $note = t('csv_gen_pass', ['pass' => $password]);
        }

        $results[] = ['row' => $row, 'status' => 'ok', 'msg' => t('csv_imported_row', ['row' => $row, 'name' => $username, 'role' => getRoleName($role), 'note' => $note])];
        $imported++;
    }
    fclose($handle);
}

if ($canViewAll) {
    $users = ($myRole === ROLE_CENTER_ADMIN) ? DB::getAllUsers(null, $me['center_id']) : DB::getAllUsers();
} elseif ($canImport) {
    $users = DB::getAllUsers((int)$me['id']);
} else {
    $users = [];
}
$centersMap = [];
if (can('change_roles') && $myRole === ROLE_ADMIN) {
    foreach (DB::getCenters() as $c) {
        $centersMap[$c['id']] = $c['name'];
    }
}
$totalUsers = count($users);
$pageTitle  = 'Admin — ' . APP_NAME;
$wideLayout = true;
require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <h1><?= t('admin_panel_title') ?></h1>
    <p><?= t('admin_panel_desc') ?></p>
</div>

<div class="admin-tabs">
    <a href="index.php" class="active"><?= t('admin_tab_users') ?></a>
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
    <?php if (can('manage_isos')): ?>
        <a href="isos.php"><?= t('admin_tab_isos') ?></a><?php endif; ?>
</div>

<?php if (!$canViewAll && !$canImport): ?>
<div class="card" style="text-align:center;padding:2.5rem;">
    <svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="color:var(--text-3);margin-bottom:.75rem;"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
    <h2 style="font-size:.95rem;margin-bottom:.35rem;">Access Denied</h2>
    <p style="color:var(--text-2);font-size:.8rem;">You do not have permission to view this page.</p>
</div>
<?php else: ?>

<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:.85rem;flex-wrap:wrap;gap:.5rem;">
    <div class="stat-chip">
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
        <?= $totalUsers ?> total users
    </div>
    <?php if (can('import_users')): ?>
    <div class="csv-btn-wrap">
        <button type="button" class="btn btn-sm btn-outline" id="csv-toggle-btn" onclick="toggleCsvPanel()">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
            <?= t('admin_import_btn') ?>
            <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" id="csv-chevron" style="transition:transform .2s var(--spring);"><polyline points="6 9 12 15 18 9"/></svg>
        </button>
        <div class="csv-panel" id="csv-panel" style="<?= $showImport ? '' : 'display:none;' ?>">
            <div class="csv-panel-arrow"></div>
            <div class="csv-panel-inner">
                <div class="csv-panel-grid">
                    <div>
                        <div style="font-size:.82rem;font-weight:600;margin-bottom:.45rem;"><?= t('csv_upload_title') ?></div>
                        <p style="color:var(--text-2);font-size:.72rem;margin-bottom:.6rem;line-height:1.5;">
                            <?= t('csv_upload_desc') ?>
                        </p>
                        <form method="post" enctype="multipart/form-data" id="upload-form">
                            <div class="import-drop csv-drop" id="drop-zone" onclick="document.getElementById('csv-file').click()">
                                <input type="file" id="csv-file" name="csv_file" accept=".csv,.txt" style="display:none;" onchange="handleFileSelect(this)">
                                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" style="display:block;margin:0 auto .4rem;"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                                <div class="drop-title" id="drop-title"><?= t('csv_drop_title') ?></div>
                                <div class="drop-sub" id="drop-sub"><?= t('csv_drop_sub') ?></div>
                            </div>
                            <button type="submit" class="btn btn-sm" id="import-btn" style="width:100%;margin-top:.5rem;" disabled><?= t('admin_process_import') ?></button>
                        </form>
                    </div>
                    <div>
                        <div style="font-size:.82rem;font-weight:600;margin-bottom:.45rem;"><?= t('csv_format_title') ?></div>
                        <div style="font-size:.68rem;color:var(--text-2);line-height:1.7;margin-bottom:.5rem;">
                            <?= t('csv_format_desc') ?>
                        </div>
                        <pre style="background:var(--bg);border:1px solid var(--sep);border-radius:var(--r-sm);padding:.4rem .55rem;font-size:.62rem;color:var(--text-2);overflow-x:auto;font-family:var(--mono);line-height:1.6;">username,email,password,role
jdoe,john@school.edu,secret123,student
asmith,alice@school.edu,,teacher</pre>
                    </div>
                </div>

                <?php if (!empty($results)): ?>
                <div style="border-top:1px solid var(--sep);margin-top:.75rem;padding-top:.65rem;">
                    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:.45rem;">
                        <span style="font-size:.8rem;font-weight:600;"><?= t('csv_results_title') ?></span>
                        <div style="display:flex;gap:.35rem;">
                            <?php if ($imported > 0): ?>
                            <span class="stat-chip" style="color:var(--green);border-color:rgba(16,185,129,.2);font-size:.62rem;"><?= $imported ?> imported</span>
                            <?php endif; ?>
                            <?php if ($skipped > 0): ?>
                            <span class="stat-chip" style="color:var(--orange);border-color:rgba(245,158,11,.2);font-size:.62rem;"><?= $skipped ?> skipped</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="import-results" style="max-height:200px;overflow-y:auto;">
                        <?php foreach ($results as $r): ?>
                        <div class="import-row <?= htmlspecialchars($r['status']) ?>">
                            <span class="row-num">#<?= $r['row'] ?></span>
                            <?php if ($r['status'] === 'ok'): ?>
                                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><polyline points="20 6 9 17 4 12"/></svg>
                            <?php elseif ($r['status'] === 'warn'): ?>
                                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/></svg>
                            <?php else: ?>
                                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
                            <?php endif; ?>
                            <span class="row-msg"><?= $r['msg'] ?></span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<div style="display:flex;align-items:center;gap:.5rem;margin-bottom:.85rem;flex-wrap:wrap;">
    <div class="form-group" style="margin-bottom:0;flex:1;min-width:180px;">
        <input type="search" id="live-search" placeholder="<?= htmlspecialchars(t('admin_search_placeholder')) ?>" autofocus>
    </div>
    <?php if ($myRole >= ROLE_TEACHER): ?>
    <button type="button" class="btn btn-sm" onclick="document.getElementById('create-user-modal').style.display='flex';">
        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        <?= t('admin_create_user_btn') ?>
    </button>
    <?php endif; ?>
</div>

<div class="table-wrap">
    <table>
        <thead>
            <tr>
                <th style="width:48px;">ID</th>
                <th><?= t('admin_col_user') ?></th>
                <th class="hide-mobile"><?= t('admin_col_email') ?></th>
                <th><?= t('admin_col_role') ?></th>
                <?php if ($myRole === ROLE_ADMIN): ?><th class="hide-mobile"><?= t('admin_col_group') ?></th><?php endif; ?>
                <?php if (can('change_roles')): ?><th class="hide-mobile"><?= t('admin_col_change_role') ?></th><?php endif; ?>
                <th><?= t('admin_col_actions') ?></th>
            </tr>
        </thead>
        <tbody id="user-tbody">
        <?php foreach ($users as $u):
            $uid      = (int)$u['id'];
            $urole    = (int)$u['role'];
            $ucenter  = $u['center_id'];
            $isSelf   = ($uid === (int)$me['id']);
            $isRoot   = ($uid === ROOT_ADMIN_ID);
            $canMod   = !$isSelf && !$isRoot && ($urole < $myRole || $myRole === ROLE_ADMIN);
            $initial  = strtoupper(substr($u['username'], 0, 1));
            $avPath   = !empty($u['avatar']) ? '../data/avatars/' . $u['avatar'] : '';
            $searchable = strtolower($u['username'] . ' ' . $u['email'] . ' #' . $uid);
        ?>
            <tr data-search="<?= htmlspecialchars($searchable) ?>">
                <td class="id-col">#<?= $uid ?></td>
                <td style="white-space:nowrap;">
                    <?php if ($avPath): ?>
                        <img src="<?= htmlspecialchars($avPath) ?>" class="tbl-av" alt="">
                    <?php else: ?>
                        <span class="tbl-av-init"><?= $initial ?></span>
                    <?php endif; ?>
                    <a href="../profile.php?id=<?= $uid ?>" style="color:var(--text);font-weight:500;">
                        <?= htmlspecialchars($u['username']) ?>
                    </a>
                    <?php if ($isRoot): ?><span style="color:var(--text-3);font-size:.6rem;margin-left:.2rem;">ROOT</span><?php endif; ?>
                    <?php if ($isSelf):  ?><span style="color:var(--text-3);font-size:.6rem;margin-left:.2rem;">YOU</span><?php endif; ?>
                </td>
                <td class="hide-mobile" style="color:var(--text-2);">
                    <?= $u['email'] ? htmlspecialchars($u['email']) : '<span style="color:var(--text-3)">—</span>' ?>
                </td>
                <td><span class="badge <?= roleBadgeClass($urole) ?>"><?= getRoleName($urole) ?></span></td>

                <?php if ($myRole === ROLE_ADMIN): ?>
                <td class="hide-mobile">
                    <?php if ($canMod): ?>
                    <form method="post" style="display:flex;gap:.25rem;align-items:center;">
                        <input type="hidden" name="action" value="change_center">
                        <input type="hidden" name="user_id" value="<?= $uid ?>">
                        <select name="new_center" style="padding:.2rem .4rem;font-size:.72rem;background:var(--surface2);color:var(--text);border:1px solid var(--sep-bold);border-radius:var(--r-sm);font-family:var(--font);width:105px;">
                            <?php // None label ?>
                            <option value=""><?= t('admin_none') ?></option>
                            <?php foreach ($centersMap as $cid => $cname): ?>
                                <option value="<?= $cid ?>" <?= $cid === $ucenter ? 'selected' : '' ?>><?= htmlspecialchars($cname) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button type="submit" class="btn btn-sm" style="padding:.2rem .55rem;font-size:.68rem;"><?= t('admin_set_btn') ?></button>
                    </form>
                    <?php else: ?>
                        <span style="color:var(--text-3);font-size:.72rem;"><?= $ucenter ? htmlspecialchars($centersMap[$ucenter] ?? '—') : '—' ?></span>
                    <?php endif; ?>
                </td>
                <?php endif; ?>

                <?php if (can('change_roles')): ?>
                <td class="hide-mobile">
                    <?php if ($canMod): ?>
                    <form method="post" style="display:flex;gap:.25rem;align-items:center;">
                        <input type="hidden" name="action" value="change_role">
                        <input type="hidden" name="user_id" value="<?= $uid ?>">
                        <select name="new_role" style="padding:.2rem .4rem;font-size:.72rem;background:var(--surface2);color:var(--text);border:1px solid var(--sep-bold);border-radius:var(--r-sm);font-family:var(--font);">
                            <?php for ($r = 1; $r <= ($myRole === ROLE_ADMIN ? ROLE_ADMIN : $myRole - 1); $r++): ?>
                                <option value="<?= $r ?>" <?= $r === $urole ? 'selected' : '' ?>><?= getRoleName($r) ?></option>
                            <?php endfor; ?>
                        </select>
                        <button type="submit" class="btn btn-sm" style="padding:.2rem .55rem;font-size:.68rem;"><?= t('admin_set_btn') ?></button>
                    </form>
                    <?php else: ?>
                        <span style="color:var(--text-3);font-size:.72rem;">—</span>
                    <?php endif; ?>
                </td>
                <?php endif; ?>

                <td style="white-space:nowrap;">
                    <?php if ($canMod && can('ban_users')): ?>
                        <?php if ($urole === ROLE_BANNED): ?>
                            <form method="post" style="display:inline;">
                                <input type="hidden" name="action" value="unban">
                                <input type="hidden" name="user_id" value="<?= $uid ?>">
                                <button type="submit" class="icon-btn success" title="Unban user">
                                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><polyline points="20 6 9 17 4 12"/></svg>
                                </button>
                            </form>
                        <?php else: ?>
                            <button type="button" class="icon-btn danger" title="Ban user"
                                onclick="openBan(<?= $uid ?>,'<?= htmlspecialchars($u['username'], ENT_QUOTES) ?>')">
                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><circle cx="12" cy="12" r="10"/><line x1="4.93" y1="4.93" x2="19.07" y2="19.07"/></svg>
                            </button>
                        <?php endif; ?>
                        <?php if ($myRole >= ROLE_ADMIN): ?>
                        <form method="post" style="display:inline;">
                            <input type="hidden" name="action" value="reset_password">
                            <input type="hidden" name="user_id" value="<?= $uid ?>">
                            <button type="submit" class="icon-btn" title="<?= t('admin_send_reset') ?>" style="color:var(--accent);border-color:var(--accent-border);">
                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                            </button>
                        </form>
                        <?php endif; ?>
                    <?php elseif ($isSelf): ?>
                        <span style="color:var(--text-3);font-size:.7rem;"><?= t('admin_you_label') ?></span>
                    <?php elseif ($isRoot): ?>
                        <span style="color:var(--text-3);font-size:.7rem;"><?= t('admin_protected_label') ?></span>
                    <?php else: ?>
                        <span style="color:var(--text-3);">—</span>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<div id="create-user-modal" class="modal-overlay" style="display:none;">
    <div class="card modal-card" style="padding:1.3rem;max-width:420px;width:100%;">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1rem;">
            <h2 style="font-size:.95rem;display:flex;align-items:center;gap:.4rem;margin:0;">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="var(--accent)" stroke-width="2" stroke-linecap="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/><line x1="19" y1="8" x2="19" y2="14"/><line x1="22" y1="11" x2="16" y2="11"/></svg>
                <?= t('admin_create_user_title') ?>
            </h2>
            <button class="icon-btn" onclick="document.getElementById('create-user-modal').style.display='none';" title="<?= t('cancel') ?>">
                <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>
        <p style="font-size:.78rem;color:var(--text-3);margin-bottom:1rem;"><?= t('admin_create_user_desc') ?></p>
        <form method="post">
            <input type="hidden" name="action" value="create_user_manual">
            <div class="form-group">
                <label><?= t('login_username_label') ?></label>
                <input type="text" name="new_username" required minlength="3" maxlength="32" pattern="[a-zA-Z0-9_]+" placeholder="alumno1">
            </div>
            <div class="form-group">
                <label>
                    <?= t('profile_email_label') ?>
                    <span style="color:var(--text-3);font-size:.65rem;font-weight:400;margin-left:.25rem;"><?= t('admin_create_user_email_hint') ?></span>
                </label>
                <input type="email" name="new_email" placeholder="user@echo.dev">
            </div>
            <div class="form-group">
                <label><?= t('admin_col_role') ?></label>
                <select name="new_role">
                    <?php for ($r = ROLE_USER; $r <= ($myRole === ROLE_ADMIN ? ROLE_ADMIN : $myRole - 1); $r++): ?>
                    <option value="<?= $r ?>" <?= $r === ROLE_STUDENT ? 'selected' : '' ?>><?= getRoleName($r) ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <div style="display:flex;gap:.4rem;justify-content:flex-end;margin-top:.85rem;">
                <button type="button" class="btn btn-sm btn-outline" onclick="document.getElementById('create-user-modal').style.display='none';"><?= t('cancel') ?></button>
                <button type="submit" class="btn btn-sm">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                    <?= t('admin_create_user_btn') ?>
                </button>
            </div>
        </form>
    </div>
</div>


<div id="ban-modal" class="modal-overlay" style="display:none;">
    <div class="card modal-card" style="padding:1.25rem;">
        <h2 style="color:var(--red);font-size:.95rem;margin-bottom:.25rem;">Ban User</h2>
        <p style="color:var(--text-2);font-size:.8rem;margin-bottom:.85rem;">
            You are about to ban <strong id="ban-name"></strong>.
        </p>
        <form method="post">
            <input type="hidden" name="action" value="ban">
            <input type="hidden" name="user_id" id="ban-uid" value="">
            <div class="form-group">
                <label>Reason (optional)</label>
                <textarea name="ban_message" placeholder="Explain why this user is being banned…"></textarea>
            </div>
            <div style="display:flex;gap:.4rem;justify-content:flex-end;">
                <button type="button" class="btn btn-sm btn-outline" onclick="closeBan()"><?= t('cancel') ?></button>
                <button type="submit" class="btn btn-sm btn-danger">Ban User</button>
            </div>
        </form>
    </div>
</div>

<style>
.csv-btn-wrap { position:relative; }
.csv-panel {
    position:absolute;
    top:calc(100% + 8px);
    right:0;
    width:540px;
    max-width:90vw;
    background:var(--surface);
    border:1px solid var(--sep-bold);
    border-radius:var(--r);
    box-shadow:var(--shadow-lg);
    z-index:50;
    animation:csvIn .2s var(--spring) both;
}
@keyframes csvIn { from { opacity:0; transform:translateY(-6px); } }
.csv-panel-arrow {
    position:absolute;
    top:-6px;
    right:20px;
    width:12px;
    height:12px;
    background:var(--surface);
    border:1px solid var(--sep-bold);
    border-bottom:none;
    border-right:none;
    transform:rotate(45deg);
    z-index:1;
}
.csv-panel-inner { padding:1rem; position:relative; z-index:2; background:var(--surface); border-radius:var(--r); }
.csv-panel-grid { display:grid; grid-template-columns:1fr 1fr; gap:.85rem; }
.csv-drop { padding:1.25rem .8rem; }
.csv-drop .drop-title { font-size:.78rem; }
.csv-drop .drop-sub { font-size:.65rem; }

@media (max-width:768px) {
    .csv-panel { position:fixed; top:auto; bottom:0; left:0; right:0; width:100%; max-width:100%; border-radius:var(--r) var(--r) 0 0; max-height:80vh; overflow-y:auto; }
    .csv-panel-arrow { display:none; }
    .csv-panel-grid { grid-template-columns:1fr; }
    .hide-mobile { display:none!important; }
}
</style>

<script>
document.getElementById('live-search').addEventListener('input', function() {
    const q = this.value.toLowerCase().trim();
    document.querySelectorAll('#user-tbody tr').forEach(tr => {
        tr.classList.toggle('hidden-row', q !== '' && !(tr.dataset.search || '').includes(q));
    });
});

let csvOpen = <?= $showImport ? 'true' : 'false' ?>;
function toggleCsvPanel() {
    csvOpen = !csvOpen;
    const panel = document.getElementById('csv-panel');
    const chev = document.getElementById('csv-chevron');
    const btn = document.getElementById('csv-toggle-btn');
    if (csvOpen) {
        panel.style.display = '';
        chev.style.transform = 'rotate(180deg)';
        btn.classList.add('active-outline');
    } else {
        panel.style.display = 'none';
        chev.style.transform = '';
        btn.classList.remove('active-outline');
    }
}
<?php if ($showImport): ?>
document.getElementById('csv-chevron').style.transform = 'rotate(180deg)';
document.getElementById('csv-toggle-btn').classList.add('active-outline');
<?php endif; ?>

document.addEventListener('click', function(e) {
    if (!csvOpen) return;
    const wrap = document.querySelector('.csv-btn-wrap');
    if (wrap && !wrap.contains(e.target)) {
        toggleCsvPanel();
    }
});

function openBan(uid, name) {
    document.getElementById('ban-uid').value = uid;
    document.getElementById('ban-name').textContent = name;
    document.getElementById('ban-modal').style.display = 'flex';
}
function closeBan() { document.getElementById('ban-modal').style.display = 'none'; }
document.getElementById('ban-modal').addEventListener('click', e => { if (e.target === e.currentTarget) closeBan(); });

const dropZone   = document.getElementById('drop-zone');
const fileInput  = document.getElementById('csv-file');
const importBtn  = document.getElementById('import-btn');
const dropTitle  = document.getElementById('drop-title');
const dropSub    = document.getElementById('drop-sub');

if (dropZone) {
    window.handleFileSelect = function(input) {
        if (input.files.length > 0) {
            const f = input.files[0];
            dropTitle.textContent = f.name;
            dropSub.textContent   = (f.size / 1024).toFixed(1) + ' KB';
            dropZone.style.borderColor = 'var(--accent)';
            dropZone.style.background  = 'var(--accent-bg)';
            importBtn.disabled = false;
        }
    };

    dropZone.addEventListener('dragover',  e => { e.preventDefault(); dropZone.classList.add('drag-over'); });
    dropZone.addEventListener('dragleave', () => dropZone.classList.remove('drag-over'));
    dropZone.addEventListener('drop', e => {
        e.preventDefault();
        dropZone.classList.remove('drag-over');
        const files = e.dataTransfer.files;
        if (files.length > 0) {
            const dt = new DataTransfer();
            dt.items.add(files[0]);
            fileInput.files = dt.files;
            window.handleFileSelect(fileInput);
        }
    });
}
</script>

<?php endif; ?>


<style>
.vm-win{position:fixed;background:#141418;border:1px solid rgba(255,255,255,.12);border-radius:10px;overflow:hidden;box-shadow:0 24px 60px rgba(0,0,0,.7);display:flex;flex-direction:column;pointer-events:all;}
.vm-win-tb{display:flex;align-items:center;justify-content:space-between;padding:0 .7rem;height:40px;background:var(--surface,#1a1a24);border-bottom:1px solid rgba(255,255,255,.07);cursor:move;user-select:none;flex-shrink:0;gap:.5rem;}
.vm-win-tb-left{display:flex;align-items:center;gap:.5rem;min-width:0;}
.vm-win-tb-title{font-size:.72rem;font-weight:600;color:#e0e0e0;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
.vm-win-tb-right{display:flex;align-items:center;gap:3px;flex-shrink:0;}
.vm-win-tb-btn{height:26px;border-radius:4px;border:1px solid rgba(255,255,255,.1);background:rgba(255,255,255,.04);color:rgba(255,255,255,.5);cursor:pointer;display:inline-flex;align-items:center;gap:.28rem;padding:0 .5rem;font-size:.6rem;font-weight:600;font-family:inherit;transition:all .12s;white-space:nowrap;}
.vm-win-tb-btn:hover{background:rgba(255,255,255,.09);color:#e0e0e0;}
.vm-win-tb-sep{width:1px;height:16px;background:rgba(255,255,255,.08);margin:0 1px;}
.vm-win-screen{flex:1;background:#000;display:flex;align-items:center;justify-content:center;}
.vm-resize{position:absolute;z-index:10;}
.vm-resize-e{top:8px;right:0;width:5px;bottom:8px;cursor:e-resize;}
.vm-resize-s{bottom:0;left:8px;right:8px;height:5px;cursor:s-resize;}
.vm-resize-se{bottom:0;right:0;width:12px;height:12px;cursor:se-resize;}
.vm-resize-w{top:8px;left:0;width:5px;bottom:8px;cursor:w-resize;}
.vm-resize-n{top:0;left:8px;right:8px;height:5px;cursor:n-resize;}

#doom-peek-wrap {
    position: fixed;
    bottom: 0;
    right: 2.5rem;
    z-index: 9999;
    width: 80px;
    transform: translateY(55px);
    transition: transform .35s cubic-bezier(.34,1.56,.64,1);
    cursor: pointer;
    user-select: none;
    animation: doomPeekIdle 7s ease-in-out infinite;
}
#doom-peek-wrap:hover {
    transform: translateY(4px) !important;
    animation: none;
}
#doom-slayer-img {
    width: 80px;
    display: block;
    image-rendering: pixelated;
    filter: drop-shadow(0 0 10px rgba(200,0,0,.6));
    transition: filter .2s;
}
#doom-peek-wrap:hover #doom-slayer-img {
    filter: drop-shadow(0 0 18px rgba(220,0,0,.9)) drop-shadow(0 0 6px rgba(200,0,0,.5));
}
@keyframes doomPeekIdle {
    0%,100% { transform: translateY(55px); }
    12%     { transform: translateY(28px); }
    28%     { transform: translateY(28px); }
    40%     { transform: translateY(55px); }
    65%     { transform: translateY(55px); }
    75%     { transform: translateY(36px); }
    88%     { transform: translateY(36px); }
    96%     { transform: translateY(55px); }
}

#doom-window {
    width: min(900px, 92vw);
    height: min(560px, 82vh);
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    z-index: 9100;
    border-color: rgba(180,0,0,.45) !important;
}
#doom-window.doom-maximized {
    top: 0 !important; left: 0 !important;
    width: 100vw !important; height: 100vh !important;
    transform: none !important; border-radius: 0 !important;
}
#doom-canvas {
    display: block;
    max-width: 100%; max-height: 100%;
    image-rendering: pixelated;
}
</style>

<div id="doom-peek-wrap" onclick="openDoomWindow()" title="...">
    <img id="doom-slayer-img" src="../doom/doom_slayer.png" alt="Doom Slayer">
</div>

<div id="doom-window" class="vm-win" style="display:none;">
    <div class="vm-win-tb" id="doom-win-tb">
        <div class="vm-win-tb-left">
            <span class="vm-win-tb-title" style="color:#ff4400;">&#9760; DOOM (1993) — UAC Mars Base</span>
        </div>
        <div class="vm-win-tb-right">
            <button class="vm-win-tb-btn" onclick="minimizeDoomWindow()">
                <svg width="9" height="9" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><line x1="5" y1="12" x2="19" y2="12"/></svg>Min
            </button>
            <span class="vm-win-tb-sep"></span>
            <button class="vm-win-tb-btn" id="doom-max-btn" onclick="toggleDoomMaximize()">
                <svg width="9" height="9" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><rect x="3" y="3" width="18" height="18" rx="2"/></svg>Max
            </button>
            <span class="vm-win-tb-sep"></span>
            <button class="vm-win-tb-btn" onclick="closeDoomWindow()" style="color:#ff6666;">
                <svg width="9" height="9" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>Close
            </button>
        </div>
    </div>
    <div class="vm-win-screen">
        <canvas id="doom-canvas" title="Click to focus · Arrow keys + Ctrl/Space/Enter"></canvas>
    </div>
    <div class="vm-resize vm-resize-e" id="dr-e"></div>
    <div class="vm-resize vm-resize-s" id="dr-s"></div>
    <div class="vm-resize vm-resize-se" id="dr-se" style="cursor:se-resize;width:12px;height:12px;"></div>
    <div class="vm-resize vm-resize-w" id="dr-w"></div>
    <div class="vm-resize vm-resize-n" id="dr-n"></div>
</div>

<script src="../doom/doom.js"></script>
<script>
(function(){
    var doomLoaded=false, doomMaximized=false;

    window.openDoomWindow = function() {
        var win = document.getElementById('doom-window');
        win.style.display = 'flex';
        if (!doomLoaded) {
            doomLoaded = true;
            var canvas = document.getElementById('doom-canvas');
            if (window.DoomGame) window.DoomGame.start(canvas, '../doom/doom.wasm');
        }
        makeDraggable(); makeResizable();
        if (typeof showToast === 'function') showToast('success', '💀 RIP AND TEAR!');
    };
    window.closeDoomWindow = function() {
        document.getElementById('doom-window').style.display = 'none';
    };
    window.minimizeDoomWindow = function() {
        document.getElementById('doom-window').style.display = 'none';
    };
    window.toggleDoomMaximize = function() {
        var win = document.getElementById('doom-window');
        doomMaximized = !doomMaximized;
        win.classList.toggle('doom-maximized', doomMaximized);
    };

    function makeDraggable() {
        var win = document.getElementById('doom-window');
        var tb  = document.getElementById('doom-win-tb');
        if (tb._doomDrag) return; tb._doomDrag = true;
        var sx,sy,sl,st;
        tb.addEventListener('mousedown', function(e) {
            if (e.target.closest('button') || doomMaximized) return;
            var r = win.getBoundingClientRect();
            sx=e.clientX; sy=e.clientY; sl=r.left; st=r.top;
            win.style.transform='none'; win.style.left=sl+'px'; win.style.top=st+'px';
            function mv(e){ win.style.left=Math.max(0,sl+e.clientX-sx)+'px'; win.style.top=Math.max(0,st+e.clientY-sy)+'px'; }
            function up(){ document.removeEventListener('mousemove',mv); document.removeEventListener('mouseup',up); }
            document.addEventListener('mousemove',mv); document.addEventListener('mouseup',up);
        });
    }
    function makeResizable() {
        var win = document.getElementById('doom-window');
        [['dr-e','e'],['dr-s','s'],['dr-se','se'],['dr-w','w'],['dr-n','n']].forEach(function(p){
            var el=document.getElementById(p[0]); var dir=p[1];
            if(!el||el._res) return; el._res=true;
            el.addEventListener('mousedown',function(e){
                if(doomMaximized) return; e.preventDefault();
                var r=win.getBoundingClientRect(), sx=e.clientX,sy=e.clientY,sw=r.width,sh=r.height,sl=r.left,st=r.top;
                win.style.transform='none'; win.style.left=sl+'px'; win.style.top=st+'px';
                function mv(e){
                    var dx=e.clientX-sx, dy=e.clientY-sy;
                    if(dir.includes('e')) win.style.width=Math.max(500,sw+dx)+'px';
                    if(dir.includes('s')) win.style.height=Math.max(320,sh+dy)+'px';
                    if(dir.includes('w')){ win.style.width=Math.max(500,sw-dx)+'px'; win.style.left=Math.min(sl+sw-500,sl+dx)+'px'; }
                    if(dir.includes('n')){ win.style.height=Math.max(320,sh-dy)+'px'; win.style.top=Math.min(st+sh-320,st+dy)+'px'; }
                }
                function up(){ document.removeEventListener('mousemove',mv); document.removeEventListener('mouseup',up); }
                document.addEventListener('mousemove',mv); document.addEventListener('mouseup',up);
            });
        });
    }

    var seq=[], code=[38,38,40,40,37,39,37,39,66,65];
    document.addEventListener('keydown',function(e){
        seq.push(e.keyCode); seq=seq.slice(-10);
        if(seq.join(',')==code.join(',')) {
            if(typeof showToast==='function') showToast('success',' IDDQD — God Mode? Not in echo.');
            var p=document.getElementById('doom-peek-wrap');
            if(p){ p.style.transition='transform .4s'; p.style.transform='translateY(-30px) rotate(-15deg)';
                setTimeout(function(){ p.style.transform='translateY(55px)'; setTimeout(function(){ p.style.transition=''; p.style.animation='doomPeekIdle 7s ease-in-out infinite'; },400); },600); }
        }
    });
})();
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
