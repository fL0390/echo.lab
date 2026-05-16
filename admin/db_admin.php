<?php

require_once __DIR__ . '/../lang.php';
require_once __DIR__ . '/../auth.php';

requireLogin();
$me     = currentUser();
$myRole = (int)($me['role'] ?? 0);

// Solo admins
if ($myRole < ROLE_ADMIN) {
    http_response_code(403);
    require_once __DIR__ . '/../includes/header.php';
    echo '<div class="main-content"><div class="flash flash-error">403 — Admins only.</div></div>';
    require_once __DIR__ . '/../includes/footer.php';
    exit;
}

if (TESTING_MODE) {
    $pageTitle  = 'DB Admin';
    $wideLayout = true;
    require_once __DIR__ . '/../includes/header.php';
    echo '<div class="main-content" style="padding:3rem 1.5rem;text-align:center;">';
    echo '<svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="var(--orange)" stroke-width="1.5" stroke-linecap="round" style="margin-bottom:1rem;"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>';
    echo '<h2 style="font-size:1.1rem;margin-bottom:.5rem;color:var(--text);">MySQL required</h2>';
    echo '<p style="color:var(--text-3);font-size:.85rem;margin-bottom:1.25rem;">DB Admin only works when TESTING_MODE = false in config.php.<br>Set it to false and point the DB credentials at your MySQL server.</p>';
    echo '<a href="../index.php" class="btn btn-sm btn-outline">Back to dashboard</a>';
    echo '</div>';
    require_once __DIR__ . '/../includes/footer.php';
    exit;
}

$pdo = DB::getConnection();

$ALLOWED_TABLES = [
    'users','centers','vms','groups','group_members',
    'nodes','isos','proxmox_isos','api_tokens',
    'email_confirmations','banned_ips','logs',
];

$action = $_REQUEST['action'] ?? 'browse';
$table  = $_REQUEST['table']  ?? 'users';
$rowId  = (int)($_REQUEST['id'] ?? 0);
$flash  = '';

if (!in_array($table, $ALLOWED_TABLES)) { $table = 'users'; }

// Obtenemos la primary key de la tabla
function getPk(PDO $pdo, string $table): string {
    $stmt = $pdo->query("SHOW KEYS FROM `$table` WHERE Key_name = 'PRIMARY'");
    $row  = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ? $row['Column_name'] : 'id';
}

//GET columns de la tabla
function getColumns(PDO $pdo, string $table): array {
    $stmt = $pdo->query("DESCRIBE `$table`");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

//POST delete row
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'delete' && $rowId) {
    $pk = getPk($pdo, $table);
    if ($table === 'users' && $rowId === 1) {
        $flash = 'error:Cannot delete the root admin account.';
    } else {
        $pdo->prepare("DELETE FROM `$table` WHERE `$pk` = ?")->execute([$rowId]);
        $flash = 'success:Row deleted.';
    }
    $action = 'browse';
}

//POST save edit
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'save' && $rowId) {
    $pk   = getPk($pdo, $table);
    $cols = getColumns($pdo, $table);
    $sets = [];
    $vals = [];
    foreach ($cols as $col) {
        $name = $col['Field'];
        if ($name === $pk) continue;
        if (!isset($_POST['col_' . $name])) continue;
        $val = $_POST['col_' . $name];
        if ($val === '' && stripos($col['Null'], 'yes') !== false) $val = null;
        $sets[] = "`$name` = ?";
        $vals[] = $val;
    }
    if ($sets) {
        $vals[] = $rowId;
        $pdo->prepare("UPDATE `$table` SET " . implode(', ', $sets) . " WHERE `$pk` = ?")->execute($vals);
        $flash = 'success:Row updated.';
    }
    $action = 'browse';
}

//Cargar datos
$search  = trim($_GET['q'] ?? '');
$page    = max(1, (int)($_GET['p'] ?? 1));
$perPage = 30;
$offset  = ($page - 1) * $perPage;

$editRow = null;
if ($action === 'edit' && $rowId) {
    $pk     = getPk($pdo, $table);
    $stmt   = $pdo->prepare("SELECT * FROM `$table` WHERE `$pk` = ? LIMIT 1");
    $stmt->execute([$rowId]);
    $editRow = $stmt->fetch(PDO::FETCH_ASSOC);
}

//Rows de la tabla
$rows       = [];
$totalRows  = 0;
$cols       = getColumns($pdo, $table);
$colNames   = array_column($cols, 'Field');

if ($action !== 'schema') {
    $countSql = "SELECT COUNT(*) FROM `$table`";
    $params   = [];
    if ($search && $colNames) {
        $likes = array_map(fn($c) => "`$c` LIKE ?", $colNames);
        $countSql .= ' WHERE ' . implode(' OR ', $likes);
        $params = array_fill(0, count($colNames), "%$search%");
    }
    $totalRows = (int)$pdo->prepare($countSql)->execute($params) ? $pdo->prepare($countSql)->execute($params) : 0;
    $s = $pdo->prepare($countSql);
    $s->execute($params);
    $totalRows = (int)$s->fetchColumn();

    $sql = "SELECT * FROM `$table`";
    if ($search && $colNames) {
        $likes = array_map(fn($c) => "`$c` LIKE ?", $colNames);
        $sql  .= ' WHERE ' . implode(' OR ', $likes);
    }
    $sql .= " LIMIT $perPage OFFSET $offset";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$totalPages = max(1, (int)ceil($totalRows / $perPage));
$pageTitle  = 'DB Admin';
$wideLayout = true;
require_once __DIR__ . '/../includes/header.php';

//Obtener relaciones para ver el schema
function getRelations(PDO $pdo, string $db): array {
    $stmt = $pdo->prepare("
        SELECT TABLE_NAME, COLUMN_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME
        FROM information_schema.KEY_COLUMN_USAGE
        WHERE TABLE_SCHEMA = ? AND REFERENCED_TABLE_NAME IS NOT NULL
        ORDER BY TABLE_NAME
    ");
    $stmt->execute([$db]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

//Tabla con el numero de rows
function getTableCounts(PDO $pdo, array $tables): array {
    $counts = [];
    foreach ($tables as $t) {
        $counts[$t] = (int)$pdo->query("SELECT COUNT(*) FROM `$t`")->fetchColumn();
    }
    return $counts;
}
?>

<style>
.dba-wrap { display:grid; grid-template-columns:200px 1fr; gap:0; min-height:calc(100vh - 100px); }
.dba-sidebar {
    background:var(--surface); border-right:1px solid var(--sep);
    padding:.75rem 0; position:sticky; top:54px; height:calc(100vh - 54px);
    overflow-y:auto;
}
.dba-sidebar-title { font-size:.58rem; font-weight:700; text-transform:uppercase; letter-spacing:.08em; color:var(--text-4); padding:.35rem .85rem .2rem; }
.dba-table-link {
    display:flex; align-items:center; justify-content:space-between;
    padding:.32rem .85rem; font-size:.78rem; color:var(--text-2);
    text-decoration:none; transition:all .12s; border-left:2px solid transparent;
}
.dba-table-link:hover { background:var(--surface2); color:var(--text); }
.dba-table-link.active { color:var(--accent); background:var(--accent-bg); border-left-color:var(--accent); font-weight:600; }
.dba-table-count { font-size:.6rem; color:var(--text-4); font-family:var(--mono); }
.dba-main { padding:1.5rem 2rem; min-width:0; }

.dba-topbar { display:flex; align-items:center; gap:.75rem; margin-bottom:1.2rem; flex-wrap:wrap; }
.dba-title { font-size:1.1rem; font-weight:700; letter-spacing:-.02em; color:var(--text); }
.dba-subtitle { font-size:.72rem; color:var(--text-3); margin-top:.08rem; font-family:var(--mono); }
.dba-search { flex:1; min-width:180px; max-width:280px; padding:.38rem .65rem; background:var(--bg); border:1px solid var(--sep-bold); border-radius:var(--r-sm); color:var(--text); font-size:.8rem; font-family:var(--font); outline:none; }
.dba-search:focus { border-color:var(--accent); }

.dba-table-wrap { overflow-x:auto; border:1px solid var(--sep); border-radius:var(--r); background:var(--surface); margin-bottom:1rem; }
.dba-table { width:100%; border-collapse:collapse; font-size:.77rem; }
.dba-table th { background:var(--surface2); color:var(--text-3); font-size:.62rem; font-weight:700; text-transform:uppercase; letter-spacing:.06em; padding:.45rem .65rem; text-align:left; border-bottom:1px solid var(--sep); white-space:nowrap; position:sticky; top:0; }
.dba-table td { padding:.4rem .65rem; border-bottom:1px solid var(--sep); color:var(--text-2); max-width:220px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; vertical-align:middle; }
.dba-table tr:last-child td { border-bottom:none; }
.dba-table tr:hover td { background:rgba(123,94,167,.04); }
.dba-table td.pk { font-family:var(--mono); font-size:.68rem; color:var(--text-4); }
.dba-table td.null { color:var(--text-4); font-style:italic; font-size:.72rem; }
.dba-table td.json-cell { color:var(--accent-2); font-family:var(--mono); font-size:.68rem; }

.dba-pager { display:flex; align-items:center; gap:.35rem; flex-wrap:wrap; }
.dba-page-btn { padding:.22rem .55rem; border-radius:var(--r-sm); font-size:.72rem; font-weight:500; border:1px solid var(--sep-bold); background:transparent; color:var(--text-2); cursor:pointer; text-decoration:none; transition:all .12s; }
.dba-page-btn:hover { background:var(--surface2); color:var(--text); }
.dba-page-btn.active { background:var(--accent); color:#fff; border-color:var(--accent); }
.dba-page-info { font-size:.72rem; color:var(--text-3); margin-left:.5rem; }

.dba-edit-card { background:var(--surface); border:1px solid var(--sep-bold); border-radius:var(--r); padding:1.4rem; max-width:700px; }
.dba-edit-card h2 { font-size:.95rem; margin-bottom:1rem; color:var(--text); }
.dba-field-grid { display:grid; grid-template-columns:1fr 1fr; gap:.65rem; }
.dba-field { display:flex; flex-direction:column; gap:.2rem; }
.dba-field label { font-size:.62rem; font-weight:700; text-transform:uppercase; letter-spacing:.05em; color:var(--text-3); }
.dba-field input, .dba-field textarea, .dba-field select {
    padding:.42rem .65rem; background:var(--bg); border:1px solid var(--sep-bold);
    border-radius:var(--r-sm); color:var(--text); font-family:var(--font); font-size:.8rem; outline:none;
}
.dba-field input:focus, .dba-field textarea:focus { border-color:var(--accent); box-shadow:0 0 0 2px var(--accent-bg); }
.dba-field .pk-field { color:var(--text-4); font-family:var(--mono); font-size:.78rem; padding:.42rem .65rem; background:var(--surface2); border:1px solid var(--sep); border-radius:var(--r-sm); }
.dba-field textarea { resize:vertical; min-height:60px; }

.schema-grid { display:grid; grid-template-columns:repeat(auto-fill, minmax(260px, 1fr)); gap:1rem; }
.schema-card { background:var(--surface); border:1px solid var(--sep); border-radius:var(--r); overflow:hidden; }
.schema-card-head { background:var(--surface2); border-bottom:1px solid var(--sep); padding:.6rem .9rem; display:flex; align-items:center; justify-content:space-between; }
.schema-card-name { font-size:.82rem; font-weight:700; color:var(--accent); font-family:var(--mono); }
.schema-card-count { font-size:.62rem; color:var(--text-3); }
.schema-col { display:flex; align-items:center; gap:.5rem; padding:.28rem .9rem; border-bottom:1px solid var(--sep); font-size:.72rem; }
.schema-col:last-child { border-bottom:none; }
.schema-col-name { color:var(--text); font-family:var(--mono); flex:1; }
.schema-col-type { color:var(--text-3); font-size:.65rem; font-family:var(--mono); }
.schema-col-pk { font-size:.55rem; font-weight:700; color:#f59e0b; background:rgba(245,158,11,.12); padding:.06rem .28rem; border-radius:3px; }
.schema-col-fk { font-size:.55rem; font-weight:700; color:#60a5fa; background:rgba(96,165,250,.12); padding:.06rem .28rem; border-radius:3px; }
.schema-col-null { font-size:.55rem; color:var(--text-4); }
.schema-fk-line { font-size:.65rem; color:var(--text-3); padding:.2rem .9rem; font-style:italic; border-bottom:1px solid var(--sep); }
.schema-fk-line:last-child { border-bottom:none; }
.schema-fk-badge { color:var(--blue); font-family:var(--mono); }

.dba-flash { padding:.5rem .9rem; border-radius:var(--r-sm); margin-bottom:.9rem; font-size:.8rem; font-weight:500; }
.dba-flash.success { background:var(--green-bg); color:var(--green); border:1px solid rgba(34,201,126,.18); }
.dba-flash.error   { background:var(--red-bg);   color:var(--red);   border:1px solid rgba(240,74,110,.18); }

.dba-nav { display:flex; gap:4px; margin-bottom:1.2rem; }
.dba-nav a { padding:.3rem .7rem; border-radius:var(--r-sm); font-size:.78rem; color:var(--text-3); text-decoration:none; border:1px solid transparent; transition:all .12s; font-weight:500; }
.dba-nav a:hover { background:var(--surface2); color:var(--text); }
.dba-nav a.active { background:var(--accent-bg); color:var(--accent); border-color:var(--accent-border); }
</style>

<div class="dba-wrap">
<aside class="dba-sidebar">
    <div class="dba-sidebar-title">Tables</div>
    <?php
    $counts = getTableCounts($pdo, $ALLOWED_TABLES);
    foreach ($ALLOWED_TABLES as $t):
        $isActive = $t === $table && $action !== 'schema';
    ?>
    <a href="db_admin.php?table=<?= urlencode($t) ?>&action=browse"
       class="dba-table-link <?= $isActive ? 'active' : '' ?>">
        <span><?= htmlspecialchars($t) ?></span>
        <span class="dba-table-count"><?= number_format($counts[$t]) ?></span>
    </a>
    <?php endforeach; ?>

    <div class="dba-sidebar-title" style="margin-top:.75rem;">Views</div>
    <a href="db_admin.php?action=schema" class="dba-table-link <?= $action==='schema'?'active':'' ?>">
        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M3 9h18M9 21V9"/></svg> Schema
    </a>
</aside>

<div class="dba-main">

<?php if ($flash): [$ftype, $fmsg] = explode(':', $flash, 2); ?>
<div class="dba-flash <?= $ftype ?>"><?= htmlspecialchars($fmsg) ?></div>
<?php endif; ?>

<?php if ($action === 'schema'): ?>
<?php
$dbName    = DB_NAME;
$relations = getRelations($pdo, $dbName);
$fkMap     = [];
foreach ($relations as $r) {
    $fkMap[$r['TABLE_NAME']][] = $r;
}
?>
<div class="dba-topbar">
    <div>
        <div class="dba-title">Database Schema</div>
        <div class="dba-subtitle"><?= htmlspecialchars(DB_NAME) ?> · <?= count($ALLOWED_TABLES) ?> tables</div>
    </div>
</div>

<div class="schema-grid">
<?php foreach ($ALLOWED_TABLES as $t):
    $tableCols = getColumns($pdo, $t);
    $pk = getPk($pdo, $t);
    $fks = $fkMap[$t] ?? [];
    $fkCols = array_column($fks, 'COLUMN_NAME');
?>
<div class="schema-card">
    <div class="schema-card-head">
        <a href="db_admin.php?table=<?= urlencode($t) ?>&action=browse"
           class="schema-card-name"><?= htmlspecialchars($t) ?></a>
        <span class="schema-card-count"><?= number_format($counts[$t]) ?> rows</span>
    </div>
    <?php foreach ($tableCols as $col): ?>
    <div class="schema-col">
        <span class="schema-col-name"><?= htmlspecialchars($col['Field']) ?></span>
        <span class="schema-col-type"><?= htmlspecialchars(strtolower($col['Type'])) ?></span>
        <?php if ($col['Field'] === $pk): ?>
            <span class="schema-col-pk">PK</span>
        <?php elseif (in_array($col['Field'], $fkCols)): ?>
            <span class="schema-col-fk">FK</span>
        <?php endif; ?>
        <?php if ($col['Null'] === 'YES'): ?>
            <span class="schema-col-null">null</span>
        <?php endif; ?>
    </div>
    <?php endforeach; ?>
    <?php foreach ($fks as $fk): ?>
    <div class="schema-fk-line">
        <span class="schema-fk-badge"><?= htmlspecialchars($fk['COLUMN_NAME']) ?></span>
        → <?= htmlspecialchars($fk['REFERENCED_TABLE_NAME']) ?>.<span class="schema-fk-badge"><?= htmlspecialchars($fk['REFERENCED_COLUMN_NAME']) ?></span>
    </div>
    <?php endforeach; ?>
</div>
<?php endforeach; ?>
</div>

<?php elseif ($action === 'edit' && $editRow): ?>
<?php $pk = getPk($pdo, $table); ?>
<div class="dba-topbar">
    <div>
        <div class="dba-title">Edit Row</div>
        <div class="dba-subtitle"><?= htmlspecialchars($table) ?> · <?= $pk ?> = <?= $rowId ?></div>
    </div>
    <a href="db_admin.php?table=<?= urlencode($table) ?>&action=browse" class="btn btn-sm btn-outline">← Back</a>
</div>

<div class="dba-edit-card">
    <form method="post" action="db_admin.php?table=<?= urlencode($table) ?>&id=<?= $rowId ?>&action=save">
        <div class="dba-field-grid">
        <?php foreach ($cols as $col):
            $name = $col['Field'];
            $val  = $editRow[$name] ?? '';
            $isPk = $name === $pk;
            $isLong = str_contains($col['Type'], 'text') || str_contains($col['Type'], 'json');
        ?>
        <div class="dba-field" style="<?= $isLong ? 'grid-column:1/-1' : '' ?>">
            <label><?= htmlspecialchars($name) ?>
                <?php if ($isPk): ?> <span style="color:var(--orange);font-size:.55rem;">PK</span><?php endif; ?>
                <span style="font-weight:400;color:var(--text-4);font-size:.6rem;"> <?= htmlspecialchars($col['Type']) ?></span>
            </label>
            <?php if ($isPk): ?>
                <div class="pk-field"><?= htmlspecialchars((string)$val) ?></div>
            <?php elseif ($isLong): ?>
                <textarea name="col_<?= htmlspecialchars($name) ?>" rows="4"><?= htmlspecialchars((string)$val) ?></textarea>
            <?php elseif (str_starts_with($col['Type'], 'enum')): ?>
                <?php
                preg_match("/^enum\((.+)\)$/i", $col['Type'], $em);
                $options = $em ? array_map(fn($o) => trim($o, "'\" "), explode(',', $em[1])) : [];
                ?>
                <select name="col_<?= htmlspecialchars($name) ?>">
                    <?php if ($col['Null'] === 'YES'): ?><option value="">NULL</option><?php endif; ?>
                    <?php foreach ($options as $opt): ?>
                    <option <?= $val === $opt ? 'selected' : '' ?>><?= htmlspecialchars($opt) ?></option>
                    <?php endforeach; ?>
                </select>
            <?php else: ?>
                <input type="text" name="col_<?= htmlspecialchars($name) ?>"
                       value="<?= htmlspecialchars((string)$val) ?>"
                       <?= $name === 'password' ? 'placeholder="Leave blank to keep current"' : '' ?>>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
        </div>
        <div style="display:flex;gap:.45rem;margin-top:1.1rem;">
            <button type="submit" class="btn btn-sm">Save Changes</button>
            <a href="db_admin.php?table=<?= urlencode($table) ?>&action=browse" class="btn btn-sm btn-outline">Cancel</a>
        </div>
    </form>
</div>

<?php else: ?>
<div class="dba-topbar">
    <div>
        <div class="dba-title"><?= htmlspecialchars($table) ?></div>
        <div class="dba-subtitle"><?= number_format($totalRows) ?> rows · <?= count($cols) ?> columns</div>
    </div>
    <form method="get" style="display:flex;gap:.4rem;align-items:center;">
        <input type="hidden" name="table" value="<?= htmlspecialchars($table) ?>">
        <input type="search" name="q" class="dba-search" placeholder="Search all columns…" value="<?= htmlspecialchars($search) ?>">
        <button type="submit" class="btn btn-sm">Search</button>
        <?php if ($search): ?>
        <a href="db_admin.php?table=<?= urlencode($table) ?>" class="btn btn-sm btn-outline">Clear</a>
        <?php endif; ?>
    </form>
</div>

<?php if (empty($rows)): ?>
<div class="flash flash-warning">No rows found<?= $search ? ' matching "'.htmlspecialchars($search).'"' : '' ?>.</div>
<?php else: ?>
<div class="dba-table-wrap">
<table class="dba-table">
    <thead>
        <tr>
            <?php foreach ($colNames as $c): ?>
            <th><?= htmlspecialchars($c) ?></th>
            <?php endforeach; ?>
            <th style="width:100px;">Actions</th>
        </tr>
    </thead>
    <tbody>
    <?php
    $pk = getPk($pdo, $table);
    foreach ($rows as $row):
        $pkVal = $row[$pk] ?? 0;
    ?>
    <tr>
        <?php foreach ($colNames as $c):
            $v = $row[$c];
            $isPk    = $c === $pk;
            $isNull  = $v === null;
            $isJson  = is_string($v) && strlen($v) > 0 && ($v[0] === '{' || $v[0] === '[');
            $display = $isNull ? 'NULL' : (is_string($v) && strlen($v) > 60 ? substr($v,0,60).'…' : $v);
        ?>
        <td class="<?= $isPk ? 'pk' : ($isNull ? 'null' : ($isJson ? 'json-cell' : '')) ?>"
            title="<?= htmlspecialchars((string)$v) ?>">
            <?= htmlspecialchars((string)$display) ?>
        </td>
        <?php endforeach; ?>
        <td>
            <div style="display:flex;gap:3px;">
                <a href="db_admin.php?table=<?= urlencode($table) ?>&action=edit&id=<?= (int)$pkVal ?>"
                   class="icon-btn" title="Edit" style="width:26px;height:26px;">
                    <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4z"/></svg>
                </a>
                <?php if (!($table === 'users' && (int)$pkVal === 1)): ?>
                <form method="post" style="display:inline;"
                      onsubmit="return confirm('Delete this row permanently?')">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="table" value="<?= htmlspecialchars($table) ?>">
                    <input type="hidden" name="id"    value="<?= (int)$pkVal ?>">
                    <button type="submit" class="icon-btn danger" title="Delete" style="width:26px;height:26px;">
                        <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4h6v2"/></svg>
                    </button>
                </form>
                <?php endif; ?>
            </div>
        </td>
    </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>

<?php if ($totalPages > 1): ?>
<div class="dba-pager">
    <?php for ($i = 1; $i <= $totalPages; $i++):
        $url = "db_admin.php?table=".urlencode($table)."&p=$i".($search?"&q=".urlencode($search):'');
    ?>
    <a href="<?= $url ?>" class="dba-page-btn <?= $i===$page?'active':'' ?>"><?= $i ?></a>
    <?php endfor; ?>
    <span class="dba-page-info">Page <?= $page ?> of <?= $totalPages ?> · <?= number_format($totalRows) ?> total rows</span>
</div>
<?php endif; ?>
<?php endif; ?>
<?php endif; ?>

</div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
