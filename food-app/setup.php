<?php
/**
 * setup.php — мастер первоначальной настройки.
 * Доступен только пока в таблице users нет записей.
 *
 * Шаг 1: ввести email → отправить код
 * Шаг 2: ввести код из письма
 * Шаг 3: задать логин + пароль → создать пользователя
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/src/db.php';
require_once __DIR__ . '/src/mailer.php';
require_once __DIR__ . '/src/auth.php';

session_init();

// Если пользователи уже есть — редирект на вход
$count = (int) db()->query('SELECT COUNT(*) FROM users')->fetchColumn();
if ($count > 0) {
    header('Location: index.php');
    exit;
}

$step  = $_SESSION['setup_step'] ?? 1;
$email = $_SESSION['setup_email'] ?? '';
$error = '';
$info  = '';

// ── Обработка POST ───────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if ($step === 1) {
        // Шаг 1: получить email и отправить код
        $em = trim($_POST['email'] ?? '');
        if (!filter_var($em, FILTER_VALIDATE_EMAIL)) {
            $error = 'Введите корректный email.';
        } else {
            $result = send_token($em, 'setup');
            if ($result === '') {
                $error = 'Не удалось отправить письмо. Проверьте настройки почты в config.php.';
            } else {
                $_SESSION['setup_email'] = $em;
                $_SESSION['setup_step']  = 2;
                $step  = 2;
                $email = $em;
                $info  = 'Код отправлен на ' . htmlspecialchars($em) . '. Проверьте почту.';
            }
        }
    } elseif ($step === 2) {
        // Шаг 2: проверить код
        $code = trim($_POST['code'] ?? '');
        $tok  = verify_token($email, 'setup', $code);
        if (!$tok) {
            $error = 'Неверный или просроченный код.';
        } else {
            $_SESSION['setup_step'] = 3;
            $step = 3;
        }
    } elseif ($step === 3) {
        // Шаг 3: создать пользователя
        $username = trim($_POST['username'] ?? '');
        $pass1    = $_POST['password']  ?? '';
        $pass2    = $_POST['password2'] ?? '';

        if ($username === '' || strlen($username) < 3) {
            $error = 'Логин должен содержать минимум 3 символа.';
        } elseif (strlen($pass1) < 8) {
            $error = 'Пароль должен содержать минимум 8 символов.';
        } elseif ($pass1 !== $pass2) {
            $error = 'Пароли не совпадают.';
        } else {
            $hash = password_hash($pass1, PASSWORD_BCRYPT);
            db()->prepare(
                'INSERT INTO users (username, password_hash, email, email_verified) VALUES (?,?,?,1)'
            )->execute([$username, $hash, $email]);

            // Очистить сессию мастера
            unset($_SESSION['setup_step'], $_SESSION['setup_email']);

            // Войти автоматически
            if (try_login($username, $pass1)) {
                header('Location: calendar.php');
                exit;
            }
            header('Location: index.php');
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
    <title>Первоначальная настройка — Меню</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
<div class="login-wrap">
    <div class="login-box" style="width:400px">
        <div class="logo-big">Мониторинг питания</div>
        <div class="subtitle">Первоначальная настройка</div>
        <div class="divider">✦</div>

        <?php if ($error): ?>
            <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <?php if ($info): ?>
            <div class="alert alert-success"><?= $info ?></div>
        <?php endif; ?>

        <!-- ── Шаг 1: Email ──────────────────────────────────── -->
        <?php if ($step === 1): ?>
        <p class="text-muted mb-2">Введите email, на который будут приходить напоминания и код подтверждения.</p>
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

        <!-- ── Шаг 2: Код ────────────────────────────────────── -->
        <?php elseif ($step === 2): ?>
        <p class="text-muted mb-2">Введите 6-значный код из письма, отправленного на <strong><?= htmlspecialchars($email) ?></strong>.</p>
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
        <div class="mt-2 text-muted" style="text-align:center;font-size:0.82rem">
            <a href="setup.php" style="color:var(--orange)">Ввести другой email</a>
        </div>

        <!-- ── Шаг 3: Логин и пароль ─────────────────────────── -->
        <?php elseif ($step === 3): ?>
        <p class="text-muted mb-2">Придумайте логин и пароль для входа в систему.</p>
        <form method="post">
            <div class="form-group">
                <label>Логин</label>
                <input type="text" name="username" class="form-control" required autofocus
                       minlength="3" maxlength="50"
                       value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label>Пароль (минимум 8 символов)</label>
                <input type="password" name="password" class="form-control" required minlength="8">
            </div>
            <div class="form-group">
                <label>Повторите пароль</label>
                <input type="password" name="password2" class="form-control" required minlength="8">
            </div>
            <div class="mt-2">
                <button type="submit" class="btn btn-primary" style="width:100%">Создать аккаунт →</button>
            </div>
        </form>
        <?php endif; ?>

        <div class="mt-3" style="text-align:center">
            <div class="text-muted" style="font-size:0.78rem">Шаг <?= $step ?> из 3</div>
        </div>
    </div>
</div>
</body>
</html>
