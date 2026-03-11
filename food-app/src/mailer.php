<?php
/**
 * Отправка email.
 * Использует PHP mail() по умолчанию.
 * Если задан SMTP_HOST — использует PHPMailer/SMTP.
 */
require_once __DIR__ . '/../config.php';

function send_mail(string $to, string $subject, string $htmlBody): bool
{
    if (SMTP_HOST !== '') {
        return _send_smtp($to, $subject, $htmlBody);
    }
    return _send_native($to, $subject, $htmlBody);
}

function _send_native(string $to, string $subject, string $htmlBody): bool
{
    $headers  = "From: " . MAIL_FROM_NAME . " <" . MAIL_FROM . ">\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $subjectEncoded = '=?UTF-8?B?' . base64_encode($subject) . '?=';
    return mail($to, $subjectEncoded, $htmlBody, $headers);
}

function _send_smtp(string $to, string $subject, string $htmlBody): bool
{
    require_once __DIR__ . '/../vendor/autoload.php';
    $mail = new PHPMailer\PHPMailer\PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = SMTP_USER !== '';
        $mail->Username   = SMTP_USER;
        $mail->Password   = SMTP_PASS;
        if (SMTP_SECURE === '') {
            $mail->SMTPSecure = false;
            $mail->SMTPAutoTLS = false;
        } else {
            $mail->SMTPSecure = SMTP_SECURE === 'ssl'
                ? PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS
                : PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        }
        $mail->Port       = (int) SMTP_PORT;
        $mail->CharSet    = 'UTF-8';

        $mail->setFrom(MAIL_FROM, MAIL_FROM_NAME);
        $mail->addAddress($to);
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $htmlBody;
        $mail->send();
        return true;
    } catch (\Exception $e) {
        error_log('Mailer error: ' . $e->getMessage());
        return false;
    }
}

/**
 * Создаёт токен (6-значный код), сохраняет в БД и отправляет на email.
 * Возвращает code при успехе, '' при ошибке отправки.
 */
function send_token(string $email, string $purpose, ?int $userId = null): string
{
    require_once __DIR__ . '/db.php';
    $code    = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $expires = date('Y-m-d H:i:s', time() + TOKEN_TTL_MIN * 60);

    // Инвалидируем старые токены для этого email/purpose
    db()->prepare(
        'UPDATE email_tokens SET used=1 WHERE email=? AND purpose=? AND used=0'
    )->execute([$email, $purpose]);

    db()->prepare(
        'INSERT INTO email_tokens (user_id, purpose, code, email, expires_at) VALUES (?,?,?,?,?)'
    )->execute([$userId, $purpose, $code, $email, $expires]);

    $subject = $purpose === 'setup'
        ? 'Код подтверждения для настройки системы'
        : 'Код для сброса пароля';

    $html = '<p>Ваш код: <strong style="font-size:24px">' . $code . '</strong></p>'
          . '<p>Действителен ' . TOKEN_TTL_MIN . ' минут.</p>';

    if (!send_mail($email, $subject, $html)) {
        return '';
    }
    return $code;
}

/**
 * Проверяет код. Возвращает запись из email_tokens или null.
 */
function verify_token(string $email, string $purpose, string $code): ?array
{
    require_once __DIR__ . '/db.php';
    $s = db()->prepare(
        'SELECT * FROM email_tokens
          WHERE email=? AND purpose=? AND code=? AND used=0 AND expires_at > NOW()
          ORDER BY id DESC LIMIT 1'
    );
    $s->execute([$email, $purpose, $code]);
    $row = $s->fetch();
    if ($row) {
        db()->prepare('UPDATE email_tokens SET used=1 WHERE id=?')->execute([$row['id']]);
    }
    return $row ?: null;
}
