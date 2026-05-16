<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db.php';

function authenticateApiToken(): ?array {
    $header = $_SERVER['HTTP_AUTHORIZATION']
           ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION']
           ?? (function_exists('getallheaders') ? (getallheaders()['Authorization'] ?? getallheaders()['authorization'] ?? '') : '');
    if (preg_match('/^Bearer\s+(.+)$/i', trim($header), $m)) {
        return resolveToken(trim($m[1]));
    }

    if (!empty($_GET['token'])) {
        return resolveToken(trim($_GET['token']));
    }
    $raw = file_get_contents('php://input');
    if ($raw) {
        $body = json_decode($raw, true) ?? [];
        if (!empty($body['token'])) {
            return resolveToken(trim($body['token']));
        }
    }

    return null;
}

function resolveToken(string $tok): ?array {
    if (strlen($tok) < 32) return null;

    if (TESTING_MODE) {
        $file = __DIR__ . '/../data/api_tokens.json';
        if (!file_exists($file)) return null;
        $rows = json_decode(file_get_contents($file), true) ?? [];
        foreach ($rows as $row) {
            if (hash_equals($row['token'], $tok)) {
                $row['last_used'] = date('Y-m-d H:i:s');
                $rows = array_map(fn($r) => $r['token'] === $tok ? $row : $r, $rows);
                file_put_contents($file, json_encode($rows, JSON_PRETTY_PRINT));
                return DB::findUserById((int)$row['user_id']);
            }
        }
        return null;
    }

    $pdo  = DB::getConnection();
    $stmt = $pdo->prepare('SELECT * FROM api_tokens WHERE token = :t LIMIT 1');
    $stmt->execute(['t' => $tok]);
    $row  = $stmt->fetch(\PDO::FETCH_ASSOC);
    if (!$row) return null;

    $pdo->prepare('UPDATE api_tokens SET last_used = NOW() WHERE token = :t')->execute(['t' => $tok]);

    return DB::findUserById((int)$row['user_id']);
}

function generateApiToken(): string {
    return bin2hex(random_bytes(32)); // 64-char hex
}

function createApiToken(int $userId, string $name = 'Mi Integración'): string {
    $token = generateApiToken();

    if (TESTING_MODE) {
        $file = __DIR__ . '/../data/api_tokens.json';
        $rows = file_exists($file) ? (json_decode(file_get_contents($file), true) ?? []) : [];
        $rows[] = [
            'id'         => count($rows) + 1,
            'user_id'    => $userId,
            'token'      => $token,
            'name'       => $name,
            'last_used'  => null,
            'created_at' => date('Y-m-d H:i:s'),
        ];
        file_put_contents($file, json_encode($rows, JSON_PRETTY_PRINT));
    } else {
        $pdo = DB::getConnection();
        $pdo->prepare('INSERT INTO api_tokens (user_id, token, name) VALUES (:u, :t, :n)')
            ->execute(['u' => $userId, 't' => $token, 'n' => $name]);
    }

    return $token;
}

function getUserApiTokens(int $userId): array {
    if (TESTING_MODE) {
        $file = __DIR__ . '/../data/api_tokens.json';
        if (!file_exists($file)) return [];
        $rows = json_decode(file_get_contents($file), true) ?? [];
        return array_values(array_filter($rows, fn($r) => (int)$r['user_id'] === $userId));
    }
    $pdo  = DB::getConnection();
    $stmt = $pdo->prepare('SELECT * FROM api_tokens WHERE user_id = :u ORDER BY created_at DESC');
    $stmt->execute(['u' => $userId]);
    return $stmt->fetchAll(\PDO::FETCH_ASSOC);
}

function deleteApiToken(int $tokenId, int $userId): bool {
    if (TESTING_MODE) {
        $file = __DIR__ . '/../data/api_tokens.json';
        if (!file_exists($file)) return false;
        $rows = json_decode(file_get_contents($file), true) ?? [];
        $new  = array_values(array_filter($rows, fn($r) => !((int)$r['id'] === $tokenId && (int)$r['user_id'] === $userId)));
        file_put_contents($file, json_encode($new, JSON_PRETTY_PRINT));
        return count($new) < count($rows);
    }
    $pdo  = DB::getConnection();
    $stmt = $pdo->prepare('DELETE FROM api_tokens WHERE id = :id AND user_id = :u');
    $stmt->execute(['id' => $tokenId, 'u' => $userId]);
    return $stmt->rowCount() > 0;
}
