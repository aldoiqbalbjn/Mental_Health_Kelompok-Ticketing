<?php
/**
 * Proses login/logout. Sertakan file ini sebelum output HTML.
 */

declare(strict_types=1);

require_once __DIR__ . '/functions.php';

function attempt_login(string $username, string $password): bool
{
    $stmt = db()->prepare('SELECT id, username, password, role FROM users WHERE username = ? LIMIT 1');
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password'])) {
        return false;
    }

    session_regenerate_id(true);
    $_SESSION['user'] = [
        'id'       => (int) $user['id'],
        'username' => $user['username'],
        'role'     => $user['role'],
    ];

    return true;
}

function logout(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
}
