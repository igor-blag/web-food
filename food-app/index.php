<?php
require_once __DIR__ . '/src/auth.php';
require_once __DIR__ . '/src/db.php';

// Если пользователей ещё нет — первоначальная настройка
$count = (int) db()->query('SELECT COUNT(*) FROM users')->fetchColumn();
if ($count === 0) {
    header('Location: setup.php');
    exit;
}

// Уже вошёл — на главную
session_init();
if (!empty($_SESSION['user_id'])) {
    header('Location: calendar.php');
    exit;
}

$error = '';
$msg   = '';

// Сообщение после успешного сброса пароля
if (($_GET['msg'] ?? '') === 'password_reset') {
    $msg = 'Пароль успешно изменён. Войдите с новым паролем.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    if (try_login($username, $password)) {
        header('Location: calendar.php');
        exit;
    }
    $error = 'Неверный логин или пароль.';
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Вход — Меню</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
<div class="login-wrap">
    <div class="login-box">
        <div class="logo-big">Мониторинг питания</div>
        <div class="subtitle">Управление ежедневным меню</div>
        <div class="divider">✦</div>

        <?php if ($error): ?>
            <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <?php if ($msg): ?>
            <div class="alert alert-success"><?= htmlspecialchars($msg) ?></div>
        <?php endif; ?>

        <form method="post" autocomplete="off">
            <div class="form-group">
                <label for="username">Логин</label>
                <input type="text" id="username" name="username" class="form-control"
                       value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
                       autofocus required>
            </div>
            <div class="form-group">
                <label for="password">Пароль</label>
                <input type="password" id="password" name="password" class="form-control" required>
            </div>
            <div class="mt-2">
                <button type="submit" class="btn btn-primary" style="width:100%">Войти</button>
            </div>
        </form>

        <div class="mt-2" style="text-align:center;font-size:0.82rem">
            <a href="forgot-password.php" style="color:var(--orange)">Забыли пароль?</a>
        </div>
    </div>
</div>
</body>
</html>
