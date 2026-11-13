<?php
declare(strict_types=1);

const AUTH_COOKIE_NAME = 'plantia_auth';
const AUTH_TOKEN_TTL_HOURS = 8;

/** Genera las opciones seguras de la cookie de autenticación. */
function authCookieOptions(int $expires = 0): array
{
    $https = (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off')
        || strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';

    return [
        'expires' => $expires,
        'path' => '/',
        'secure' => $https,
        'httponly' => true,
        'samesite' => 'Lax',
    ];
}

function clearAuthCookie(): void
{
    setcookie(AUTH_COOKIE_NAME, '', authCookieOptions(time() - 3600));
    unset($_COOKIE[AUTH_COOKIE_NAME]);
}

/** Emite una sesión de duración limitada y almacena únicamente su hash. */
function issueAuthToken(PDO $db, int $userId): void
{
    $existingToken = $_COOKIE[AUTH_COOKIE_NAME] ?? '';
    if (is_string($existingToken) && preg_match('/^[a-f0-9]{64}$/i', $existingToken)) {
        $removeCurrent = $db->prepare('DELETE FROM auth_sessions WHERE token_hash = ?');
        $removeCurrent->execute([hash('sha256', $existingToken)]);
    }

    $token = bin2hex(random_bytes(32));
    $insert = $db->prepare('INSERT INTO auth_sessions(token_hash,user_id,expires_at) VALUES(?,?,DATE_ADD(CURRENT_TIMESTAMP, INTERVAL ' . AUTH_TOKEN_TTL_HOURS . ' HOUR))');
    $insert->execute([hash('sha256', $token), $userId]);

    // Session cookie: the browser drops it when its session ends; the database token also expires after eight hours.
    setcookie(AUTH_COOKIE_NAME, $token, authCookieOptions());
}

/** Devuelve la persona autenticada o null después de limpiar sesiones vencidas. */
function currentUser(PDO $db): ?array
{
    $db->exec('DELETE FROM auth_sessions WHERE expires_at <= CURRENT_TIMESTAMP');
    $token = $_COOKIE[AUTH_COOKIE_NAME] ?? '';
    if (!is_string($token) || !preg_match('/^[a-f0-9]{64}$/i', $token)) {
        return null;
    }

    $query = $db->prepare("SELECT u.id,TRIM(CONCAT_WS(' ',NULLIF(u.first_name,''),NULLIF(u.paternal_last_name,''),NULLIF(u.maternal_last_name,''))) AS name,u.email FROM auth_sessions s INNER JOIN users u ON u.id=s.user_id WHERE s.token_hash=? AND s.expires_at>CURRENT_TIMESTAMP LIMIT 1");
    $query->execute([hash('sha256', $token)]);
    $user = $query->fetch(PDO::FETCH_ASSOC) ?: null;
    if (!$user) {
        clearAuthCookie();
    }

    return $user;
}

/** Valida los datos de registro, crea la cuenta y abre su sesión. */
function registerUser(PDO $db, array $data): void
{
    $name = trim($data['name'] ?? '');
    $paternalLastName = trim($data['paternal_last_name'] ?? '');
    $maternalLastName = trim($data['maternal_last_name'] ?? '');
    $phone = trim($data['phone'] ?? '');
    $email = strtolower(trim($data['email'] ?? ''));
    $password = $data['password'] ?? '';
    $phoneDigits = preg_replace('/\D/', '', $phone) ?? '';
    $nameLength = static function (string $value): int {
        $length = preg_match_all('/./us', $value);
        return $length === false ? PHP_INT_MAX : $length;
    };

    if ($name === '' || $paternalLastName === '' || $maternalLastName === ''
        || $nameLength($name) > 100 || $nameLength($paternalLastName) > 100 || $nameLength($maternalLastName) > 100
        || !preg_match('/^[0-9+()\s-]{8,30}$/D', $phone) || strlen($phoneDigits) < 8
        || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 6) {
        respond(['error' => 'Completa tu nombre, ambos apellidos, un teléfono válido, un correo válido y una contraseña de al menos 6 caracteres.'], 422);
    }

    $query = $db->prepare('INSERT INTO users(first_name,paternal_last_name,maternal_last_name,email,phone,password,created_at,updated_at) VALUES(?,?,?,?,?,?,CURRENT_TIMESTAMP,CURRENT_TIMESTAMP)');
    $query->execute([$name,$paternalLastName,$maternalLastName,$email,$phone,password_hash($password,PASSWORD_DEFAULT)]);
    issueAuthToken($db, (int)$db->lastInsertId());
    respond(['ok' => true]);
}

/** Comprueba credenciales y reemplaza la sesión anterior por una nueva. */
function loginUser(PDO $db, array $data): void
{
    $query = $db->prepare('SELECT id,password FROM users WHERE email=? LIMIT 1');
    $query->execute([strtolower(trim($data['email'] ?? ''))]);
    $user = $query->fetch(PDO::FETCH_ASSOC);

    if (!$user || !password_verify($data['password'] ?? '', $user['password'])) {
        respond(['error' => 'Correo o contraseña incorrectos.'], 401);
    }

    issueAuthToken($db, (int)$user['id']);
    respond(['ok' => true]);
}

function logoutUser(PDO $db): void
{
    $token = $_COOKIE[AUTH_COOKIE_NAME] ?? '';
    if (is_string($token) && preg_match('/^[a-f0-9]{64}$/i', $token)) {
        $delete = $db->prepare('DELETE FROM auth_sessions WHERE token_hash=?');
        $delete->execute([hash('sha256', $token)]);
    }

    clearAuthCookie();
    respond(['ok' => true]);
}
