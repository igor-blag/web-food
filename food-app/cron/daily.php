<?php
/**
 * Cron-скрипт: запускать ежедневно утром (например в 07:00).
 *
 * На Jino в панели управления:
 *   Команда: php /home/YOURLOGIN/public_html/menu-app/cron/daily.php
 *   Расписание: 0 7 * * *
 *
 * Что делает:
 *   1. Генерирует Excel-файл на сегодня (если день учебный и шаблон назначен)
 *   2. Удаляет xlsx-файлы старше DELETE_AFTER_DAYS дней
 *   3. Отправляет напоминание, если в следующие 3 рабочих дня меню не заполнено
 */

// CLI-only защита
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit('Access denied.');
}

define('APP_ROOT', dirname(__DIR__));
require_once APP_ROOT . '/config.php';
require_once APP_ROOT . '/src/db.php';
require_once APP_ROOT . '/src/excel.php';
require_once APP_ROOT . '/src/mailer.php';

$today = date('Y-m-d');
$departments = get_enabled_departments();

echo "[" . date('Y-m-d H:i:s') . "] Старт генерации меню\n";

// 1. Генерация файлов на 7 дней вперёд для включённых отделений с публикацией
$dt = new DateTime($today);
for ($i = 0; $i < 7; $i++) {
    $dateStr = $dt->format('Y-m-d');
    $isVacation = is_vacation_day($dateStr);
    foreach ($departments as $dept) {
        if (!$dept['publish_xlsx']) continue;
        // Пропускаем каникулярные дни (кроме отделений, работающих круглый год)
        if ($isVacation && empty($dept['ignore_vacations'])) continue;
        $path = generate_menu_excel($dateStr, $dept['code']);
        if ($path) {
            echo "[OK] $dateStr/{$dept['code']}: " . basename($path) . "\n";
        }
    }
    $dt->modify('+1 day');
}

// 2. Очистка старых файлов
$deleted = cleanup_old_files();
echo "[OK] Удалено старых файлов: $deleted\n";

// 3. Напоминание о незаполненных рабочих днях
if (ADMIN_EMAIL !== '') {
    $missing = find_missing_days($today, 3);
    if (!empty($missing)) {
        send_reminder($missing);
    }
}

echo "[" . date('Y-m-d H:i:s') . "] Завершено.\n";

// ── Вспомогательные функции ──────────────────────────────────

/**
 * Возвращает до $lookAhead ближайших рабочих дней без назначенного меню (для sm).
 * Проверяет дни начиная со $fromDate (включительно).
 */
function find_missing_days(string $fromDate, int $lookAhead): array
{
    $missing = [];
    $dt = new DateTimeImmutable($fromDate);
    $checked = 0;
    $limit = $lookAhead * 7; // запас на выходные/праздники

    while ($checked < $limit && count($missing) < $lookAhead) {
        $dow = (int)$dt->format('N'); // 1=Пн, 7=Вс
        $dateStr = $dt->format('Y-m-d');
        if ($dow <= 5 && !is_vacation_day($dateStr)) {
            // Рабочий день (не каникулы) — проверяем наличие записи в calendar
            $s = db()->prepare(
                'SELECT template_id FROM calendar WHERE date=? AND school_type=\'sm\' LIMIT 1'
            );
            $s->execute([$dateStr]);
            $row = $s->fetch();
            // Нет записи или явный выходной (template_id IS NULL) — считаем незаполненным
            if (!$row || $row['template_id'] === null) {
                $missing[] = $dt->format('d.m.Y (D)');
            }
        }
        $dt = $dt->modify('+1 day');
        $checked++;
    }
    return $missing;
}

/**
 * Отправляет email-напоминание о незаполненных днях.
 */
function send_reminder(array $missingDays): void
{
    $list = implode('', array_map(fn($d) => "<li>$d</li>", $missingDays));
    $html = "<p>Добрый день!</p>"
          . "<p>Меню не заполнено на следующие рабочие дни:</p>"
          . "<ul>$list</ul>"
          . "<p>Пожалуйста, заполните меню в системе мониторинга питания.</p>";

    $ok = send_mail(ADMIN_EMAIL, 'Напоминание: меню не заполнено', $html);
    if ($ok) {
        echo "[OK] Напоминание отправлено на " . ADMIN_EMAIL . "\n";
    } else {
        echo "[!!] Не удалось отправить напоминание.\n";
    }
}
