<?php
require_once __DIR__ . '/../lang.php';
require_once __DIR__ . '/../auth.php';

// Solo Administradores
$me = currentUser();
if (!$me || (int)$me['role'] < ROLE_ADMIN) {
    http_response_code(403);
    require_once __DIR__ . '/../includes/header.php';
    echo '<div class="main-content" style="display:flex;align-items:center;justify-content:center;min-height:60vh;">';
    echo '<div style="text-align:center"><div style="font-size:2.5rem;margin-bottom:.75rem">🔒</div>';
    echo '<h2 style="font-size:1.1rem;margin-bottom:.5rem">Admin only</h2>';
    echo '<a href="../index.php" class="btn btn-sm btn-outline">Dashboard</a></div></div>';
    require_once __DIR__ . '/../includes/footer.php';
    exit;
}

$pdo = DB::getConnection();

$TABLES = [
    'users'               => ['hide' => ['password','reset_token','email_confirm_token']],
    'vms'                 => ['hide' => []],
    'centers'             => ['hide' => []],
    'groups'              => ['hide' => []],
    'group_members'       => ['hide' => []],
    'isos'                => ['hide' => []],
    'proxmox_isos'        => ['hide' => []],
    'nodes'               => ['hide' => []],
    'api_tokens'          => ['hide' => ['token']],
    'banned_ips'          => ['hide' => []],
    'logs'                => ['hide' => []],
    'email_confirmations' => ['hide' => ['token']],
];

// ── POST: edit/delete
$action  = $_POST['action'] ?? '';
$table   = $_POST['table']  ?? '';
$rowId   = (int)($_POST['row_id'] ?? 0);
$colName = $_POST['col'] ?? '';
$newVal  = $_POST['val'] ?? '';
$pkCol   = $_POST['pk'] ?? 'id';

// Tabla
$safeTable = isset($TABLES[$table]) ? $table : null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $safeTable) {

    if ($action === 'delete' && $rowId) {
        if ($safeTable === 'users' && $rowId === 1) {
            setFlash('error', 'Cannot delete root admin.');
        } else {
            $safeCol = preg_replace('/[^a-zA-Z0-9_]/', '', $pkCol);
            $pdo->prepare("DELETE FROM `{$safeTable}` WHERE `{$safeCol}` = ?")->execute([$rowId]);
            setFlash('success', "Row deleted from {$safeTable}.");
        }
    }

    if ($action === 'update' && $rowId && $colName) {
        $cols = array_column($pdo->query("DESCRIBE `{$safeTable}`")->fetchAll(PDO::FETCH_ASSOC), 'Field');
        if (in_array($colName, $cols)) {
            $safeCol  = preg_replace('/[^a-zA-Z0-9_]/', '', $colName);
            $safePk   = preg_replace('/[^a-zA-Z0-9_]/', '', $pkCol);
            $finalVal = ($newVal === '__NULL__') ? null : $newVal;
            $pdo->prepare("UPDATE `{$safeTable}` SET `{$safeCol}` = ? WHERE `{$safePk}` = ?")->execute([$finalVal, $rowId]);
            setFlash('success', "Updated {$safeTable}.{$safeCol}.");
        }
    }

    header('Location: db.php?t=' . urlencode($safeTable)); exit;
}

// ── GET: cargar datos
$activeTable  = (isset($_GET['t']) && isset($TABLES[$_GET['t']])) ? $_GET['t'] : 'users';
$hideCols     = $TABLES[$activeTable]['hide'] ?? [];
$search       = trim($_GET['q'] ?? '');
$page         = max(1, (int)($_GET['p'] ?? 1));
$perPage      = 50;
$offset       = ($page - 1) * $perPage;

// Columnas
$cols = $pdo->query("DESCRIBE `{$activeTable}`")->fetchAll(PDO::FETCH_ASSOC);
$pkField = 'id';
foreach ($cols as $c) { if ($c['Key'] === 'PRI') { $pkField = $c['Field']; break; } }
$visibleCols = array_filter($cols, fn($c) => !in_array($c['Field'], $hideCols));

// Obtener filas con búsqueda opcional
$where = '';
$params = [];
if ($search !== '') {
    $likeParts = [];
    foreach ($cols as $c) {
        if (!in_array($c['Field'], $hideCols)) {
            $likeParts[] = "`{$c['Field']}` LIKE ?";
            $params[] = "%{$search}%";
        }
    }
    if ($likeParts) $where = 'WHERE ' . implode(' OR ', $likeParts);
}

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM `{$activeTable}` {$where}");
$countStmt->execute($params);
$totalRows = (int)$countStmt->fetchColumn();
$totalPages = max(1, (int)ceil($totalRows / $perPage));

$dataStmt = $pdo->prepare("SELECT * FROM `{$activeTable}` {$where} ORDER BY `{$pkField}` DESC LIMIT {$perPage} OFFSET {$offset}");
$dataStmt->execute($params);
$rows = $dataStmt->fetchAll(PDO::FETCH_ASSOC);

// Obtener información de la relación para visualización
function getRelated(PDO $pdo, string $table, string $col, $val): string {
    if ($val === null || $val === '') return '<span style="color:var(--text-4)">—</span>';
    $maps = [
        'users.id'     => ['users',   'username'],
        'centers.id'   => ['centers', 'name'],
        'groups.id'    => ['groups',  'name'],
        'vms.id'       => ['vms',     'name'],
        'created_by'   => ['users',   'username'],
        'banned_by'    => ['users',   'username'],
        'uploaded_by'  => ['users',   'username'],
        'user_id'      => ['users',   'username'],
        'center_id'    => ['centers', 'name'],
        'group_id'     => ['groups',  'name'],
        'assigned_to'  => ['users',   'username'],
        'template_id'  => ['vms',     'name'],
    ];
    $key = "{$table}.{$col}";
    $ref = $maps[$key] ?? $maps[$col] ?? null;
    if (!$ref) return htmlspecialchars((string)$val);
    try {
        $s = $pdo->prepare("SELECT `{$ref[1]}` FROM `{$ref[0]}` WHERE id = ? LIMIT 1");
        $s->execute([$val]);
        $label = $s->fetchColumn();
        if ($label !== false) {
            return '<span title="ID: '.htmlspecialchars((string)$val).'" style="color:var(--accent-2)">'
                .htmlspecialchars($label).'</span>';
        }
    } catch (\Throwable $e) {}
    return htmlspecialchars((string)$val);
}

$pageTitle  = 'DB Explorer';
$wideLayout = true;
require_once __DIR__ . '/../includes/header.php';
?>

<style>
.dbx-wrap { display:grid; grid-template-columns:180px 1fr; gap:1.5rem; max-width:1200px; margin:0 auto; padding:1.5rem 1rem 3rem; align-items:start; }
.dbx-sidebar { position:sticky; top:72px; background:var(--surface); border:1px solid var(--sep); border-radius:var(--r); padding:.65rem; }
.dbx-sidebar-title { font-size:.6rem; font-weight:700; text-transform:uppercase; letter-spacing:.08em; color:var(--text-3); padding:.2rem .45rem .5rem; }
.dbx-tbl-link { display:flex; align-items:center; justify-content:space-between; padding:.3rem .5rem; border-radius:var(--r-sm); color:var(--text-2); font-size:.78rem; font-weight:500; text-decoration:none; transition:all .12s; }
.dbx-tbl-link:hover { background:var(--surface2); color:var(--text); }
.dbx-tbl-link.active { background:var(--accent-bg); color:var(--accent); border:1px solid var(--accent-border); }
.dbx-tbl-count { font-size:.62rem; color:var(--text-3); font-weight:400; font-family:var(--mono); }
.dbx-main { min-width:0; }
.dbx-header { display:flex; align-items:center; gap:.65rem; margin-bottom:.9rem; flex-wrap:wrap; }
.dbx-header h1 { font-size:1.05rem; font-weight:700; letter-spacing:-.02em; flex:1; }
.dbx-search { flex:1; min-width:160px; padding:.4rem .7rem; background:var(--bg); border:1px solid var(--sep-bold); border-radius:var(--r-sm); color:var(--text); font-family:var(--font); font-size:.8rem; outline:none; transition:border-color .15s; }
.dbx-search:focus { border-color:var(--accent); }
.dbx-table-wrap { overflow-x:auto; border-radius:var(--r); border:1px solid var(--sep); background:var(--surface); }
.dbx-table { width:100%; border-collapse:collapse; font-size:.76rem; }
.dbx-table th { background:var(--surface2); color:var(--text-3); font-size:.62rem; font-weight:700; text-transform:uppercase; letter-spacing:.06em; padding:.45rem .6rem; text-align:left; border-bottom:1px solid var(--sep); white-space:nowrap; }
.dbx-table td { padding:.42rem .6rem; border-bottom:1px solid var(--sep); color:var(--text-2); max-width:260px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; vertical-align:middle; }
.dbx-table tr:last-child td { border-bottom:none; }
.dbx-table tr:hover td { background:rgba(123,94,167,.04); }
.dbx-table td.pk-col { color:var(--text-3); font-family:var(--mono); font-size:.68rem; }
.dbx-table td.null-val { color:var(--text-4); font-style:italic; }
.dbx-table td.json-val { color:var(--orange); font-family:var(--mono); font-size:.66rem; }
.edit-btn { background:none; border:none; cursor:pointer; color:var(--text-4); padding:.1rem; border-radius:3px; transition:color .12s; }
.edit-btn:hover { color:var(--accent); }
.inline-edit-form { display:none; align-items:center; gap:.3rem; }
.inline-edit-form.open { display:flex; }
.inline-input { padding:.2rem .4rem; background:var(--bg); border:1px solid var(--accent); border-radius:var(--r-sm); color:var(--text); font-family:var(--font); font-size:.75rem; outline:none; max-width:180px; }
.inline-save { padding:.18rem .4rem; background:var(--accent); color:#fff; border:none; border-radius:var(--r-sm); font-size:.68rem; cursor:pointer; font-family:var(--font); }
.inline-cancel { padding:.18rem .4rem; background:var(--surface3); color:var(--text-2); border:none; border-radius:var(--r-sm); font-size:.68rem; cursor:pointer; font-family:var(--font); }
.del-btn { background:none; border:1px solid rgba(240,74,110,.2); color:var(--red); border-radius:var(--r-sm); padding:.18rem .4rem; font-size:.65rem; cursor:pointer; font-family:var(--font); transition:all .12s; }
.del-btn:hover { background:var(--red-bg); }
.dbx-pagination { display:flex; align-items:center; gap:.3rem; margin-top:.85rem; flex-wrap:wrap; }
.dbx-page-btn { padding:.28rem .55rem; border-radius:var(--r-sm); border:1px solid var(--sep-bold); background:var(--surface); color:var(--text-2); font-size:.74rem; text-decoration:none; transition:all .12s; }
.dbx-page-btn:hover { border-color:var(--accent-border); color:var(--accent); }
.dbx-page-btn.active { background:var(--accent); color:#fff; border-color:var(--accent); }
.dbx-info { font-size:.72rem; color:var(--text-3); margin-left:auto; }
.role-chip { display:inline-block; padding:.08rem .35rem; border-radius:var(--r-pill); font-size:.63rem; font-weight:600; }
.role-0 { background:var(--red-bg);    color:var(--red); }
.role-1 { background:var(--surface3);  color:var(--text-3); }
.role-2 { background:var(--green-bg);  color:var(--green); }
.role-3 { background:var(--accent-bg); color:var(--accent); }
.role-4 { background:var(--orange-bg); color:var(--orange); }
.role-5 { background:rgba(240,74,110,.1); color:#f87185; }
.status-chip { display:inline-block; padding:.08rem .35rem; border-radius:var(--r-pill); font-size:.63rem; font-weight:600; }
.status-running { background:var(--green-bg);  color:var(--green); }
.status-stopped { background:var(--surface3);  color:var(--text-3); }
.status-error   { background:var(--red-bg);    color:var(--red); }
@media(max-width:768px) {
    .dbx-wrap { grid-template-columns:1fr; }
    .dbx-sidebar { position:static; display:flex; gap:.3rem; flex-wrap:wrap; padding:.5rem; }
    .dbx-tbl-link { font-size:.72rem; }
}
</style>

<div class="dbx-wrap">

    <!-- Sidebar -->
    <aside class="dbx-sidebar">
        <div class="dbx-sidebar-title">Tables</div>
        <?php foreach (array_keys($TABLES) as $tbl):
            $cnt = (int)$pdo->query("SELECT COUNT(*) FROM `{$tbl}`")->fetchColumn();
        ?>
        <a href="db.php?t=<?= urlencode($tbl) ?>" class="dbx-tbl-link <?= $tbl===$activeTable?'active':'' ?>">
            <span><?= htmlspecialchars($tbl) ?></span>
            <span class="dbx-tbl-count"><?= number_format($cnt) ?></span>
        </a>
        <?php endforeach; ?>
    </aside>

    <!-- Main -->
    <main class="dbx-main">

        <?php
        if ($f = getFlash()): ?>
        <div class="flash flash-<?= $f['type'] ?>" style="margin-bottom:.85rem;"><?= htmlspecialchars($f['msg']) ?></div>
        <?php endif; ?>

        <div class="dbx-header">
            <h1 style="display:flex;align-items:center;gap:.5rem;">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="var(--accent)" stroke-width="2" stroke-linecap="round"><ellipse cx="12" cy="5" rx="9" ry="3"/><path d="M21 12c0 1.66-4 3-9 3s-9-1.34-9-3"/><path d="M3 5v14c0 1.66 4 3 9 3s9-1.34 9-3V5"/></svg>
                <?= htmlspecialchars($activeTable) ?>
            </h1>
            <form method="get" style="display:flex;gap:.4rem;align-items:center;flex:1;">
                <input type="hidden" name="t" value="<?= htmlspecialchars($activeTable) ?>">
                <input type="search" name="q" value="<?= htmlspecialchars($search) ?>"
                       class="dbx-search" placeholder="Search visible columns...">
                <button type="submit" class="btn btn-sm">Search</button>
                <?php if ($search): ?><a href="db.php?t=<?= urlencode($activeTable) ?>" class="btn btn-sm btn-outline">Clear</a><?php endif; ?>
            </form>
            <span class="dbx-info"><?= number_format($totalRows) ?> rows</span>
        </div>

        <div class="dbx-table-wrap">
            <table class="dbx-table">
                <thead>
                    <tr>
                        <?php foreach ($visibleCols as $col): ?>
                        <th><?= htmlspecialchars($col['Field']) ?></th>
                        <?php endforeach; ?>
                        <th style="width:80px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($rows)): ?>
                    <tr><td colspan="<?= count($visibleCols)+1 ?>" style="text-align:center;color:var(--text-3);padding:1.5rem;">No rows found.</td></tr>
                <?php endif; ?>
                <?php foreach ($rows as $row): ?>
                    <tr>
                        <?php foreach ($visibleCols as $col):
                            $field = $col['Field'];
                            $val   = $row[$field] ?? null;
                            $type  = strtolower($col['Type']);
                            $isPk  = ($field === $pkField);
                            $isFk  = str_ends_with($field, '_id') || in_array($field, ['created_by','banned_by','uploaded_by','user_id','assigned_to','template_id']);
                        ?>
                        <td class="<?= $isPk?'pk-col':'' ?>" id="cell-<?= $row[$pkField] ?>-<?= htmlspecialchars($field) ?>">
                            <div class="cell-display" id="disp-<?= $row[$pkField] ?>-<?= htmlspecialchars($field) ?>">
                                <?php if ($val === null): ?>
                                    <span class="null-val">NULL</span>
                                <?php elseif ($field === 'role'): ?>
                                    <span class="role-chip role-<?= (int)$val ?>"><?= getRoleName((int)$val) ?></span>
                                <?php elseif ($field === 'status' && in_array($val, ['running','stopped','error'])): ?>
                                    <span class="status-chip status-<?= $val ?>"><?= $val ?></span>
                                <?php elseif (str_starts_with($type, 'json') || (is_string($val) && str_starts_with(trim($val), '['))): ?>
                                    <span class="json-val" title="<?= htmlspecialchars($val) ?>"><?= htmlspecialchars(mb_substr($val,0,40)) ?>...</span>
                                <?php elseif ($isFk): ?>
                                    <?= getRelated($pdo, $activeTable, $field, $val) ?>
                                <?php else: ?>
                                    <span title="<?= htmlspecialchars((string)$val) ?>"><?= htmlspecialchars(mb_substr((string)$val, 0, 60)) ?><?= mb_strlen((string)$val) > 60 ? '…' : '' ?></span>
                                <?php endif; ?>
                                <?php if (!$isPk): ?>
                                <button class="edit-btn" onclick="openEdit('<?= $row[$pkField] ?>','<?= htmlspecialchars($field) ?>','<?= htmlspecialchars(addslashes((string)$val)) ?>')" title="Edit">
                                    <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4z"/></svg>
                                </button>
                                <?php endif; ?>
                            </div>
                            <?php if (!$isPk): ?>
                            <div class="inline-edit-form" id="edit-<?= $row[$pkField] ?>-<?= htmlspecialchars($field) ?>">
                                <form method="post" style="display:flex;gap:.25rem;align-items:center;">
                                    <input type="hidden" name="action"  value="update">
                                    <input type="hidden" name="table"   value="<?= htmlspecialchars($activeTable) ?>">
                                    <input type="hidden" name="row_id"  value="<?= $row[$pkField] ?>">
                                    <input type="hidden" name="pk"      value="<?= htmlspecialchars($pkField) ?>">
                                    <input type="hidden" name="col"     value="<?= htmlspecialchars($field) ?>">
                                    <input type="text"   name="val"     value="<?= htmlspecialchars((string)$val) ?>"
                                           class="inline-input" id="inp-<?= $row[$pkField] ?>-<?= htmlspecialchars($field) ?>">
                                    <button type="submit" class="inline-save">Save</button>
                                    <button type="button" class="inline-cancel" onclick="closeEdit('<?= $row[$pkField] ?>','<?= htmlspecialchars($field) ?>')">✕</button>
                                </form>
                            </div>
                            <?php endif; ?>
                        </td>
                        <?php endforeach; ?>
                        <td>
                            <form method="post" onsubmit="return confirm('Delete this row?')">
                                <input type="hidden" name="action"  value="delete">
                                <input type="hidden" name="table"   value="<?= htmlspecialchars($activeTable) ?>">
                                <input type="hidden" name="row_id"  value="<?= $row[$pkField] ?>">
                                <input type="hidden" name="pk"      value="<?= htmlspecialchars($pkField) ?>">
                                <button type="submit" class="del-btn">Delete</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Paginación -->
        <?php if ($totalPages > 1): ?>
        <div class="dbx-pagination">
            <?php
            $base = "db.php?t=".urlencode($activeTable).($search?"&q=".urlencode($search):"");
            for ($i = 1; $i <= $totalPages; $i++):
                if ($i === 1 || $i === $totalPages || abs($i - $page) <= 2):
            ?>
                <a href="<?= $base ?>&p=<?= $i ?>" class="dbx-page-btn <?= $i===$page?'active':'' ?>"><?= $i ?></a>
            <?php elseif (abs($i - $page) === 3): ?>
                <span style="color:var(--text-4);padding:.2rem .3rem;">…</span>
            <?php endif; ?>
            <?php endfor; ?>
            <span class="dbx-info">Page <?= $page ?> of <?= $totalPages ?></span>
        </div>
        <?php endif; ?>

    </main>
</div>

<script>
function openEdit(id, col, currentVal) {
    var disp = document.getElementById('disp-' + id + '-' + col);
    var form = document.getElementById('edit-' + id + '-' + col);
    var inp  = document.getElementById('inp-'  + id + '-' + col);
    if (!disp || !form) return;
    disp.style.display = 'none';
    form.classList.add('open');
    if (inp) { inp.value = currentVal === 'null' ? '' : currentVal; inp.focus(); inp.select(); }
}
function closeEdit(id, col) {
    var disp = document.getElementById('disp-' + id + '-' + col);
    var form = document.getElementById('edit-' + id + '-' + col);
    if (disp) disp.style.display = '';
    if (form) form.classList.remove('open');
}
// Cerrar edición al presionar Escape
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        document.querySelectorAll('.inline-edit-form.open').forEach(function(f) {
            f.classList.remove('open');
        });
        document.querySelectorAll('.cell-display').forEach(function(d) {
            d.style.display = '';
        });
    }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
