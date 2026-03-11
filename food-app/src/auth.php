<?php
require_once __DIR__ . '/../config.php';

function session_init(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        ini_set('session.cookie_httponly', '1');
        ini_set('session.use_strict_mode', '1');
        session_name('menu_sess');
        session_start();
    }
}

function require_auth(): void
{
    session_init();
    if (empty($_SESSION['user_id'])) {
        header('Location: index.php');
        exit;
    }
}

function try_login(string $username, string $password): bool
{
    require_once __DIR__ . '/db.php';
    $s = db()->prepare('SELECT id, password_hash FROM users WHERE username = ?');
    $s->execute([trim($username)]);
    $user = $s->fetch();
    if ($user && password_verify($password, $user['password_hash'])) {
        session_init();
        session_regenerate_id(true);
        $_SESSION['user_id']  = $user['id'];
        $_SESSION['username'] = $username;
        return true;
    }
    return false;
}

function do_logout(): void
{
    session_init();
    session_destroy();
    header('Location: index.php');
    exit;
}

function current_user(): ?string
{
    session_init();
    return $_SESSION['username'] ?? null;
}
