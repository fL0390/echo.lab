<?php
require_once __DIR__ . '/db.php';

function can(string $feature): bool {
    $u = currentUser();
    if (!$u) return false;
    $perms = PERMISSIONS;
    if (!isset($perms[$feature])) return false;
    return in_array((int)$u['role'], $perms[$feature], true);
}

function requirePermission(string $feature): void {
    requireLogin();
    if (!can($feature)) {
        http_response_code(403);
        $prefix = str_contains($_SERVER['PHP_SELF'], '/admin/') ? '../' : '';
        echo '<!DOCTYPE html><html><head><meta charset="UTF-8">
              <link rel="stylesheet" href="' . $prefix . 'assets/css/style.css"></head><body>
              <div class="error-page"><div class="error-box">
              <h1>403</h1><p>You don\'t have permission to access this page.</p>
              <a href="' . $prefix . 'index.php" class="btn">Go Back</a>
              </div></div></body></html>';
        exit;
    }
}


function detectMime(string $filepath, string $origName = ''): string {
    if (function_exists('mime_content_type')) {
        $m = @mime_content_type($filepath);
        if ($m && $m !== 'application/octet-stream') return $m;
    }
    if (class_exists('finfo')) {
        $fi = @new finfo(FILEINFO_MIME_TYPE);
        if ($fi) { $m = @$fi->file($filepath); if ($m && $m !== 'application/octet-stream') return $m; }
    }
    $ext = strtolower(pathinfo($origName ?: $filepath, PATHINFO_EXTENSION));
    return ['jpg'=>'image/jpeg','jpeg'=>'image/jpeg','png'=>'image/png','gif'=>'image/gif','webp'=>'image/webp'][$ext]
        ?? ($_FILES['avatar']['type'] ?? 'application/octet-stream');
}

function processAvatarUpload(array $file, int $userId, string $oldAvatar = ''): ?string {
    $maxSize = 2 * 1024 * 1024;
    $allowed = ['image/jpeg','image/png','image/gif','image/webp'];
    if ($file['size'] > $maxSize) { setFlash('error', 'File too large (max 2 MB).'); return null; }
    $mime = detectMime($file['tmp_name'], $file['name']);
    if (!in_array($mime, $allowed)) { setFlash('error', 'Only JPG, PNG, GIF, WEBP allowed.'); return null; }
    $dir = __DIR__ . '/data/avatars';
    if (!is_dir($dir)) mkdir($dir, 0775, true);
    if ($oldAvatar && file_exists($dir . '/' . $oldAvatar)) @unlink($dir . '/' . $oldAvatar);
    if (function_exists('imagecreatetruecolor')) {
        try {
            $src = match($mime) {
                'image/jpeg' => @imagecreatefromjpeg($file['tmp_name']),
                'image/png'  => @imagecreatefrompng($file['tmp_name']),
                'image/gif'  => @imagecreatefromgif($file['tmp_name']),
                'image/webp' => @imagecreatefromwebp($file['tmp_name']),
                default      => false,
            };
            if ($src) {
                $w = imagesx($src); $h = imagesy($src); $s = min($w, $h);
                $dst = imagecreatetruecolor(256, 256);
                imagecopyresampled($dst, $src, 0, 0, (int)(($w-$s)/2), (int)(($h-$s)/2), 256, 256, $s, $s);
                $fname = 'av_' . $userId . '_' . time() . '.jpg';
                imagejpeg($dst, $dir . '/' . $fname, 85);
                imagedestroy($src); imagedestroy($dst);
                return $fname;
            }
        } catch (\Throwable $e) {}
    }
    $ext = match($mime) { 'image/png'=>'png','image/gif'=>'gif','image/webp'=>'webp',default=>'jpg' };
    $fname = 'av_' . $userId . '_' . time() . '.' . $ext;
    move_uploaded_file($file['tmp_name'], $dir . '/' . $fname);
    return $fname;
}


function isOffensiveUsername(string $name): bool {
    $blocked = [
        'nigger','nigga','faggot','fag','retard','cunt','bitch','whore','slut',
        'tranny','spic','kike','chink','wetback','raghead','gook','dyke',
        'pussy','cock','dick','penis','vagina','anus','asshole','shit','fuck',
        'rape','molest','pedo','nazi','hitler',
    ];
    $lower = strtolower($name);
    foreach ($blocked as $w) {
        if (str_contains($lower, $w)) return true;
    }
    return false;
}


function isLoggedIn(): bool    { return isset($_SESSION['user_id']); }
function currentUser(): ?array { return isLoggedIn() ? DB::findUserById((int)$_SESSION['user_id']) : null; }

function requireLogin(): void {
    if (!isLoggedIn()) { header('Location: ' . (str_contains($_SERVER['PHP_SELF'], '/admin/') ? '../' : '') . 'login.php'); exit; }
}

function requireRole(int $minRole): void {
    requireLogin();
    $u = currentUser();
    if (!$u || (int)$u['role'] < $minRole) {
        http_response_code(403);
        $prefix = str_contains($_SERVER['PHP_SELF'], '/admin/') ? '../' : '';
        echo '<!DOCTYPE html><html><head><meta charset="UTF-8">
              <link rel="stylesheet" href="' . $prefix . 'assets/css/style.css"></head><body>
              <div class="error-page"><div class="error-box">
              <h1>403</h1><p>You don\'t have permission to access this page.</p>
              <a href="' . $prefix . 'index.php" class="btn">Go Back</a>
              </div></div></body></html>';
        exit;
    }
}

function isBanned(): bool    { $u = currentUser(); return $u && (int)$u['role'] === ROLE_BANNED; }

function enforceBan(): void {
    if (!isBanned()) return;
    $u   = currentUser();
    $msg = $u['ban_message'] ?? '';
    echo '<!DOCTYPE html><html><head><meta charset="UTF-8">
          <link rel="stylesheet" href="assets/css/style.css"></head><body>
          <div class="error-page"><div class="error-box" style="max-width:380px;">
          <h1 style="color:var(--red);font-size:1.4rem;">Suspended</h1>
          <p>Your account has been suspended.</p>'
        . ($msg ? '<p style="color:var(--text-3);font-size:.82rem;margin-top:.25rem;">Reason: ' . htmlspecialchars($msg) . '</p>' : '')
        . '<a href="logout.php" class="btn btn-danger" style="margin-top:1rem;">Sign Out</a>
          </div></div></body></html>';
    exit;
}

function setFlash(string $type, string $message): void { $_SESSION['flash'] = ['type' => $type, 'message' => $message]; }
function getFlash(): ?array { $f = $_SESSION['flash'] ?? null; unset($_SESSION['flash']); return $f; }
