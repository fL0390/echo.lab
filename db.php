<?php
require_once __DIR__ . '/config.php';

class DB {
    private static ?PDO $pdo = null;
    private static string $jsonPath = __DIR__ . '/data/users.json';
    private static string $centersJsonPath = __DIR__ . '/data/centers.json';

    /* PDO (production) */
    public static function getConnection(): ?PDO {
        if (TESTING_MODE) return null;
        if (self::$pdo === null) {
            self::$pdo = new PDO(
                'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
                DB_USER, DB_PASS,
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );
        }
        return self::$pdo;
    }

    private static string $proxmoxIsosJsonPath = __DIR__ . '/data/proxmox_isos.json';

private static function loadProxmoxIsos(): array {
    if (!is_dir(__DIR__ . '/data')) mkdir(__DIR__ . '/data', 0755, true);
    if (!file_exists(self::$proxmoxIsosJsonPath)) return [];
    return json_decode(file_get_contents(self::$proxmoxIsosJsonPath), true) ?: [];
}

private static function saveProxmoxIsos(array $isos): void {
    if (!is_dir(__DIR__ . '/data')) mkdir(__DIR__ . '/data', 0755, true);
    file_put_contents(self::$proxmoxIsosJsonPath, json_encode(array_values($isos), JSON_PRETTY_PRINT));
}

/* Add a Proxmox ISO record to local tracking. */
public static function addProxmoxIso(string $volid, string $name, int $size, string $visibility, int $uploadedBy): array {
    if (TESTING_MODE) {
        $isos = self::loadProxmoxIsos();
        $maxId = 0;
        foreach ($isos as $iso) if ($iso['id'] > $maxId) $maxId = $iso['id'];
        $new = [
            'id'          => $maxId + 1,
            'volid'       => $volid,
            'name'        => trim($name),
            'size'        => $size,
            'visibility'  => $visibility,
            'uploaded_by' => $uploadedBy,
            'created_at'  => date('Y-m-d H:i:s'),
        ];
        $isos[] = $new;
        self::saveProxmoxIsos($isos);
        return $new;
    }
    $stmt = self::getConnection()->prepare(
        'INSERT INTO proxmox_isos (volid, name, size, visibility, uploaded_by) VALUES (:v, :n, :s, :vis, :u)'
    );
    $stmt->execute(['v' => $volid, 'n' => trim($name), 's' => $size, 'vis' => $visibility, 'u' => $uploadedBy]);
    $id = (int)self::getConnection()->lastInsertId();
    return self::getProxmoxIsoById($id);
}

/* Proxmox ISO ID. */
public static function getProxmoxIsoById(int $id): ?array {
    if (TESTING_MODE) {
        foreach (self::loadProxmoxIsos() as $iso) {
            if ((int)$iso['id'] === $id) return $iso;
        }
        return null;
    }
    $stmt = self::getConnection()->prepare('SELECT * FROM proxmox_isos WHERE id = :id');
    $stmt->execute(['id' => $id]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

/* Saca las ISOs para un usuario 
 * Los admins ven todas
 * Los profes ven las ISOs publicas, mas las que ellos subieron, mas las ISOs del centro al que pertenecen.
 */
public static function getProxmoxIsosForUser(int $userId, int $userRole, ?int $centerId): array {
    if (TESTING_MODE) {
        $isos = self::loadProxmoxIsos();
        if ($userRole >= ROLE_ADMIN) return $isos;
        $result = [];
        foreach ($isos as $iso) {
            if ($iso['visibility'] === 'public') {
                $result[] = $iso;
            } elseif ($iso['visibility'] === 'center' && $centerId !== null) {
                // En modo de prueba, no tenemos asociación de centro para ISOs; asumimos que el centro del cargador coincide
                $uploader = self::findUserById($iso['uploaded_by']);
                if ($uploader && $uploader['center_id'] === $centerId) $result[] = $iso;
            } elseif ($iso['uploaded_by'] === $userId) {
                $result[] = $iso;
            }
        }
        return $result;
    }

    if ($userRole >= ROLE_ADMIN) {
        $stmt = self::getConnection()->query('SELECT * FROM proxmox_isos ORDER BY created_at DESC');
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    $sql = 'SELECT pi.* FROM proxmox_isos pi
            LEFT JOIN users u ON pi.uploaded_by = u.id
            WHERE pi.visibility = "public"
               OR pi.uploaded_by = :uid
               OR (pi.visibility = "center" AND u.center_id = :cid)
            ORDER BY pi.created_at DESC';
    $stmt = self::getConnection()->prepare($sql);
    $stmt->execute(['uid' => $userId, 'cid' => $centerId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/* Borrar ISOs del sistema. */
public static function deleteProxmoxIso(int $id): bool {
    if (TESTING_MODE) {
        $isos = self::loadProxmoxIsos();
        foreach ($isos as $k => $iso) {
            if ((int)$iso['id'] === $id) {
                unset($isos[$k]);
                self::saveProxmoxIsos($isos);
                return true;
            }
        }
        return false;
    }
    $stmt = self::getConnection()->prepare('DELETE FROM proxmox_isos WHERE id = :id');
    return $stmt->execute(['id' => $id]);
}
    /* JSON helpers */
    private static function loadUsers(): array {
        if (!is_dir(__DIR__ . '/data')) mkdir(__DIR__ . '/data', 0755, true);
        if (!file_exists(self::$jsonPath)) {
            $seed = [[
                'id'          => 1,
                'username'    => 'admin',
                'email'       => 'admin@localhost',
                'password'    => password_hash('admin', PASSWORD_DEFAULT),
                'role'        => ROLE_ADMIN,
                'ban_message' => '',
                'banned_by'   => null,
                'created_at'  => date('Y-m-d H:i:s'),
            ]];
            file_put_contents(self::$jsonPath, json_encode($seed, JSON_PRETTY_PRINT));
            return $seed;
        }
        $users = json_decode(file_get_contents(self::$jsonPath), true) ?: [];
        // Migración de datos antiguos que pueden carecer de campos nuevos
        foreach ($users as &$u) {
            if (!isset($u['ban_message'])) $u['ban_message'] = '';
            if (!isset($u['banned_by']))   $u['banned_by'] = null;
            if (!isset($u['created_by']))  $u['created_by']  = null;
            if (!array_key_exists('center_id', $u)) $u['center_id'] = null;
            if (!isset($u['avatar']))      $u['avatar'] = '';
            if (!array_key_exists('last_ip', $u)) $u['last_ip'] = null;
            if (!isset($u['layout_pref'])) $u['layout_pref'] = 'list';
            if (!array_key_exists('reset_token', $u)) $u['reset_token'] = null;
            if (!array_key_exists('reset_expires', $u)) $u['reset_expires'] = null;
            if (!array_key_exists('pending_email', $u)) $u['pending_email'] = null;
            if (!array_key_exists('email_confirm_token', $u)) $u['email_confirm_token'] = null;
            if (!array_key_exists('email_confirm_expires', $u)) $u['email_confirm_expires'] = null;
        }
        return $users;
    }

    private static function saveUsers(array $users): void {
        if (!is_dir(__DIR__ . '/data')) mkdir(__DIR__ . '/data', 0755, true);
        file_put_contents(self::$jsonPath, json_encode(array_values($users), JSON_PRETTY_PRINT));
    }

    private static function loadCenters(): array {
        if (!is_dir(__DIR__ . '/data')) mkdir(__DIR__ . '/data', 0755, true);
        if (!file_exists(self::$centersJsonPath)) return [];
        return json_decode(file_get_contents(self::$centersJsonPath), true) ?: [];
    }

    private static function saveCenters(array $centers): void {
        if (!is_dir(__DIR__ . '/data')) mkdir(__DIR__ . '/data', 0755, true);
        file_put_contents(self::$centersJsonPath, json_encode(array_values($centers), JSON_PRETTY_PRINT));
    }

    /* IPs bloqueadas */
    private static string $bannedIpsJsonPath = __DIR__ . '/data/banned_ips.json';
    
    private static function loadBannedIps(): array {
        if (!is_dir(__DIR__ . '/data')) mkdir(__DIR__ . '/data', 0755, true);
        if (!file_exists(self::$bannedIpsJsonPath)) return [];
        return json_decode(file_get_contents(self::$bannedIpsJsonPath), true) ?: [];
    }

    private static function saveBannedIps(array $ips): void {
        if (!is_dir(__DIR__ . '/data')) mkdir(__DIR__ . '/data', 0755, true);
        file_put_contents(self::$bannedIpsJsonPath, json_encode(array_values($ips), JSON_PRETTY_PRINT));
    }

    /* ISO helpers */
    private static string $isosJsonPath = __DIR__ . '/data/isos.json';
    private static function loadIsos(): array {
        if (!is_dir(__DIR__ . '/data')) mkdir(__DIR__ . '/data', 0755, true);
        if (!file_exists(self::$isosJsonPath)) return [];
        return json_decode(file_get_contents(self::$isosJsonPath), true) ?: [];
    }

    private static function saveIsos(array $isos): void {
        if (!is_dir(__DIR__ . '/data')) mkdir(__DIR__ . '/data', 0755, true);
        file_put_contents(self::$isosJsonPath, json_encode(array_values($isos), JSON_PRETTY_PRINT));
    }

    /* API Pública */

    public static function findUserByUsername(string $username): ?array {
        if (TESTING_MODE) {
            foreach (self::loadUsers() as $u) {
                if (strtolower($u['username']) === strtolower($username)) return $u;
            }
            return null;
        }
        $stmt = self::getConnection()->prepare('SELECT * FROM users WHERE username = :u LIMIT 1');
        $stmt->execute(['u' => $username]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public static function findUserByLogin(string $login): ?array {
        if (TESTING_MODE) {
            foreach (self::loadUsers() as $u) {
                if ($u['username'] === $login || $u['email'] === $login) return $u;
            }
            return null;
        }
        $stmt = self::getConnection()->prepare('SELECT * FROM users WHERE username = :l OR email = :l LIMIT 1');
        $stmt->execute(['l' => $login]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public static function findUserById(int $id): ?array {
        if (TESTING_MODE) {
            foreach (self::loadUsers() as $u) {
                if ((int)$u['id'] === $id) return $u;
            }
            return null;
        }
        $stmt = self::getConnection()->prepare('SELECT * FROM users WHERE id = :id');
        $stmt->execute(['id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public static function usernameExists(string $username): bool {
        if (TESTING_MODE) {
            foreach (self::loadUsers() as $u) {
                if (strtolower($u['username']) === strtolower($username)) return true;
            }
            return false;
        }
        $stmt = self::getConnection()->prepare('SELECT 1 FROM users WHERE username = :u');
        $stmt->execute(['u' => $username]);
        return (bool)$stmt->fetch();
    }

    public static function emailExists(string $email): bool {
        if ($email === '') return false;
        if (TESTING_MODE) {
            foreach (self::loadUsers() as $u) {
                if (strtolower($u['email']) === strtolower($email)) return true;
            }
            return false;
        }
        $stmt = self::getConnection()->prepare('SELECT 1 FROM users WHERE email = :e');
        $stmt->execute(['e' => $email]);
        return (bool)$stmt->fetch();
    }

    public static function createUser(string $username, string $email, string $hashedPassword): array {
        if (TESTING_MODE) {
            $users = self::loadUsers();
            $maxId = 0;
            foreach ($users as $u) { if ($u['id'] > $maxId) $maxId = $u['id']; }
            $newUser = [
                'id'          => $maxId + 1,
                'username'    => $username,
                'email'       => $email,
                'password'    => $hashedPassword,
                'role'        => ROLE_USER,
                'ban_message' => '',
                'banned_by'   => null,
                'avatar'      => '',
                'last_ip'     => null,
                'layout_pref' => 'list',
                'reset_token' => null,
                'reset_expires'=> null,
                'pending_email'       => null,
                'email_confirm_token' => null,
                'email_confirm_expires'=> null,
                'created_at'  => date('Y-m-d H:i:s'),
            ];
            $users[] = $newUser;
            self::saveUsers($users);
            return $newUser;
        }
        $stmt = self::getConnection()->prepare(
            'INSERT INTO users (username, email, password, role, created_at) VALUES (:u, :e, :p, :r, NOW())'
        );
        $stmt->execute(['u' => $username, 'e' => $email, 'p' => $hashedPassword, 'r' => ROLE_USER]);
        return self::findUserById((int)self::getConnection()->lastInsertId());
    }

    /* Crear un usuario con un rol específico (importación CSV). */
    public static function createUserWithRole(string $username, string $email, string $hashedPassword, int $role, ?int $createdBy = null, ?int $centerId = null): array {
        if (TESTING_MODE) {
            $users = self::loadUsers();
            $maxId = 0;
            foreach ($users as $u) { if ($u['id'] > $maxId) $maxId = $u['id']; }
            $newUser = [
                'id'          => $maxId + 1,
                'username'    => $username,
                'email'       => $email,
                'password'    => $hashedPassword,
                'role'        => $role,
                'ban_message' => '',
                'banned_by'   => null,
                'created_by'  => $createdBy,
                'center_id'   => $centerId,
                'avatar'      => '',
                'last_ip'     => null,
                'layout_pref' => 'list',
                'reset_token' => null,
                'reset_expires'=> null,
                'pending_email'       => null,
                'email_confirm_token' => null,
                'email_confirm_expires'=> null,
                'created_at'  => date('Y-m-d H:i:s'),
            ];
            $users[] = $newUser;
            self::saveUsers($users);
            return $newUser;
        }
        $stmt = self::getConnection()->prepare(
            'INSERT INTO users (username, email, password, role, created_by, center_id, created_at) VALUES (:u, :e, :p, :r, :cb, :cid, NOW())'
        );
        $stmt->execute(['u' => $username, 'e' => $email, 'p' => $hashedPassword, 'r' => $role, 'cb' => $createdBy, 'cid' => $centerId]);
        return self::findUserById((int)self::getConnection()->lastInsertId());
    }

    /* Obtener todos los usuarios. Filtra opcionalmente por $limitedToCenterId, o $limitedToCreatedBy. Usa OR si ambos están presentes (aunque la lógica típica usa uno). */
    public static function getAllUsers(?int $limitedToCreatedBy = null, ?int $limitedToCenterId = null): array {
        if (TESTING_MODE) {
            $users = self::loadUsers();
            if ($limitedToCenterId !== null) {
                return array_values(array_filter($users, fn($u) => $u['center_id'] === $limitedToCenterId || $u['id'] === $limitedToCreatedBy));
            }
            if ($limitedToCreatedBy !== null) {
                return array_values(array_filter($users, fn($u) => $u['created_by'] === $limitedToCreatedBy || $u['id'] === $limitedToCreatedBy));
            }
            return $users;
        }
        
        if ($limitedToCenterId !== null) {
            $stmt = self::getConnection()->prepare('SELECT * FROM users WHERE center_id = :cid OR id = :cb ORDER BY id ASC');
            $stmt->execute(['cid' => $limitedToCenterId, 'cb' => $limitedToCreatedBy]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        if ($limitedToCreatedBy !== null) {
            $stmt = self::getConnection()->prepare('SELECT * FROM users WHERE created_by = :cb OR id = :cb ORDER BY id ASC');
            $stmt->execute(['cb' => $limitedToCreatedBy]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        
        $stmt = self::getConnection()->query('SELECT * FROM users ORDER BY id ASC');
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /* Actualizar el rol de un usuario. */
    public static function updateUserRole(int $id, int $role): bool {
        if (TESTING_MODE) {
            $users = self::loadUsers();
            foreach ($users as &$u) {
                if ((int)$u['id'] === $id) {
                    $u['role'] = $role;
                    // Limpiar información de baneo si no se está baneando
                    if ($role !== ROLE_BANNED) {
                        $u['ban_message'] = '';
                        $u['banned_by'] = null;
                    }
                    self::saveUsers($users);
                    return true;
                }
            }
            return false;
        }
        $stmt = self::getConnection()->prepare('UPDATE users SET role = :r WHERE id = :id');
        if ($role !== ROLE_BANNED) {
            $stmt2 = self::getConnection()->prepare('UPDATE users SET ban_message = "", banned_by = NULL WHERE id = :id');
            $stmt2->execute(['id' => $id]);
        }
        return $stmt->execute(['r' => $role, 'id' => $id]);
    }
    
    public static function updateUserCenter(int $id, ?int $centerId): bool {
        if (TESTING_MODE) {
            $users = self::loadUsers();
            foreach ($users as &$u) {
                if ((int)$u['id'] === $id) {
                    $u['center_id'] = $centerId;
                    self::saveUsers($users);
                    return true;
                }
            }
            return false;
        }
        $stmt = self::getConnection()->prepare('UPDATE users SET center_id = :cid WHERE id = :id');
        return $stmt->execute(['cid' => $centerId, 'id' => $id]);
    }

    /* Banear usuario con mensaje opcional */
    public static function banUser(int $id, string $message, int $bannedById): bool {
        if (TESTING_MODE) {
            $users = self::loadUsers();
            foreach ($users as &$u) {
                if ((int)$u['id'] === $id) {
                    $u['role'] = ROLE_BANNED;
                    $u['ban_message'] = $message;
                    $u['banned_by'] = $bannedById;
                    self::saveUsers($users);
                    return true;
                }
            }
            return false;
        }
        $stmt = self::getConnection()->prepare(
            'UPDATE users SET role = :r, ban_message = :m, banned_by = :b WHERE id = :id'
        );
        return $stmt->execute(['r' => ROLE_BANNED, 'm' => $message, 'b' => $bannedById, 'id' => $id]);
    }

    public static function searchUsers(string $query): array {
        $query = trim($query);
        if ($query === '') return self::getAllUsers();

        if (TESTING_MODE) {
            $results = [];
            foreach (self::loadUsers() as $u) {
                if ((string)$u['id'] === $query
                    || stripos($u['username'], $query) !== false
                    || stripos($u['email'], $query) !== false) {
                    $results[] = $u;
                }
            }
            return $results;
        }
        $stmt = self::getConnection()->prepare(
            "SELECT * FROM users WHERE id = :exact OR username LIKE :like OR email LIKE :like ORDER BY id ASC"
        );
        $stmt->execute(['exact' => $query, 'like' => "%$query%"]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function updateAvatar(int $id, string $filename): bool {
        if (TESTING_MODE) {
            $users = self::loadUsers();
            foreach ($users as &$u) {
                if ((int)$u['id'] === $id) {
                    $u['avatar'] = $filename;
                    self::saveUsers($users);
                    return true;
                }
            }
            return false;
        }
        $stmt = self::getConnection()->prepare('UPDATE users SET avatar = :a WHERE id = :id');
        return $stmt->execute(['a' => $filename, 'id' => $id]);
    }

    public static function updateProfile(int $id, string $email): bool {
        if (TESTING_MODE) {
            $users = self::loadUsers();
            foreach ($users as &$u) {
                if ((int)$u['id'] === $id) {
                    $u['email'] = $email;
                    self::saveUsers($users);
                    return true;
                }
            }
            return false;
        }
        $stmt = self::getConnection()->prepare('UPDATE users SET email = :e WHERE id = :id');
        return $stmt->execute(['e' => $email, 'id' => $id]);
    }

    public static function updateUsername(int $id, string $username): bool {
        if (TESTING_MODE) {
            $users = self::loadUsers();
            foreach ($users as &$u) {
                if ((int)$u['id'] === $id) {
                    $u['username'] = $username;
                    self::saveUsers($users);
                    return true;
                }
            }
            return false;
        }
        $stmt = self::getConnection()->prepare('UPDATE users SET username = :u WHERE id = :id');
        return $stmt->execute(['u' => $username, 'id' => $id]);
    }

    public static function updatePassword(int $id, string $hash): bool {
        if (TESTING_MODE) {
            $users = self::loadUsers();
            foreach ($users as &$u) {
                if ((int)$u['id'] === $id) {
                    $u['password'] = $hash;
                    self::saveUsers($users);
                    return true;
                }
            }
            return false;
        }
        $stmt = self::getConnection()->prepare('UPDATE users SET password = :p WHERE id = :id');
        return $stmt->execute(['p' => $hash, 'id' => $id]);
    }

    public static function setResetToken(int $id, ?string $token, ?string $expires): bool {
        if (TESTING_MODE) {
            $users = self::loadUsers();
            foreach ($users as &$u) {
                if ((int)$u['id'] === $id) {
                    $u['reset_token'] = $token;
                    $u['reset_expires'] = $expires;
                    self::saveUsers($users);
                    return true;
                }
            }
            return false;
        }
        $stmt = self::getConnection()->prepare('UPDATE users SET reset_token = :t, reset_expires = :e WHERE id = :id');
        return $stmt->execute(['t' => $token, 'e' => $expires, 'id' => $id]);
    }

    public static function getUserByResetToken(string $token): ?array {
        if (TESTING_MODE) {
            foreach (self::loadUsers() as $u) {
                if (($u['reset_token'] ?? null) === $token) return $u;
            }
            return null;
        }
        $stmt = self::getConnection()->prepare('SELECT * FROM users WHERE reset_token = :t LIMIT 1');
        $stmt->execute(['t' => $token]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /* Establecer cambio de correo pendiente (pendiente de confirmación) */
    public static function setPendingEmail(int $id, string $pendingEmail, string $token, string $expires): bool {
        if (TESTING_MODE) {
            $users = self::loadUsers();
            foreach ($users as &$u) {
                if ((int)$u['id'] === $id) {
                    $u['pending_email']        = $pendingEmail;
                    $u['email_confirm_token']  = $token;
                    $u['email_confirm_expires']= $expires;
                    self::saveUsers($users);
                    return true;
                }
            }
            return false;
        }
        $stmt = self::getConnection()->prepare(
            'UPDATE users SET pending_email=:pe, email_confirm_token=:t, email_confirm_expires=:e WHERE id=:id'
        );
        return $stmt->execute(['pe'=>$pendingEmail,'t'=>$token,'e'=>$expires,'id'=>$id]);
    }

    /* Buscar usuario por token de confirmación de correo */
    public static function getUserByEmailConfirmToken(string $token): ?array {
        if (TESTING_MODE) {
            foreach (self::loadUsers() as $u) {
                if (($u['email_confirm_token'] ?? null) === $token) return $u;
            }
            return null;
        }
        $stmt = self::getConnection()->prepare('SELECT * FROM users WHERE email_confirm_token=:t LIMIT 1');
        $stmt->execute(['t'=>$token]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /* Aplicar cambio de correo confirmado y limpiar campos pendientes */
    public static function confirmEmailChange(int $id, string $newEmail): bool {
        if (TESTING_MODE) {
            $users = self::loadUsers();
            foreach ($users as &$u) {
                if ((int)$u['id'] === $id) {
                    $u['email']               = $newEmail;
                    $u['pending_email']        = null;
                    $u['email_confirm_token']  = null;
                    $u['email_confirm_expires']= null;
                    self::saveUsers($users);
                    return true;
                }
            }
            return false;
        }
        $stmt = self::getConnection()->prepare(
            'UPDATE users SET email=:e, pending_email=NULL, email_confirm_token=NULL, email_confirm_expires=NULL WHERE id=:id'
        );
        return $stmt->execute(['e'=>$newEmail,'id'=>$id]);
    }

    /* Descartar cambio de correo pendiente */
    public static function clearPendingEmail(int $id): bool {
        if (TESTING_MODE) {
            $users = self::loadUsers();
            foreach ($users as &$u) {
                if ((int)$u['id'] === $id) {
                    $u['pending_email']        = null;
                    $u['email_confirm_token']  = null;
                    $u['email_confirm_expires']= null;
                    self::saveUsers($users);
                    return true;
                }
            }
            return false;
        }
        $stmt = self::getConnection()->prepare(
            'UPDATE users SET pending_email=NULL, email_confirm_token=NULL, email_confirm_expires=NULL WHERE id=:id'
        );
        return $stmt->execute(['id'=>$id]);
    }
    public static function updateLayoutPref(int $id, string $pref): bool {
        if (TESTING_MODE) {
            $users = self::loadUsers();
            foreach ($users as &$u) {
                if ((int)$u['id'] === $id) {
                    $u['layout_pref'] = $pref;
                    self::saveUsers($users);
                    return true;
                }
            }
            return false;
        }
        $stmt = self::getConnection()->prepare('UPDATE users SET layout_pref = :p WHERE id = :id');
        return $stmt->execute(['p' => $pref, 'id' => $id]);
    }

    /* Gestión IP */
    
    public static function updateLastIp(int $id, string $ip): bool {
        if (TESTING_MODE) {
            $users = self::loadUsers();
            foreach ($users as &$u) {
                if ((int)$u['id'] === $id) {
                    $u['last_ip'] = $ip;
                    self::saveUsers($users);
                    return true;
                }
            }
            return false;
        }
        $stmt = self::getConnection()->prepare('UPDATE users SET last_ip = :ip WHERE id = :id');
        return $stmt->execute(['ip' => $ip, 'id' => $id]);
    }

    public static function banIp(string $ip, int $bannedById): bool {
        if (TESTING_MODE) {
            $ips = self::loadBannedIps();
            foreach ($ips as $b) { if (isset($b['ip']) && $b['ip'] === $ip) return true; }
            $ips[] = ['ip' => $ip, 'banned_by' => $bannedById, 'created_at' => date('Y-m-d H:i:s')];
            self::saveBannedIps($ips);
            return true;
        }
        $stmt = self::getConnection()->prepare('INSERT IGNORE INTO banned_ips (ip, banned_by) VALUES (:ip, :by)');
        return $stmt->execute(['ip' => $ip, 'by' => $bannedById]);
    }

    public static function unbanIp(string $ip): bool {
        if (TESTING_MODE) {
            $ips = self::loadBannedIps();
            $filtered = array_values(array_filter($ips, fn($b) => isset($b['ip']) && $b['ip'] !== $ip));
            self::saveBannedIps($filtered);
            return true;
        }
        $stmt = self::getConnection()->prepare('DELETE FROM banned_ips WHERE ip = :ip');
        return $stmt->execute(['ip' => $ip]);
    }

    public static function isIpBanned(string $ip): bool {
        if (TESTING_MODE) {
            foreach (self::loadBannedIps() as $b) { if (isset($b['ip']) && $b['ip'] === $ip) return true; }
            return false;
        }
        $stmt = self::getConnection()->prepare('SELECT 1 FROM banned_ips WHERE ip = :ip');
        $stmt->execute(['ip' => $ip]);
        return (bool)$stmt->fetch();
    }

    /* Gestión de Centros */
    public static function getCenters(): array {
        if (TESTING_MODE) {
            $centers = self::loadCenters();
            // Migrar: asegurar campo 'type' existe
            foreach ($centers as &$c) {
                if (!isset($c['type'])) $c['type'] = 'organization';
            }
            return $centers;
        }
        $stmt = self::getConnection()->query('SELECT * FROM centers ORDER BY name ASC');
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function getCenterById(int $id): ?array {
        if (TESTING_MODE) {
            foreach (self::loadCenters() as $c) {
                if ((int)$c['id'] === $id) {
                    if (!isset($c['type'])) $c['type'] = 'organization';
                    return $c;
                }
            }
            return null;
        }
        $stmt = self::getConnection()->prepare('SELECT * FROM centers WHERE id = :id');
        $stmt->execute(['id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public static function createCenter(string $name, string $type = 'organization'): array {
        if (TESTING_MODE) {
            $centers = self::loadCenters();
            $maxId = 0;
            foreach ($centers as $c) { if ($c['id'] > $maxId) $maxId = $c['id']; }
            $newCenter = [
                'id' => $maxId + 1,
                'name' => trim($name),
                'type' => $type,
                'created_at' => date('Y-m-d H:i:s')
            ];
            $centers[] = $newCenter;
            self::saveCenters($centers);
            return $newCenter;
        }
        $stmt = self::getConnection()->prepare('INSERT INTO centers (name, type) VALUES (:n, :t)');
        $stmt->execute(['n' => trim($name), 't' => $type]);
        $id = (int)self::getConnection()->lastInsertId();
        $stmt = self::getConnection()->prepare('SELECT * FROM centers WHERE id = :id');
        $stmt->execute(['id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public static function updateCenter(int $id, array $data): bool {
        if (TESTING_MODE) {
            $centers = self::loadCenters();
            foreach ($centers as &$c) {
                if ((int)$c['id'] === $id) {
                    foreach ($data as $k => $v) $c[$k] = $v;
                    self::saveCenters($centers);
                    return true;
                }
            }
            return false;
        }
        $sets = []; $params = ['id' => $id];
        foreach ($data as $k => $v) { $sets[] = "$k = :$k"; $params[$k] = $v; }
        if (empty($sets)) return false;
        $stmt = self::getConnection()->prepare('UPDATE centers SET ' . implode(', ', $sets) . ' WHERE id = :id');
        return $stmt->execute($params);
    }

    public static function deleteCenter(int $id): bool {
        if (TESTING_MODE) {
            $centers = self::loadCenters();
            foreach ($centers as $k => $c) {
                if ((int)$c['id'] === $id) {
                    unset($centers[$k]);
                    self::saveCenters($centers);
                    // Limpiar center_id de usuarios
                    $users = self::loadUsers();
                    foreach ($users as &$u) {
                        if ($u['center_id'] === $id) $u['center_id'] = null;
                    }
                    self::saveUsers($users);
                    $members = self::loadGroupMembers();
                    unset($members[$id]);
                    self::saveGroupMembers($members);
                    return true;
                }
            }
            return false;
        }
        self::getConnection()->prepare('UPDATE users SET center_id = NULL WHERE center_id = :id')->execute(['id' => $id]);
        $stmt = self::getConnection()->prepare('DELETE FROM centers WHERE id = :id');
        return $stmt->execute(['id' => $id]);
    }

    /* Gestión Grupos */
    private static string $groupMembersJsonPath = __DIR__ . '/data/group_members.json';

    private static function loadGroupMembers(): array {
        if (!is_dir(__DIR__ . '/data')) mkdir(__DIR__ . '/data', 0755, true);
        if (!file_exists(self::$groupMembersJsonPath)) return [];
        return json_decode(file_get_contents(self::$groupMembersJsonPath), true) ?: [];
    }

    private static function saveGroupMembers(array $data): void {
        if (!is_dir(__DIR__ . '/data')) mkdir(__DIR__ . '/data', 0755, true);
        file_put_contents(self::$groupMembersJsonPath, json_encode($data, JSON_PRETTY_PRINT));
    }

    public static function getGroupMemberIds(int $groupId): array {
        if (TESTING_MODE) {
            $all = self::loadGroupMembers();
            return $all[$groupId] ?? [];
        }
        $stmt = self::getConnection()->prepare('SELECT user_id FROM group_members WHERE group_id = :gid');
        $stmt->execute(['gid' => $groupId]);
        return array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'user_id');
    }

    public static function getGroupMembers(int $groupId): array {
        $ids = self::getGroupMemberIds($groupId);
        if (empty($ids)) return [];
        if (TESTING_MODE) {
            $users = self::loadUsers();
            return array_values(array_filter($users, fn($u) => in_array((int)$u['id'], $ids)));
        }
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = self::getConnection()->prepare("SELECT * FROM users WHERE id IN ($placeholders)");
        $stmt->execute($ids);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function addGroupMember(int $groupId, int $userId): bool {
        if (TESTING_MODE) {
            $all = self::loadGroupMembers();
            if (!isset($all[$groupId])) $all[$groupId] = [];
            if (!in_array($userId, $all[$groupId])) {
                $all[$groupId][] = $userId;
                self::saveGroupMembers($all);
            }
            return true;
        }
        $stmt = self::getConnection()->prepare('INSERT IGNORE INTO group_members (group_id, user_id) VALUES (:gid, :uid)');
        return $stmt->execute(['gid' => $groupId, 'uid' => $userId]);
    }

    public static function removeGroupMember(int $groupId, int $userId): bool {
        if (TESTING_MODE) {
            $all = self::loadGroupMembers();
            if (isset($all[$groupId])) {
                $all[$groupId] = array_values(array_filter($all[$groupId], fn($id) => $id !== $userId));
                if (empty($all[$groupId])) unset($all[$groupId]);
                self::saveGroupMembers($all);
            }
            return true;
        }
        $stmt = self::getConnection()->prepare('DELETE FROM group_members WHERE group_id = :gid AND user_id = :uid');
        return $stmt->execute(['gid' => $groupId, 'uid' => $userId]);
    }

    public static function getUserGroups(int $userId): array {
        if (TESTING_MODE) {
            $all = self::loadGroupMembers();
            $groupIds = [];
            foreach ($all as $gid => $uids) {
                if (in_array($userId, $uids)) $groupIds[] = (int)$gid;
            }
            if (empty($groupIds)) return [];
            $centers = self::loadCenters();
            return array_values(array_filter($centers, fn($c) => in_array((int)$c['id'], $groupIds)));
        }
        $stmt = self::getConnection()->prepare(
            'SELECT c.* FROM centers c JOIN group_members gm ON c.id = gm.group_id WHERE gm.user_id = :uid ORDER BY c.name'
        );
        $stmt->execute(['uid' => $userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function getGroupMemberCounts(): array {
        if (TESTING_MODE) {
            $all = self::loadGroupMembers();
            $counts = [];
            foreach ($all as $gid => $uids) $counts[$gid] = count($uids);
            return $counts;
        }
        $stmt = self::getConnection()->query('SELECT group_id, COUNT(*) as cnt FROM group_members GROUP BY group_id');
        $result = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) $result[$row['group_id']] = (int)$row['cnt'];
        return $result;
    }

    /* Gestión ISOs */
    public static function createIso(string $name, string $osType, string $visibility, string $filename, int $size, int $uploadedBy, ?int $centerId): array {
        if (TESTING_MODE) {
            $isos = self::loadIsos();
            $maxId = 0;
            foreach ($isos as $iso) { if ($iso['id'] > $maxId) $maxId = $iso['id']; }
            $newIso = [
                'id'          => $maxId + 1,
                'name'        => trim($name),
                'os_type'     => trim($osType),
                'visibility'  => $visibility,
                'filename'    => $filename,
                'size'        => $size,
                'uploaded_by' => $uploadedBy,
                'center_id'   => $centerId,
                'created_at'  => date('Y-m-d H:i:s')
            ];
            $isos[] = $newIso;
            self::saveIsos($isos);
            return $newIso;
        }

        $stmt = self::getConnection()->prepare(
            'INSERT INTO isos (name, os_type, visibility, filename, size, uploaded_by, center_id)
             VALUES (:n, :o, :v, :f, :s, :u, :c)'
        );
        $stmt->execute([
            'n' => trim($name), 'o' => trim($osType), 'v' => $visibility,
            'f' => $filename, 's' => $size, 'u' => $uploadedBy, 'c' => $centerId
        ]);
        
        return self::getIsoById((int)self::getConnection()->lastInsertId());
    }

    public static function getIsoById(int $id): ?array {
        if (TESTING_MODE) {
            foreach (self::loadIsos() as $iso) {
                if ($iso['id'] === $id) return $iso;
            }
            return null;
        }
        $stmt = self::getConnection()->prepare('SELECT * FROM isos WHERE id = :id');
        $stmt->execute(['id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public static function getIsos(int $userId, int $userRole, ?int $centerId): array {
        if (TESTING_MODE) {
            $isos = self::loadIsos();
            if ($userRole === ROLE_ADMIN) {
                return $isos;
            }
            $result = [];
            foreach ($isos as $iso) {
                if ($iso['visibility'] === 'public') {
                    $result[] = $iso;
                } elseif ($iso['visibility'] === 'center' && $iso['center_id'] === $centerId) {
                    $result[] = $iso;
                } elseif ($iso['uploaded_by'] === $userId) {
                    $result[] = $iso;
                }
            }
            return $result;
        }

        if ($userRole === ROLE_ADMIN) {
            $stmt = self::getConnection()->query('SELECT * FROM isos ORDER BY created_at DESC');
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        $stmt = self::getConnection()->prepare(
            'SELECT * FROM isos 
             WHERE visibility = "public" 
                OR (visibility = "center" AND center_id = :cid)
                OR uploaded_by = :uid
             ORDER BY created_at DESC'
        );
        $stmt->execute(['cid' => $centerId, 'uid' => $userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function deleteIso(int $id): bool {
        if (TESTING_MODE) {
            $isos = self::loadIsos();
            foreach ($isos as $k => $iso) {
                if ($iso['id'] === $id) {
                    unset($isos[$k]);
                    self::saveIsos($isos);
                    return true;
                }
            }
            return false;
        }
        $stmt = self::getConnection()->prepare('DELETE FROM isos WHERE id = :id');
        return $stmt->execute(['id' => $id]);
    }

    public static function updateIso(int $id, array $data): bool {
        if (TESTING_MODE) {
            $isos = self::loadIsos();
            foreach ($isos as &$iso) {
                if ($iso['id'] === $id) {
                    foreach ($data as $k => $v) {
                        $iso[$k] = $v;
                    }
                    self::saveIsos($isos);
                    return true;
                }
            }
            return false;
        }
        $sets = [];
        $params = ['id' => $id];
        foreach ($data as $k => $v) {
            $sets[] = "$k = :$k";
            $params[$k] = $v;
        }
        if (empty($sets)) return false;
        $stmt = self::getConnection()->prepare('UPDATE isos SET ' . implode(', ', $sets) . ' WHERE id = :id');
        return $stmt->execute($params);
    }

    /* Gestión VMs */
    private static string $vmsJsonPath = __DIR__ . '/data/vms.json';

    private static function loadVms(): array {
        if (!is_dir(__DIR__ . '/data')) mkdir(__DIR__ . '/data', 0755, true);
        if (!file_exists(self::$vmsJsonPath)) return [];
        $vms = json_decode(file_get_contents(self::$vmsJsonPath), true) ?: [];
        foreach ($vms as &$v) {
            if (!isset($v['last_active']))    $v['last_active']    = null;
            if (!isset($v['proxmox_vmid']))   $v['proxmox_vmid']   = null;
            if (!isset($v['proxmox_node']))   $v['proxmox_node']   = null;
            if (!isset($v['is_pve_template'])) $v['is_pve_template'] = false;
        }
        return $vms;
    }

    private static function saveVms(array $vms): void {
        if (!is_dir(__DIR__ . '/data')) mkdir(__DIR__ . '/data', 0755, true);
        file_put_contents(self::$vmsJsonPath, json_encode(array_values($vms), JSON_PRETTY_PRINT));
    }

    public static function createVm(array $data): array {
        if (TESTING_MODE) {
            $vms = self::loadVms();
            $maxId = 0;
            foreach ($vms as $vm) { if ($vm['id'] > $maxId) $maxId = $vm['id']; }
            $data['id'] = $maxId + 1;
            $data['status'] = 'stopped';
            $data['created_at'] = date('Y-m-d H:i:s');
            $vms[] = $data;
            self::saveVms($vms);
            return $data;
        }
        $pdo = self::getConnection();
        $stmt = $pdo->prepare('
            INSERT INTO vms
                (name, iso_id, iso_name, os_type, ram_mb, cpu_cores, disk_gb,
                 assign_scope, assign_center_id, assigned_students,
                 created_by, node_key, proxmox_vmid, proxmox_node,
                 template_id, is_pve_template, assigned_to, status)
            VALUES
                (:name, :iso_id, :iso_name, :os_type, :ram_mb, :cpu_cores, :disk_gb,
                 :assign_scope, :assign_center_id, :assigned_students,
                 :created_by, :node_key, :proxmox_vmid, :proxmox_node,
                 :template_id, :is_pve_template, :assigned_to, :status)
        ');
        $stmt->execute([
            'name'             => $data['name'] ?? '',
            'iso_id'           => $data['iso_id'] ?? 0,
            'iso_name'         => $data['iso_name'] ?? '',
            'os_type'          => $data['os_type'] ?? '',
            'ram_mb'           => $data['ram_mb'] ?? 2048,
            'cpu_cores'        => $data['cpu_cores'] ?? 2,
            'disk_gb'          => $data['disk_gb'] ?? 20,
            'assign_scope'     => $data['assign_scope'] ?? 'personal',
            'assign_center_id' => $data['assign_center_id'] ?? null,
            'assigned_students'=> json_encode($data['assigned_students'] ?? []),
            'created_by'       => $data['created_by'],
            'node_key'         => $data['node_key'] ?? null,
            'proxmox_vmid'     => $data['proxmox_vmid'] ?? null,
            'proxmox_node'     => $data['proxmox_node'] ?? null,
            'template_id'      => $data['template_id'] ?? null,
            'is_pve_template'  => !empty($data['is_pve_template']) ? 1 : 0,
            'assigned_to'      => $data['assigned_to'] ?? null,
            'status'           => $data['status'] ?? 'stopped',
        ]);
        $id = (int)$pdo->lastInsertId();
        return self::getVmById($id) ?? [];
    }

    public static function getVms(int $userId, int $userRole, ?int $centerId): array {
    if (TESTING_MODE) {
        $vms = self::loadVms();
        if ($userRole >= ROLE_ADMIN) {
            return $vms;
        }
        $result = [];
        foreach ($vms as $vm) {
            $isClone = !empty($vm['template_id']);
            $isMine = ($vm['created_by'] === $userId);
            $assignedToMe = !empty($vm['assigned_to']) && (int)$vm['assigned_to'] === $userId;
            $inAssignedList = in_array($userId, array_map('intval', $vm['assigned_students'] ?? []));

            if ($isMine || $assignedToMe) {
                $result[] = $vm;
                continue;
            }
            if ($inAssignedList) {
                $result[] = $vm;
                continue;
            }
            if ($isClone) continue;
            if ($vm['assign_scope'] === 'center' && $vm['assign_center_id'] === $centerId) {
                if ($userRole >= ROLE_TEACHER) {
                    $result[] = $vm;
                }
                continue;
            }
        }
        return $result;
    }

    $pdo = self::getConnection();
    if ($userRole >= ROLE_ADMIN) {
        $stmt = $pdo->query('SELECT v.* FROM vms v ORDER BY v.id DESC');
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    $stmt = $pdo->prepare("
        SELECT v.* FROM vms v
        WHERE v.created_by = :uid
           OR v.assigned_to = :uid
           OR JSON_CONTAINS(v.assigned_students, :uid_json)
        ORDER BY v.id DESC
    ");
    $stmt->execute(['uid' => $userId, 'uid_json' => json_encode($userId)]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

    public static function getVmClones(int $templateId): array {
        if (TESTING_MODE) {
            $result = [];
            foreach (self::loadVms() as $vm) {
                if ((int)($vm['template_id'] ?? 0) === $templateId) $result[] = $vm;
            }
            return $result;
        }
        $stmt = self::getConnection()->prepare('SELECT * FROM vms WHERE template_id = :tid ORDER BY id ASC');
        $stmt->execute(['tid' => $templateId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function cloneVmForUser(int $templateId, int $userId, int $createdBy): array {
        $tpl  = self::getVmById($templateId);
        if (!$tpl) return [];
        $user = self::findUserById($userId);
        return self::createVm([
            'name'             => $tpl['name'] . ' — ' . ($user['username'] ?? 'User '.$userId),
            'iso_id'           => $tpl['iso_id'],   'iso_name'    => $tpl['iso_name'],
            'os_type'          => $tpl['os_type'],  'ram_mb'      => $tpl['ram_mb'],
            'cpu_cores'        => $tpl['cpu_cores'],'disk_gb'     => $tpl['disk_gb'],
            'assign_scope'     => 'personal',       'assign_center_id' => null,
            'assigned_students'=> [],               'created_by'  => $createdBy,
            'node_key'         => null,             'template_id' => $templateId,
            'assigned_to'      => $userId,          'proxmox_vmid'=> null,
            'proxmox_node'     => null,
        ]);
    }

    public static function deleteVmCloneForUser(int $templateId, int $userId): bool {
        if (TESTING_MODE) {
            $vms = self::loadVms(); $kept = []; $del = false;
            foreach ($vms as $vm) {
                if ((int)($vm['template_id'] ?? 0) === $templateId && (int)($vm['assigned_to'] ?? 0) === $userId) $del = true;
                else $kept[] = $vm;
            }
            if ($del) self::saveVms($kept);
            return $del;
        }
        $stmt = self::getConnection()->prepare('DELETE FROM vms WHERE template_id=:tid AND assigned_to=:uid');
        $stmt->execute(['tid'=>$templateId,'uid'=>$userId]);
        return $stmt->rowCount() > 0;
    }

    public static function deleteVm(int $id): bool {
        if (TESTING_MODE) {
            $vms = self::loadVms();
            foreach ($vms as $k => $vm) {
                if ($vm['id'] === $id) {
                    unset($vms[$k]);
                    self::saveVms($vms);
                    return true;
                }
            }
            return false;
        }
        $stmt = self::getConnection()->prepare('DELETE FROM vms WHERE id = :id');
        $stmt->execute(['id' => $id]);
        return $stmt->rowCount() > 0;
    }

    public static function getVmById(int $id): ?array {
        if (TESTING_MODE) {
            foreach (self::loadVms() as $vm) {
                if ((int)$vm['id'] === $id) return $vm;
            }
            return null;
        }
        $stmt = self::getConnection()->prepare('SELECT * FROM vms WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public static function updateVmStatus(int $id, string $status): bool {
        $now = date('Y-m-d H:i:s');
        if (TESTING_MODE) {
            $vms = self::loadVms();
            foreach ($vms as &$vm) {
                if ((int)$vm['id'] === $id) {
                    $vm['status']      = $status;
                    $vm['last_active'] = $now;
                    self::saveVms($vms);
                    return true;
                }
            }
            return false;
        }
        $stmt = self::getConnection()->prepare('UPDATE vms SET status = :s, last_active = :la WHERE id = :id');
        $stmt->execute(['s' => $status, 'la' => $now, 'id' => $id]);
        return $stmt->rowCount() > 0;
        $stmt = self::getConnection()->prepare('UPDATE vms SET status=:s, last_active=:la WHERE id=:id');
        return $stmt->execute(['s' => $status, 'la' => $now, 'id' => $id]);
    }

    public static function updateVmField(int $id, string $field, $value): bool {
        $allowed = ['proxmox_vmid','proxmox_node','status','last_active','node_key','assign_scope',
                    'template_id','assigned_to','name','is_pve_template'];
        if (!in_array($field, $allowed)) return false;
        if (TESTING_MODE) {
            $vms = self::loadVms();
            foreach ($vms as &$vm) {
                if ((int)$vm['id'] === $id) {
                    $vm[$field] = $value;
                    self::saveVms($vms);
                    return true;
                }
            }
            return false;
        }
        $stmt = self::getConnection()->prepare("UPDATE vms SET `{$field}` = :v WHERE id = :id");
        return $stmt->execute(['v' => $value, 'id' => $id]);
    }

    public static function touchVmActive(int $id): bool {
        $now = date('Y-m-d H:i:s');
        if (TESTING_MODE) {
            $vms = self::loadVms();
            foreach ($vms as &$vm) {
                if ((int)$vm['id'] === $id) {
                    $vm['last_active'] = $now;
                    self::saveVms($vms);
                    return true;
                }
            }
            return false;
        }
        $stmt = self::getConnection()->prepare('UPDATE vms SET last_active=:la WHERE id=:id');
        return $stmt->execute(['la' => $now, 'id' => $id]);
    }
    //Parar VM inactiva
    public static function stopInactiveVms(int $minutes = 60): int {
        $cutoff = date('Y-m-d H:i:s', time() - $minutes * 60);
        $stopped = 0;
        if (TESTING_MODE) {
            $vms = self::loadVms();
            foreach ($vms as &$vm) {
                if ($vm['status'] === 'running' && !empty($vm['last_active']) && $vm['last_active'] < $cutoff) {
                    $vm['status'] = 'stopped';
                    $stopped++;
                }
            }
            if ($stopped > 0) self::saveVms($vms);
            return $stopped;
        }
        $stmt = self::getConnection()->prepare(
            'UPDATE vms SET status="stopped" WHERE status="running" AND last_active < :cutoff'
        );
        $stmt->execute(['cutoff' => $cutoff]);
        return (int)$stmt->rowCount();
    }

    public static function updateVmAssignedUsers(int $vmId, array $userIds): bool {
        if (TESTING_MODE) {
            $vms = self::loadVms();
            foreach ($vms as &$vm) {
                if ((int)$vm['id'] === $vmId) {
                    $vm['assigned_students'] = array_values(array_unique(array_map('intval', $userIds)));
                    self::saveVms($vms);
                    return true;
                }
            }
            return false;
        }
        $stmt = self::getConnection()->prepare('UPDATE vms SET assigned_students = :u WHERE id = :id');
        return $stmt->execute(['u' => json_encode(array_values(array_unique(array_map('intval', $userIds)))), 'id' => $vmId]);
    }
}
