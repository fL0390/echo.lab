<?php
require_once __DIR__ . '/../lang.php';

require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../includes/logger.php';
requirePermission('admin_panel');

if (!can('change_roles')) {
    setFlash('error', t('admin_insufficient'));
    header('Location: index.php');
    exit;
}

$me     = currentUser();
$myRole = (int)$me['role'];
$myId   = (int)$me['id'];
$myCenterId = $me['center_id'] ?? null;

/* Acciones POST */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $a = $_POST['action'];

    if ($a === 'create_group') {
        $name = trim($_POST['name'] ?? '');
        $type = in_array($_POST['type'] ?? '', ['organization', 'class']) ? $_POST['type'] : 'organization';
        if ($name === '') {
            setFlash('error', t('group_empty'));
        } else {
            $g = DB::createCenter($name, $type);
            setFlash('success', t('group_created', ['name' => $name]));
            header('Location: groups.php?view=' . $g['id']);
            exit;
        }
        header('Location: groups.php');
        exit;
    }

    if ($a === 'edit_group') {
        $gid = (int)$_POST['group_id'];
        $name = trim($_POST['name'] ?? '');
        $type = in_array($_POST['type'] ?? '', ['organization', 'class']) ? $_POST['type'] : 'organization';
        if ($name !== '') {
            DB::updateCenter($gid, ['name' => $name, 'type' => $type]);
            setFlash('success', t('group_updated_msg'));
        }
        header('Location: groups.php?view=' . $gid);
        exit;
    }

    if ($a === 'delete_group' && $myRole >= ROLE_ADMIN) {
        $gid = (int)$_POST['group_id'];
        DB::deleteCenter($gid);
        setFlash('success', t('group_deleted'));
        header('Location: groups.php');
        exit;
    }

    if ($a === 'add_member') {
        $gid = (int)$_POST['group_id'];
        $uid = (int)$_POST['user_id'];
        DB::addGroupMember($gid, $uid);
        setFlash('success', t('member_added'));
        header('Location: groups.php?view=' . $gid);
        exit;
    }

    if ($a === 'remove_member') {
        $gid = (int)$_POST['group_id'];
        $uid = (int)$_POST['user_id'];
        DB::removeGroupMember($gid, $uid);
        setFlash('success', t('member_removed'));
        header('Location: groups.php?view=' . $gid);
        exit;
    }

    if ($a === 'add_members_bulk') {
        $gid = (int)$_POST['group_id'];
        $uids = $_POST['user_ids'] ?? [];
        $added = 0;
        foreach ($uids as $uid) {
            DB::addGroupMember($gid, (int)$uid);
            $added++;
        }
        setFlash('success', $added . ' ' . t('member_added'));
        header('Location: groups.php?view=' . $gid);
        exit;
    }
}

$groups = DB::getCenters();
$memberCounts = DB::getGroupMemberCounts();

$viewGroupId = isset($_GET['view']) ? (int)$_GET['view'] : null;
$viewGroup = $viewGroupId ? DB::getCenterById($viewGroupId) : null;
$viewMembers = $viewGroup ? DB::getGroupMembers($viewGroupId) : [];
$viewMemberIds = $viewGroup ? DB::getGroupMemberIds($viewGroupId) : [];

$eligibleUsers = [];
if ($viewGroup) {
    if ($myRole >= ROLE_ADMIN) {
        $eligibleUsers = DB::getAllUsers();
    } elseif ($myRole === ROLE_CENTER_ADMIN && $myCenterId) {
        $eligibleUsers = DB::getAllUsers(null, $myCenterId);
    } else {
        $eligibleUsers = DB::getAllUsers($myId);
    }
    // Filtra los usuarios ya en el grupo y a sí mismo
    $eligibleUsers = array_values(array_filter($eligibleUsers, fn($u) =>
        !in_array((int)$u['id'], $viewMemberIds) && (int)$u['role'] !== ROLE_BANNED
    ));
}

$pageTitle  = t('admin_tab_groups') . ' — Admin';
$wideLayout = true;
require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <h1><?= t('administration') ?></h1>
    <p><?= t('admin_groups_desc') ?></p>
</div>

<div class="admin-tabs">
    <a href="index.php"><?= t('admin_tab_users') ?></a>
    <a href="groups.php" class="active"><?= t('admin_tab_groups') ?></a>
    <?php if (can('manage_nodes')): ?>
        <a href="nodes.php"><?= t('admin_tab_nodes') ?></a><?php endif; ?>
            <?php if ($myRole >= ROLE_ADMIN): ?>
            <a href="db_admin.php" class="<?= basename($_SERVER['PHP_SELF'])==='db_admin.php'?'active':'' ?>">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><ellipse cx="12" cy="5" rx="9" ry="3"/><path d="M21 12c0 1.66-4 3-9 3s-9-1.34-9-3"/><path d="M3 5v14c0 1.66 4 3 9 3s9-1.34 9-3V5"/></svg>
                DB Admin
            </a>
            <?php endif; ?>
    <?php if (can('manage_isos')): ?>
        <a href="isos.php"><?= t('admin_tab_isos') ?></a>
    <?php endif; ?>
</div>

<div class="grp-layout">
    <!-- Izquierda: lista de grupos + crear -->
    <div class="grp-sidebar">
        <!-- Crear -->
        <div class="card grp-create-card">
            <div class="grp-create-head">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="var(--accent)" stroke-width="2" stroke-linecap="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                <span><?= t('new_group_btn') ?></span>
            </div>
            <form method="post">
                <input type="hidden" name="action" value="create_group">
                <div class="form-group">
                    <label><?= t('group_name_label') ?></label>
                    <input type="text" name="name" placeholder="e.g. School #1 or Class 2A" required>
                </div>
                <div class="form-group">
                    <label><?= t('group_type_label') ?></label>
                    <select name="type">
                        <option value="organization"><?= t('group_type_org') ?></option>
                        <option value="class"><?= t('group_type_class') ?></option>
                    </select>
                </div>
                <button type="submit" class="btn" style="width:100%;"><?= t('create_group_btn') ?></button>
            </form>
        </div>

        <!-- Lista de grupos -->
        <div class="card grp-list-card">
            <div class="grp-list-head">
                <span><?= t('all_groups') ?></span>
                <span class="grp-count-badge"><?= count($groups) ?></span>
            </div>
            <?php if (empty($groups)): ?>
                <div class="grp-empty"><?= t('no_groups') ?></div>
            <?php else: ?>
                <div class="grp-items">
                    <?php foreach ($groups as $g):
                        $gid = (int)$g['id'];
                        $isActive = ($viewGroupId === $gid);
                        $type = $g['type'] ?? 'organization';
                        $mCount = $memberCounts[$gid] ?? 0;
                    ?>
                    <a href="groups.php?view=<?= $gid ?>" class="grp-item<?= $isActive ? ' active' : '' ?>">
                        <div class="grp-item-icon">
                            <?php if ($type === 'class'): ?>
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/></svg>
                            <?php else: ?>
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
                            <?php endif; ?>
                        </div>
                        <div class="grp-item-body">
                            <div class="grp-item-name"><?= htmlspecialchars($g['name']) ?></div>
                            <div class="grp-item-meta"><?= ucfirst($type) ?> · <?= $mCount ?> <?= t('members_label') ?></div>
                        </div>
                        <svg class="grp-item-arrow" width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><polyline points="9 18 15 12 9 6"/></svg>
                    </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Derecha: detalle del grupo -->
    <div class="grp-detail">
        <?php if (!$viewGroup): ?>
        <div class="card" style="text-align:center;padding:2.5rem;">
            <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.2" style="color:var(--text-3);margin-bottom:.5rem;"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
            <p style="color:var(--text-3);font-size:.82rem;"><?= t('admin_groups_desc') ?></p>
        </div>
        <?php else:
            $gType = $viewGroup['type'] ?? 'organization';
        ?>
        <!-- Card de cabecera del grupo -->
        <div class="card grp-detail-header">
            <div class="grp-detail-top">
                <div>
                    <div style="display:flex;align-items:center;gap:.4rem;margin-bottom:.15rem;">
                        <h2 class="grp-detail-name"><?= htmlspecialchars($viewGroup['name']) ?></h2>
                        <span class="badge <?= $gType === 'class' ? 'badge-student' : 'badge-center' ?>"><?= ucfirst($gType) ?></span>
                    </div>
                    <div class="grp-detail-meta">
                        <?= htmlspecialchars($viewGroup['created_at'] ?? '—') ?> · <?= count($viewMembers) ?> <?= t('members_label') ?>
                    </div>
                </div>
                <div style="display:flex;gap:.25rem;">
                    <button type="button" class="btn btn-sm btn-outline" onclick="openEditGroup()">
                        <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                        Edit
                    <?php if ($myRole >= ROLE_ADMIN): ?>
                    <button type="button" class="btn btn-sm btn-danger" onclick="openDeleteGroup()">
                        <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                    </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Panel de añadir por ID (los profesores pueden añadir cualquier usuario por ID) -->
        <div class="card grp-add-card" id="add-by-id-card">
            <div class="grp-add-head" onclick="toggleAddByIdPanel()">
                <div style="display:flex;align-items:center;gap:.35rem;">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/><line x1="19" y1="8" x2="23" y2="8"/><line x1="21" y1="6" x2="21" y2="10"/></svg>
                    <span>Add user by ID</span>
                </div>
                <svg id="add-by-id-chevron" width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" style="transition:transform .2s var(--spring);"><polyline points="6 9 12 15 18 9"/></svg>
            </div>
            <div id="add-by-id-panel" style="display:none;border-top:1px solid var(--sep);padding:.65rem;">
                <div style="display:flex;gap:.4rem;align-items:flex-start;">
                    <div style="flex:1;">
                        <input type="number" id="add-by-id-input" min="1" placeholder="Enter user ID…"
                            style="width:100%;padding:.3rem .55rem;font-size:.8rem;border-radius:var(--r-sm);border:1px solid var(--sep-bold);background:var(--surface2);color:var(--text);font-family:var(--font);"
                            oninput="lookupUser(this.value)">
                    </div>
                    <form method="post" id="add-by-id-form" style="display:none;">
                        <input type="hidden" name="action" value="add_member">
                        <input type="hidden" name="group_id" value="<?= $viewGroupId ?>">
                        <input type="hidden" name="user_id" id="add-by-id-uid" value="">
                        <button type="submit" class="btn btn-sm" id="add-by-id-btn">
                            <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                            Add
                        </button>
                    </form>
                </div>
                <div id="user-preview" style="display:none;margin-top:.55rem;padding:.55rem .65rem;background:var(--surface2);border:1px solid var(--sep-bold);border-radius:var(--r-sm);display:none;align-items:center;gap:.55rem;">
                    <div id="preview-avatar" style="width:34px;height:34px;border-radius:50%;background:var(--accent-bg);border:1px solid var(--accent-border);display:flex;align-items:center;justify-content:center;font-size:.8rem;font-weight:700;color:var(--accent);flex-shrink:0;overflow:hidden;"></div>
                    <div style="flex:1;min-width:0;">
                        <div id="preview-name" style="font-size:.82rem;font-weight:600;"></div>
                        <div id="preview-meta" style="font-size:.68rem;color:var(--text-3);margin-top:.05rem;"></div>
                    </div>
                    <span id="preview-role-badge" class="badge" style="font-size:.58rem;"></span>
                </div>
                <div id="user-preview-error" style="display:none;margin-top:.45rem;font-size:.75rem;color:var(--red);padding:.3rem .5rem;background:rgba(244,63,94,.08);border-radius:var(--r-sm);border:1px solid rgba(244,63,94,.2);"></div>
            </div>
        </div>

        <?php if (!empty($eligibleUsers)): ?>
        <div class="card grp-add-card">
            <div class="grp-add-head" onclick="toggleAddPanel()">
                <div style="display:flex;align-items:center;gap:.35rem;">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="8.5" cy="7" r="4"/><line x1="20" y1="8" x2="20" y2="14"/><line x1="23" y1="11" x2="17" y2="11"/></svg>
                    <span><?= t('add_member_btn') ?></span>
                    <span class="grp-eligible-count"><?= count($eligibleUsers) ?> <?= t('members_label') ?></span>
                </div>
                <svg id="add-chevron" width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" style="transition:transform .2s var(--spring);"><polyline points="6 9 12 15 18 9"/></svg>
            </div>
            <div id="add-panel" class="grp-add-panel" style="display:none;">
                <input type="text" id="add-search" placeholder="<?= htmlspecialchars(t('search_users_ph')) ?>" class="grp-add-search">
                <form method="post" id="bulk-add-form">
                    <input type="hidden" name="action" value="add_members_bulk">
                    <input type="hidden" name="group_id" value="<?= $viewGroupId ?>">
                    <div class="grp-add-list" id="add-list">
                        <?php foreach ($eligibleUsers as $eu):
                            $euId = (int)$eu['id'];
                            $euInit = strtoupper(substr($eu['username'], 0, 1));
                        ?>
                        <label class="grp-add-user" data-search="<?= strtolower($eu['username'] . ' ' . ($eu['email'] ?? '')) ?>">
                            <input type="checkbox" name="user_ids[]" value="<?= $euId ?>">
                            <span class="tbl-av-init" style="width:24px;height:24px;font-size:.55rem;flex-shrink:0;"><?= $euInit ?></span>
                            <span class="grp-add-uname"><?= htmlspecialchars($eu['username']) ?></span>
                            <span class="grp-add-urole badge <?= roleBadgeClass((int)$eu['role']) ?>"><?= getRoleName((int)$eu['role']) ?></span>
                        </label>
                        <?php endforeach; ?>
                    </div>
                    <button type="submit" class="btn btn-sm grp-add-btn" id="bulk-add-btn" disabled>
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                        <?= t('add_member_btn') ?>
                    </button>
                </form>
            </div>
        </div>
        <?php endif; ?>

        <div class="card" style="padding:0;overflow:hidden;">
            <div style="padding:.55rem .85rem;border-bottom:1px solid var(--sep);display:flex;align-items:center;justify-content:space-between;">
                <h3 style="font-size:.82rem;margin:0;display:flex;align-items:center;gap:.3rem;">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="var(--text-3)" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
                    <?= t('group_members_title') ?>
                </h3>
                <span style="font-size:.68rem;color:var(--text-3);"><?= count($viewMembers) ?> <?= t('members_label') ?></span>
            </div>
            <?php if (empty($viewMembers)): ?>
                <div style="text-align:center;padding:2rem;color:var(--text-3);font-size:.78rem;">
                    <?= t('no_users_found') ?>
                </div>
            <?php else: ?>
            <div class="table-wrap" style="border:none;border-radius:0;">
                <table>
                    <thead>
                        <tr>
                            <th style="width:40px;"><?= t('col_id') ?></th>
                            <th><?= t('col_user') ?></th>
                            <th class="hide-mobile"><?= t('profile_email_label') ?></th>
                            <th><?= t('col_role') ?></th>
                            <th style="width:44px;"></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($viewMembers as $m):
                        $mId = (int)$m['id'];
                        $mInit = strtoupper(substr($m['username'], 0, 1));
                    ?>
                        <tr>
                            <td class="id-col">#<?= $mId ?></td>
                            <td style="white-space:nowrap;">
                                <span class="tbl-av-init"><?= $mInit ?></span>
                                <a href="../profile.php?id=<?= $mId ?>" style="color:var(--text);font-weight:500;"><?= htmlspecialchars($m['username']) ?></a>
                            </td>
                            <td class="hide-mobile" style="color:var(--text-2);font-size:.78rem;">
                                <?= $m['email'] ? htmlspecialchars($m['email']) : '<span style="color:var(--text-3)">—</span>' ?>
                            </td>
                            <td><span class="badge <?= roleBadgeClass((int)$m['role']) ?>"><?= getRoleName((int)$m['role']) ?></span></td>
                            <td>
                                <form method="post" style="display:inline;">
                                    <input type="hidden" name="action" value="remove_member">
                                    <input type="hidden" name="group_id" value="<?= $viewGroupId ?>">
                                    <input type="hidden" name="user_id" value="<?= $mId ?>">
                                    <button type="submit" class="icon-btn danger" title="Remove from group">
                                        <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>

        <div id="edit-group-modal" class="modal-overlay" style="display:none;">
            <div class="card modal-card" style="padding:1.25rem;">
                <h2 style="font-size:.92rem;margin-bottom:.75rem;display:flex;align-items:center;gap:.3rem;">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="var(--accent)" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                    Edit Group
                </h2>
                <form method="post">
                    <input type="hidden" name="action" value="edit_group">
                    <input type="hidden" name="group_id" value="<?= $viewGroupId ?>">
                    <div class="form-group">
                        <label><?= t('group_name_label') ?></label>
                        <input type="text" name="name" value="<?= htmlspecialchars($viewGroup['name']) ?>" required>
                    </div>
                    <div class="form-group">
                        <label><?= t('group_type_label') ?></label>
                        <select name="type">
                            <option value="organization" <?= $gType === 'organization' ? 'selected' : '' ?>><?= t('group_type_org') ?></option>
                            <option value="class" <?= $gType === 'class' ? 'selected' : '' ?>><?= t('group_type_class') ?></option>
                        </select>
                    </div>
                    <div style="display:flex;gap:.3rem;justify-content:flex-end;margin-top:.65rem;">
                        <button type="button" class="btn btn-sm btn-outline" onclick="closeEditGroup()"><?= t('cancel') ?></button>
                        <button type="submit" class="btn btn-sm"><?= t('save') ?></button>
                    </div>
                </form>
            </div>
        </div>

        <?php if ($myRole >= ROLE_ADMIN): ?>
        <div id="delete-group-modal" class="modal-overlay" style="display:none;">
            <div class="card modal-card" style="padding:1.25rem;">
                <h2 style="color:var(--red);font-size:.92rem;margin-bottom:.35rem;"><?= t('delete_group_btn') ?></h2>
                <p style="color:var(--text-2);font-size:.78rem;margin-bottom:.85rem;line-height:1.5;">
                    <?= t('delete_vm_confirm') ?> <strong style="color:var(--text);"><?= htmlspecialchars($viewGroup['name']) ?></strong>?
                </p>
                <form method="post">
                    <input type="hidden" name="action" value="delete_group">
                    <input type="hidden" name="group_id" value="<?= $viewGroupId ?>">
                    <div style="display:flex;gap:.3rem;justify-content:flex-end;">
                        <button type="button" class="btn btn-sm btn-outline" onclick="closeDeleteGroup()"><?= t('cancel') ?></button>
                        <button type="submit" class="btn btn-sm btn-danger"><?= t('delete') ?></button>
                    </div>
                </form>
            </div>
        </div>
        <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<style>
.grp-layout { display: grid; grid-template-columns: 300px 1fr; gap: 1rem; align-items: start; }

.grp-sidebar { display: flex; flex-direction: column; gap: .75rem; }
.grp-create-card { margin-bottom: 0; }
.grp-create-head { display: flex; align-items: center; gap: .35rem; font-size: .82rem; font-weight: 600; margin-bottom: .65rem; }
.grp-list-card { margin-bottom: 0; padding: 0; overflow: hidden; }
.grp-list-head { padding: .55rem .75rem; border-bottom: 1px solid var(--sep); font-size: .8rem; font-weight: 600; display: flex; align-items: center; justify-content: space-between; }
.grp-count-badge { background: var(--surface3); color: var(--text-2); font-size: .58rem; font-weight: 700; padding: .08rem .3rem; border-radius: var(--r-pill); min-width: 18px; text-align: center; }
.grp-empty { padding: 1.5rem .75rem; text-align: center; color: var(--text-3); font-size: .76rem; }
.grp-items { max-height: 400px; overflow-y: auto; }
.grp-item { display: flex; align-items: center; gap: .45rem; padding: .5rem .75rem; text-decoration: none; color: var(--text); transition: background .12s; cursor: pointer; border-bottom: 1px solid var(--sep); }
.grp-item:last-child { border-bottom: none; }
.grp-item:hover { background: rgba(255,255,255,.03); }
.grp-item.active { background: var(--accent-bg); border-left: 2px solid var(--accent); }
.grp-item-icon { flex-shrink: 0; color: var(--text-3); }
.grp-item.active .grp-item-icon { color: var(--accent); }
.grp-item-body { flex: 1; min-width: 0; }
.grp-item-name { font-size: .78rem; font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.grp-item-meta { font-size: .6rem; color: var(--text-3); margin-top: .02rem; }
.grp-item-arrow { color: var(--text-3); flex-shrink: 0; opacity: 0; transition: opacity .12s; }
.grp-item:hover .grp-item-arrow, .grp-item.active .grp-item-arrow { opacity: 1; }

.grp-detail { display: flex; flex-direction: column; gap: .75rem; }
.grp-detail-header { margin-bottom: 0; }
.grp-detail-top { display: flex; align-items: flex-start; justify-content: space-between; gap: .5rem; }
.grp-detail-name { font-size: 1rem; font-weight: 700; letter-spacing: -.02em; margin: 0; }
.grp-detail-meta { font-size: .68rem; color: var(--text-3); }

.grp-add-card { margin-bottom: 0; padding: 0; overflow: hidden; }
.grp-add-head { padding: .55rem .75rem; display: flex; align-items: center; justify-content: space-between; cursor: pointer; transition: background .12s; font-size: .8rem; font-weight: 600; }
.grp-add-head:hover { background: rgba(255,255,255,.02); }
.grp-eligible-count { font-size: .58rem; font-weight: 600; color: var(--text-3); background: var(--surface3); padding: .08rem .35rem; border-radius: var(--r-pill); }
.grp-add-panel { border-top: 1px solid var(--sep); padding: .65rem; }
.grp-add-search { width: 100%; padding: .3rem .55rem; font-size: .75rem; border-radius: var(--r-sm); border: 1px solid var(--sep); background: var(--surface2); color: var(--text); font-family: var(--font); margin-bottom: .45rem; }
.grp-add-search:focus { outline: none; border-color: var(--accent); box-shadow: 0 0 0 2px var(--accent-bg); }
.grp-add-list { max-height: 220px; overflow-y: auto; display: flex; flex-direction: column; gap: 1px; margin-bottom: .45rem; }
.grp-add-user { display: flex; align-items: center; gap: .4rem; padding: .3rem .35rem; border-radius: var(--r-sm); cursor: pointer; transition: background .1s; font-size: .78rem; }
.grp-add-user:hover { background: rgba(255,255,255,.04); }
.grp-add-user input[type="checkbox"] { width: 14px; height: 14px; accent-color: var(--accent); flex-shrink: 0; cursor: pointer; }
.grp-add-uname { font-weight: 500; flex: 1; }
.grp-add-urole { font-size: .55rem !important; }
.grp-add-btn { width: 100%; }

@media (max-width: 768px) {
    .grp-layout { grid-template-columns: 1fr; }
    .hide-mobile { display: none !important; }
}
</style>

<script>
let addByIdOpen = false;
let lookupTimer = null;
function toggleAddByIdPanel() {
    addByIdOpen = !addByIdOpen;
    const panel = document.getElementById('add-by-id-panel');
    const chev = document.getElementById('add-by-id-chevron');
    panel.style.display = addByIdOpen ? '' : 'none';
    chev.style.transform = addByIdOpen ? 'rotate(180deg)' : '';
}

function lookupUser(val) {
    clearTimeout(lookupTimer);
    const id = parseInt(val);
    const preview = document.getElementById('user-preview');
    const err = document.getElementById('user-preview-error');
    const form = document.getElementById('add-by-id-form');
    preview.style.display = 'none';
    err.style.display = 'none';
    form.style.display = 'none';
    if (!id || id <= 0) return;
    lookupTimer = setTimeout(async () => {
        try {
            const res = await fetch('../api/user_preview.php?id=' + id);
            const data = await res.json();
            if (data.error) {
                err.textContent = data.error === 'User not found' ? 'No user found with ID ' + id : data.error;
                err.style.display = 'block';
                return;
            }
            const avatarEl = document.getElementById('preview-avatar');
            if (data.avatar) {
                avatarEl.innerHTML = `<img src="${data.avatar}" style="width:100%;height:100%;object-fit:cover;" onerror="this.parentElement.textContent='${data.initial}'">`;
            } else {
                avatarEl.textContent = data.initial;
            }
            document.getElementById('preview-name').textContent = data.username;
            document.getElementById('preview-meta').textContent = (data.email || '') + (data.email ? ' · ID #' + data.id : 'ID #' + data.id);
            const badge = document.getElementById('preview-role-badge');
            const roleClasses = {1:'badge-user',2:'badge-student',3:'badge-teacher',4:'badge-center',5:'badge-admin',0:'badge-banned'};
            badge.className = 'badge ' + (roleClasses[data.role] || 'badge-user');
            badge.textContent = data.roleName;
            preview.style.display = 'flex';
            document.getElementById('add-by-id-uid').value = data.id;
            form.style.display = 'flex';
        } catch(e) {
            err.textContent = 'Error looking up user.';
            err.style.display = 'block';
        }
    }, 350);
}

let addOpen = false;
function toggleAddPanel() {
    addOpen = !addOpen;
    const panel = document.getElementById('add-panel');
    const chev = document.getElementById('add-chevron');
    panel.style.display = addOpen ? '' : 'none';
    chev.style.transform = addOpen ? 'rotate(180deg)' : '';
}

document.getElementById('add-search')?.addEventListener('input', function() {
    const q = this.value.toLowerCase().trim();
    document.querySelectorAll('.grp-add-user').forEach(el => {
        el.style.display = (el.dataset.search || '').includes(q) ? '' : 'none';
    });
});

document.querySelectorAll('.grp-add-user input[type="checkbox"]').forEach(cb => {
    cb.addEventListener('change', () => {
        const btn = document.getElementById('bulk-add-btn');
        const count = document.querySelectorAll('.grp-add-user input:checked').length;
        btn.disabled = (count === 0);
        btn.innerHTML = count > 0
            ? '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg> Add ' + count + ' Selected'
            : '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg> Add Selected';
    });
});

function openEditGroup() { document.getElementById('edit-group-modal').style.display = 'flex'; }
function closeEditGroup() { document.getElementById('edit-group-modal').style.display = 'none'; }
document.getElementById('edit-group-modal')?.addEventListener('click', e => { if (e.target === e.currentTarget) closeEditGroup(); });

function openDeleteGroup() { document.getElementById('delete-group-modal').style.display = 'flex'; }
function closeDeleteGroup() { document.getElementById('delete-group-modal').style.display = 'none'; }
document.getElementById('delete-group-modal')?.addEventListener('click', e => { if (e.target === e.currentTarget) closeDeleteGroup(); });
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
