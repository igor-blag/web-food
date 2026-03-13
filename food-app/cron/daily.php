<?php
/**
 * Cron-скрипт: запускать ежедневно утром (например в 07:00).
 *
 * На Jino в панели управления:
 *   Команда: php /home/YOURLOGIN/public_html/food-app/cron/daily.php
 *   Расписание: 0 7 * * *
 *
 * Что делает:
 *   Считает, на сколько рабочих дней вперёд заполнено меню (school_type='sm').
 *   Если остаток ≤ 3 — отправляет email-напоминание администратору.
 */

// CLI-only защита
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit('Access denied.');
}

define('APP_ROOT', dirname(__DIR__));
require_once APP_ROOT . '/config.php';
require_once APP_ROOT . '/src/db.php';
require_once APP_ROOT . '/src/mailer.php';

$today = date('Y-m-d');

$filledDays = count_filled_workdays_ahead($today);

echo "[" . date('Y-m-d H:i:s') . "] Заполненных рабочих дней вперёд: $filledDays\n";

if ($filledDays <= 3 && ADMIN_EMAIL !== '') {
    send_low_menu_reminder($filledDays);
}

echo "[" . date('Y-m-d H:i:s') . "] Завершено.\n";

// ─────────────────────────────────────────────────────────────

/**
 * Считает ближайшие рабочие (не-каникулярные) дни начиная с завтра,
 * у которых в calendar (school_type='sm') есть назначенный шаблон.
 * Остановка при первом незаполненном рабочем дне.
 */
function count_filled_workdays_ahead(string $fromDate): int
{
    $dt    = new DateTimeImmutable($fromDate);
    $dt    = $dt->modify('+1 day');
    $count = 0;
    $limit = 60; // максимум смотрим вперёд на 60 дней

    for ($i = 0; $i < $limit; $i++) {
        $dow     = (int)$dt->format('N');
        $dateStr = $dt->format('Y-m-d');

        if ($dow <= 5 && !is_vacation_day($dateStr)) {
            // Рабочий день — есть ли меню?
            $s = db()->prepare(
                'SELECT template_id FROM calendar
                  WHERE date = ? AND school_type = \'sm\'
                  LIMIT 1'
            );
            $s->execute([$dateStr]);
            $row = $s->fetch();

            if (!$row || $row['template_id'] === null) {
                break; // первый незаполненный — останавливаемся
            }
            $count++;
        }

        $dt = $dt->modify('+1 day');
    }

    return $count;
}

/**
 * Отправляет email о том, что меню заполнено только на $days рабочих дней.
 */
function send_low_menu_reminder(int $days): void
{
    $word = match(true) {
        $days === 0 => 'не заполнено ни на один день',
        $days === 1 => 'заполнено только на <b>1 рабочий день</b>',
        default     => 'заполнено только на <b>' . $days . ' рабочих дня</b>',
    };

    $html = "<p>Добрый день!</p>"
          . "<p>Меню в системе мониторинга питания $word вперёд.</p>"
          . "<p>Пожалуйста, пополните расписание питания.</p>";

    $ok = send_mail(ADMIN_EMAIL, 'Напоминание: меню заполнено на ' . $days . ' дн.', $html);

    if ($ok) {
        echo "[OK] Напоминание отправлено на " . ADMIN_EMAIL . "\n";
    } else {
        echo "[!!] Не удалось отправить напоминание.\n";
    }
}
