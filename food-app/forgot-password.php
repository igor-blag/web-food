<?php
/**
 * forgot-password.php — сброс пароля.
 *
 * Шаг 1: ввести email → отправить код
 * Шаг 2: ввести код из письма
 * Шаг 3: задать новый пароль
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/src/db.php';
require_once __DIR__ . '/src/mailer.php';
require_once __DIR__ . '/src/auth.php';

session_init();

$step  = $_SESSION['reset_step']  ?? 1;
$email = $_SESSION['reset_email'] ?? '';
$uid   = $_SESSION['reset_uid']   ?? null;
$error = '';
$info  = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if ($step === 1) {
        $em = strtolower(trim($_POST['email'] ?? ''));
        if (!filter_var($em, FILTER_VALIDATE_EMAIL)) {
            $error = 'Введите корректный email.';
        } else {
            $s = db()->prepare('SELECT id FROM users WHERE email=? AND email_verified=1');
            $s->execute([$em]);
            $user = $s->fetch();
            if (!$user) {
                // Не раскрываем, есть ли пользователь
                $info = 'Если этот email зарегистрирован, на него придёт код.';
            } else {
                $result = send_token($em, 'reset', (int)$user['id']);
                if ($result === '') {
                    $error = 'Не удалось отправить письмо.';
                } else {
                    $_SESSION['reset_email'] = $em;
                    $_SESSION['reset_uid']   = (int)$user['id'];
                    $_SESSION['reset_step']  = 2;
                    $step  = 2;
                    $email = $em;
                    $uid   = (int)$user['id'];
                    $info  = 'Код отправлен на ' . htmlspecialchars($em) . '.';
                }
            }
        }
    } elseif ($step === 2) {
        $code = trim($_POST['code'] ?? '');
        $tok  = verify_token($email, 'reset', $code);
        if (!$tok) {
            $error = 'Неверный или просроченный код.';
        } else {
            $_SESSION['reset_step'] = 3;
            $step = 3;
        }
    } elseif ($step === 3) {
        $pass1 = $_POST['password']  ?? '';
        $pass2 = $_POST['password2'] ?? '';
        if (strlen($pass1) < 8) {
            $error = 'Пароль должен содержать минимум 8 символов.';
        } elseif ($pass1 !== $pass2) {
            $error = 'Пароли не совпадают.';
        } else {
            $hash = password_hash($pass1, PASSWORD_BCRYPT);
            db()->prepare('UPDATE users SET password_hash=? WHERE id=?')->execute([$hash, $uid]);
            unset($_SESSION['reset_step'], $_SESSION['reset_email'], $_SESSION['reset_uid']);
            header('Location: index.php?msg=password_reset');
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Сброс пароля — Меню</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
<div class="login-wrap">
    <div class="login-box" style="width:400px">
        <div class="logo-big">Мониторинг питания</div>
        <div class="subtitle">Восстановление пароля</div>
        <div class="divider">✦</div>

        <?php if ($error): ?>
            <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <?php if ($info): ?>
            <div class="alert alert-success"><?= $info ?></div>
        <?php endif; ?>

        <?php if ($step === 1): ?>
        <form method="post">
            <div class="form-group">
                <label>Email</label>
                <input type="email" name="email" class="form-control" required autofocus
                       value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
            </div>
            <div class="mt-2">
                <button type="submit" class="btn btn-primary" style="width:100%">Отправить код →</button>
            </div>
        </form>

        <?php elseif ($step === 2): ?>
        <p class="text-muted mb-2">Код отправлен на <strong><?= htmlspecialchars($email) ?></strong>.</p>
        <form method="post">
            <div class="form-group">
                <label>Код подтверждения</label>
                <input type="text" name="code" class="form-control" required autofocus
                       maxlength="6" pattern="\d{6}" placeholder="000000"
                       style="letter-spacing:0.3em;font-size:1.3rem;text-align:center">
            </div>
            <div class="mt-2">
                <button type="submit" class="btn btn-primary" style="width:100%">Подтвердить →</button>
            </div>
        </form>

        <?php elseif ($step === 3): ?>
        <form method="post">
            <div class="form-group">
                <label>Новый пароль (минимум 8 символов)</label>
                <input type="password" name="password" class="form-control" required minlength="8" autofocus>
            </div>
            <div class="form-group">
                <label>Повторите новый пароль</label>
                <input type="password" name="password2" class="form-control" required minlength="8">
            </div>
            <div class="mt-2">
                <button type="submit" class="btn btn-primary" style="width:100%">Сохранить пароль →</button>
            </div>
        </form>
        <?php endif; ?>

        <div class="mt-2" style="text-align:center;font-size:0.82rem">
            <a href="index.php" style="color:var(--orange)">← Вернуться ко входу</a>
        </div>
    </div>
</div>
</body>
</html>
