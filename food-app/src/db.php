<?php
require_once __DIR__ . '/../config.php';

function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $pdo = new PDO(
            'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
            DB_USER,
            DB_PASS,
            [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]
        );
    }
    return $pdo;
}

// Получить все шаблоны для типа школы (sm/main/ss)
function get_templates(string $type = 'sm'): array
{
    $s = db()->prepare('SELECT * FROM menu_templates WHERE school_type = ? ORDER BY day_number');
    $s->execute([$type]);
    return $s->fetchAll();
}

// Получить шаблон по id
function get_template(int $id): ?array
{
    $s = db()->prepare('SELECT * FROM menu_templates WHERE id = ?');
    $s->execute([$id]);
    return $s->fetch() ?: null;
}

// Добавить новый шаблон (следующий day_number для типа)
function add_template(string $type): int
{
    $s = db()->prepare('SELECT COALESCE(MAX(day_number), 0) FROM menu_templates WHERE school_type = ?');
    $s->execute([$type]);
    $nextDay = (int)$s->fetchColumn() + 1;
    $stmt = db()->prepare('INSERT INTO menu_templates (day_number, school_type, label) VALUES (?, ?, ?)');
    $stmt->execute([$nextDay, $type, 'День ' . $nextDay]);
    return (int)db()->lastInsertId();
}

// Удалить шаблон (menu_items — CASCADE, calendar.template_id → NULL)
function delete_template(int $id): void
{
    db()->prepare('DELETE FROM menu_templates WHERE id = ?')->execute([$id]);
}

// Установить флаг интерната для шаблона
function set_template_boarding(int $id, int $is_boarding): void
{
    $s = db()->prepare('UPDATE menu_templates SET is_boarding = ? WHERE id = ?');
    $s->execute([$is_boarding ? 1 : 0, $id]);
}

// Получить блюда шаблона, сгруппированные по типу приёма пищи
function get_template_items(int $template_id): array
{
    $s = db()->prepare(
        'SELECT * FROM menu_items WHERE template_id = ? ORDER BY meal_type, sort_order, id'
    );
    $s->execute([$template_id]);
    $rows = $s->fetchAll();

    $grouped = [
        'breakfast'       => [],
        'breakfast2'      => [],
        'lunch'           => [],
        'afternoon_snack' => [],
        'dinner'          => [],
        'dinner2'         => [],
    ];
    foreach ($rows as $row) {
        if (isset($grouped[$row['meal_type']])) {
            $grouped[$row['meal_type']][] = $row;
        }
    }
    return $grouped;
}

// Получить запись календаря на дату и тип школы
function get_calendar_day(string $date, string $type = 'sm'): ?array
{
    $s = db()->prepare(
        'SELECT c.*, t.label as template_label, t.is_boarding
         FROM calendar c
         LEFT JOIN menu_templates t ON t.id = c.template_id
         WHERE c.date = ? AND c.school_type = ?'
    );
    $s->execute([$date, $type]);
    return $s->fetch() ?: null;
}

// Получить все записи календаря за месяц для типа школы
function get_calendar_month(int $year, int $month, string $type = 'sm'): array
{
    $from = sprintf('%04d-%02d-01', $year, $month);
    $to   = date('Y-m-t', strtotime($from));
    $s = db()->prepare(
        'SELECT c.*, t.label as template_label
         FROM calendar c
         LEFT JOIN menu_templates t ON t.id = c.template_id
         WHERE c.date BETWEEN ? AND ? AND c.school_type = ?'
    );
    $s->execute([$from, $to, $type]);
    $rows = [];
    foreach ($s->fetchAll() as $row) {
        $rows[$row['date']] = $row;
    }
    return $rows;
}

// Сохранить/обновить день в календаре
function save_calendar_day(string $date, ?int $template_id, ?string $school, ?string $dept, string $type = 'sm', int $is_cycle_start = 0): void
{
    $s = db()->prepare(
        'INSERT INTO calendar (date, school_type, template_id, school, dept, is_cycle_start)
         VALUES (?, ?, ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE template_id=VALUES(template_id), school=VALUES(school), dept=VALUES(dept), is_cycle_start=VALUES(is_cycle_start)'
    );
    $s->execute([$date, $type, $template_id, $school, $dept, $is_cycle_start ? 1 : 0]);
}

// Количество шаблонов для типа (длина цикла)
function get_cycle_length(string $type): int
{
    $s = db()->prepare('SELECT COUNT(*) FROM menu_templates WHERE school_type = ?');
    $s->execute([$type]);
    return (int)$s->fetchColumn();
}

// Шаблоны в порядке day_number, ключ = day_number
function get_templates_ordered(string $type): array
{
    $s = db()->prepare('SELECT id, day_number FROM menu_templates WHERE school_type = ? ORDER BY day_number ASC');
    $s->execute([$type]);
    $map = [];
    foreach ($s->fetchAll() as $row) {
        $map[(int)$row['day_number']] = (int)$row['id'];
    }
    return $map;
}

// Заполнить Пн–Пт от start_date до 31 декабря того же года шаблонами цикла
function assign_cycle(string $start_date, int $start_day, string $type, ?string $school, ?string $dept, ?string $end_date = null, array $workdays = [1,2,3,4,5]): int
{
    $templates  = get_templates_ordered($type);
    $cycle_len  = count($templates);
    if ($cycle_len === 0) return 0;

    $keys = array_keys($templates); // [1,2,...,N]
    $idx  = ($start_day - 1) % $cycle_len; // 0-based

    $cur  = new DateTime($start_date);
    $end  = new DateTime($end_date ?? ($cur->format('Y') . '-12-31'));
    $count = 0;
    $first = true;

    while ($cur <= $end) {
        $wday = (int)$cur->format('N'); // 1=пн, 7=вс
        if (in_array($wday, $workdays, true)) {
            $dateStr  = $cur->format('Y-m-d');
            $existing = get_calendar_day($dateStr, $type);
            // Пропускать только явные выходные (запись есть, но template_id = null)
            if ($existing === null || $existing['template_id'] !== null) {
                $day_num  = $keys[$idx % $cycle_len];
                $tpl_id   = $templates[$day_num];
                save_calendar_day($dateStr, $tpl_id, $school, $dept, $type, $first ? 1 : 0);
                $idx++;
                $count++;
            }
            $first = false;
        }
        $cur->modify('+1 day');
    }
    return $count;
}

// Все записи календаря за год, ключ = 'Y-m-d'
function get_calendar_year(int $year, string $type): array
{
    $from = sprintf('%04d-01-01', $year);
    $to   = sprintf('%04d-12-31', $year);
    $s = db()->prepare(
        'SELECT c.*, t.day_number
         FROM calendar c
         LEFT JOIN menu_templates t ON t.id = c.template_id
         WHERE c.date BETWEEN ? AND ? AND c.school_type = ?'
    );
    $s->execute([$from, $to, $type]);
    $rows = [];
    foreach ($s->fetchAll() as $row) {
        $rows[$row['date']] = $row;
    }
    return $rows;
}

// Массовое сохранение дней календаря
// Каждый элемент $days: ['date'=>'Y-m-d', 'day_num'=>int, 'school'=>?, 'dept'=>?, 'is_cycle_start'=>0]
// day_num = -1 → удалить запись; 0 → явный выходной (template_id=null); N → шаблон дня N
function bulk_save_calendar(array $days, string $type): void
{
    $db        = db();
    $templates = get_templates_ordered($type);
    $ins = $db->prepare(
        'INSERT INTO calendar (date, school_type, template_id, school, dept, is_cycle_start)
         VALUES (?, ?, ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE template_id=VALUES(template_id), school=VALUES(school),
                                 dept=VALUES(dept), is_cycle_start=VALUES(is_cycle_start)'
    );
    $del = $db->prepare('DELETE FROM calendar WHERE date = ? AND school_type = ?');
    foreach ($days as $d) {
        $date    = $d['date']    ?? '';
        $day_num = (int)($d['day_num'] ?? 0);
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) continue;
        if ($day_num === -1) {
            $del->execute([$date, $type]);
        } elseif ($day_num === 0) {
            $ins->execute([$date, $type, null, $d['school'] ?? null, $d['dept'] ?? null, 0]);
        } else {
            $tpl_id = $templates[$day_num] ?? null;
            if ($tpl_id) {
                $ins->execute([$date, $type, $tpl_id,
                               $d['school'] ?? null, $d['dept'] ?? null,
                               !empty($d['is_cycle_start']) ? 1 : 0]);
            }
        }
    }
}

// Удалить день из календаря (сделать "не учебным")
function delete_calendar_day(string $date, string $type = 'sm'): void
{
    $s = db()->prepare('DELETE FROM calendar WHERE date = ? AND school_type = ?');
    $s->execute([$date, $type]);
}

// ─── Настройки пищеблока ─────────────────────────────────────

function get_kitchen_settings(): array
{
    $s = db()->query('SELECT * FROM kitchen_settings WHERE id = 1');
    return $s->fetch() ?: ['id' => 1, 'org_name' => ''];
}

function save_kitchen_settings(string $org_name): void
{
    $s = db()->prepare('UPDATE kitchen_settings SET org_name = ? WHERE id = 1');
    $s->execute([$org_name]);
}

function save_tm_approver(string $position, string $name): void
{
    $s = db()->prepare('UPDATE kitchen_settings SET tm_approver_position = ?, tm_approver_name = ? WHERE id = 1');
    $s->execute([$position, $name]);
}

function save_tm_approve_date(?string $date): void
{
    $s = db()->prepare('UPDATE kitchen_settings SET tm_approve_date = ? WHERE id = 1');
    $s->execute([$date ?: null]);
}

function get_org_name(): string
{
    return get_kitchen_settings()['org_name'];
}

// ─── Отделения ───────────────────────────────────────────────

function get_all_departments(): array
{
    return db()->query('SELECT * FROM departments ORDER BY sort_order, id')->fetchAll();
}

function get_enabled_departments(): array
{
    return db()->query('SELECT * FROM departments WHERE is_enabled = 1 ORDER BY sort_order, id')->fetchAll();
}

function get_department(string $code): ?array
{
    $s = db()->prepare('SELECT * FROM departments WHERE code = ?');
    $s->execute([$code]);
    return $s->fetch() ?: null;
}

function save_department(int $id, array $data): void
{
    $allowed = ['label','label_short','dept_name','is_enabled','is_builtin',
                'is_boarding','workdays','publish_xlsx','file_suffix','sort_order','note','ignore_vacations'];
    $sets = [];
    $vals = [];
    foreach ($data as $k => $v) {
        if (in_array($k, $allowed, true)) {
            $sets[] = "`$k` = ?";
            $vals[] = $v;
        }
    }
    if (!$sets) return;
    $vals[] = $id;
    db()->prepare('UPDATE departments SET ' . implode(', ', $sets) . ' WHERE id = ?')->execute($vals);
}

function add_department(string $code, string $label, string $file_suffix = ''): int
{
    $maxSort = (int)db()->query('SELECT COALESCE(MAX(sort_order),0) FROM departments')->fetchColumn();
    $s = db()->prepare(
        'INSERT INTO departments (code, label, label_short, is_enabled, is_builtin, file_suffix, sort_order)
         VALUES (?, ?, ?, 1, 0, ?, ?)'
    );
    $s->execute([$code, $label, $label, $file_suffix, $maxSort + 10]);
    return (int)db()->lastInsertId();
}

function delete_department(int $id): void
{
    db()->prepare('DELETE FROM departments WHERE id = ? AND is_builtin = 0')->execute([$id]);
}

function get_workdays(string $code): array
{
    $dept = get_department($code);
    if (!$dept) return [1,2,3,4,5];
    return array_map('intval', explode(',', $dept['workdays']));
}

/**
 * Синхронизировать количество шаблонов для отделения с заданной длиной цикла.
 * Если шаблонов меньше — добавляет недостающие.
 * Если шаблонов больше — удаляет лишние (с конца, только пустые без блюд).
 * Возвращает ['added' => int, 'removed' => int, 'kept' => int]
 */
function sync_templates_to_cycle_length(string $type, int $desired): array
{
    $templates = get_templates($type);
    $current = count($templates);
    $result = ['added' => 0, 'removed' => 0, 'kept' => $current];

    if ($desired === $current) return $result;

    if ($desired > $current) {
        // Добавить недостающие шаблоны
        for ($i = 0; $i < $desired - $current; $i++) {
            add_template($type);
            $result['added']++;
        }
        $result['kept'] = $current;
    } else {
        // Удалить лишние шаблоны с конца (только пустые)
        $toRemove = $current - $desired;
        $reversed = array_reverse($templates);
        foreach ($reversed as $tpl) {
            if ($toRemove <= 0) break;
            // Проверить, есть ли блюда у шаблона
            $items = get_template_items((int)$tpl['id']);
            $hasItems = false;
            foreach ($items as $mealItems) {
                if (!empty($mealItems)) { $hasItems = true; break; }
            }
            if (!$hasItems) {
                delete_template((int)$tpl['id']);
                $toRemove--;
                $result['removed']++;
            }
        }
        $result['kept'] = $current - $result['removed'];
    }
    return $result;
}

// Сохранить все блюда шаблона (полная перезапись)
function save_template_items(int $template_id, array $items): void
{
    $db = db();
    $db->prepare('DELETE FROM menu_items WHERE template_id = ?')->execute([$template_id]);
    $s = $db->prepare(
        'INSERT INTO menu_items
            (template_id, meal_type, section, recipe_num, dish_name, grams, price, kcal, protein, fat, carbs, sort_order)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    foreach ($items as $order => $item) {
        $s->execute([
            $template_id,
            $item['meal_type'],
            $item['section']    ?? null,
            $item['recipe_num'] ?? null,
            $item['dish_name']  ?? null,
            isset($item['grams'])   ? (float)$item['grams']   : null,
            isset($item['price'])   ? (float)$item['price']   : null,
            isset($item['kcal'])    ? (float)$item['kcal']    : null,
            isset($item['protein']) ? (float)$item['protein'] : null,
            isset($item['fat'])     ? (float)$item['fat']     : null,
            isset($item['carbs'])   ? (float)$item['carbs']   : null,
            $order,
        ]);
    }
}

// ─── Учебный год и каникулы ─────────────────────────────────

function get_academic_year_settings(): array
{
    $s = db()->query('SELECT academic_year_start, academic_year_end, reset_cycle_after_vacation FROM kitchen_settings WHERE id = 1');
    $row = $s->fetch();
    return $row ?: ['academic_year_start' => '09-01', 'academic_year_end' => '05-31', 'reset_cycle_after_vacation' => 0];
}

function save_academic_year_settings(string $start, string $end, int $reset): void
{
    $s = db()->prepare(
        'UPDATE kitchen_settings SET academic_year_start = ?, academic_year_end = ?, reset_cycle_after_vacation = ? WHERE id = 1'
    );
    $s->execute([$start, $end, $reset ? 1 : 0]);
}

function get_vacations(string $academic_year): array
{
    $s = db()->prepare('SELECT * FROM vacations WHERE academic_year = ? ORDER BY date_from');
    $s->execute([$academic_year]);
    return $s->fetchAll();
}

function add_vacation(string $academic_year, string $label, string $date_from, string $date_to): int
{
    $s = db()->prepare(
        'INSERT INTO vacations (academic_year, label, date_from, date_to) VALUES (?, ?, ?, ?)'
    );
    $s->execute([$academic_year, $label, $date_from, $date_to]);
    return (int)db()->lastInsertId();
}

function update_vacation(int $id, string $label, string $date_from, string $date_to): void
{
    $s = db()->prepare('UPDATE vacations SET label = ?, date_from = ?, date_to = ? WHERE id = ?');
    $s->execute([$label, $date_from, $date_to, $id]);
}

function delete_vacation(int $id): void
{
    db()->prepare('DELETE FROM vacations WHERE id = ?')->execute([$id]);
}

function is_vacation_day(string $date): bool
{
    $s = db()->prepare('SELECT COUNT(*) FROM vacations WHERE ? BETWEEN date_from AND date_to');
    $s->execute([$date]);
    return (int)$s->fetchColumn() > 0;
}

/**
 * Определить учебный год по дате.
 * Если дата >= academic_year_start текущего календарного года → YYYY-(YYYY+1)
 * Иначе → (YYYY-1)-YYYY
 */
function get_academic_year_for_date(string $date): string
{
    $settings = get_academic_year_settings();
    $year = (int)(new DateTime($date))->format('Y');
    $startThisYear = $year . '-' . $settings['academic_year_start'];
    if ($date >= $startThisYear) {
        return $year . '-' . ($year + 1);
    }
    return ($year - 1) . '-' . $year;
}

/**
 * Вычислить текущий рабочий период (четверть/полугодие) для типа отделения.
 * Возвращает ['from' => 'YYYY-MM-DD', 'to' => 'YYYY-MM-DD', 'label' => '...']
 * Для отделений с ignore_vacations — весь учебный год.
 */
function get_current_period(string $type, ?string $date = null): array
{
    $date = $date ?? date('Y-m-d');
    $settings = get_academic_year_settings();
    $dept = get_department($type);
    $academicYear = get_academic_year_for_date($date);

    // Границы учебного года
    [$yearStart, $yearEnd] = explode('-', $academicYear, 2);
    // Учебный год 2025-2026: start = 2025-09-01, end = 2026-05-31
    // Но yearEnd из academic_year = "2026", а settings end = "05-31"
    $ayFrom = $yearStart . '-' . $settings['academic_year_start'];
    $ayTo   = $yearEnd . '-' . $settings['academic_year_end'];

    // Отделение без каникул — весь учебный год
    if ($dept && $dept['ignore_vacations']) {
        return ['from' => $ayFrom, 'to' => $ayTo, 'label' => 'Учебный год ' . $academicYear];
    }

    // Каникулы за этот учебный год, отсортированы по date_from
    $vacations = get_vacations($academicYear);

    if (empty($vacations)) {
        return ['from' => $ayFrom, 'to' => $ayTo, 'label' => 'Учебный год ' . $academicYear];
    }

    // Построить периоды между каникулами
    $periods = [];
    $periodStart = $ayFrom;
    $periodNum = 1;

    foreach ($vacations as $vac) {
        $vacFrom = $vac['date_from'];
        $vacTo   = $vac['date_to'];

        // Период до этих каникул
        $periodEnd = (new DateTime($vacFrom))->modify('-1 day')->format('Y-m-d');
        if ($periodEnd >= $periodStart) {
            $periods[] = [
                'from'  => $periodStart,
                'to'    => $periodEnd,
                'label' => get_period_label($periodNum, count($vacations)),
            ];
            $periodNum++;
        }
        // Следующий период начинается после каникул
        $periodStart = (new DateTime($vacTo))->modify('+1 day')->format('Y-m-d');
    }

    // Последний период после последних каникул до конца учебного года
    if ($periodStart <= $ayTo) {
        $periods[] = [
            'from'  => $periodStart,
            'to'    => $ayTo,
            'label' => get_period_label($periodNum, count($vacations)),
        ];
    }

    // Найти период, в который попадает текущая дата
    foreach ($periods as $p) {
        if ($date >= $p['from'] && $date <= $p['to']) {
            return $p;
        }
    }

    // Дата в каникулах — вернуть следующий период
    foreach ($periods as $p) {
        if ($p['from'] > $date) {
            return $p;
        }
    }

    // Fallback — последний период
    return end($periods) ?: ['from' => $ayFrom, 'to' => $ayTo, 'label' => 'Учебный год ' . $academicYear];
}

function get_period_label(int $num, int $totalVacations): string
{
    // 3 каникул = 4 четверти, 1 каникулы = 2 полугодия
    if ($totalVacations >= 3) {
        $names = [1 => 'I четверть', 2 => 'II четверть', 3 => 'III четверть', 4 => 'IV четверть'];
        return $names[$num] ?? "$num-й период";
    }
    if ($totalVacations == 2) {
        $names = [1 => 'I триместр', 2 => 'II триместр', 3 => 'III триместр'];
        return $names[$num] ?? "$num-й период";
    }
    if ($totalVacations == 1) {
        $names = [1 => 'I полугодие', 2 => 'II полугодие'];
        return $names[$num] ?? "$num-й период";
    }
    return "$num-й период";
}

/**
 * Получить все даты каникул для диапазона дат (для отображения в календаре).
 * Возвращает ассоциативный массив: date => label
 */
function get_vacation_days_for_range(string $from, string $to): array
{
    $s = db()->prepare(
        'SELECT * FROM vacations WHERE date_from <= ? AND date_to >= ? ORDER BY date_from'
    );
    $s->execute([$to, $from]);
    $vacations = $s->fetchAll();

    $days = [];
    foreach ($vacations as $vac) {
        $cur = new DateTime(max($vac['date_from'], $from));
        $end = new DateTime(min($vac['date_to'], $to));
        while ($cur <= $end) {
            $days[$cur->format('Y-m-d')] = $vac['label'];
            $cur->modify('+1 day');
        }
    }
    return $days;
}

// ─── Общественный контроль питания ───────────────────────────────────────────

function get_oc_monitoring(): array
{
    $row = db()->query('SELECT * FROM oc_monitoring WHERE id=1')->fetch();
    return $row ?: [
        'school_name' => '', 'report_date' => '', 's1_url' => '',
        's2_hotline' => '', 's2_chat_url' => '', 's2_forum_url' => '',
        's3_diet1_type' => '', 's3_diet1_url' => '',
        's3_diet2_type' => '', 's3_diet2_url' => '',
        's3_diet3_type' => '', 's3_diet3_url' => '',
        's3_diet4_type' => '', 's3_diet4_url' => '',
        's4_survey_url' => '', 's4_results_url' => '',
        's5_page_url' => '', 's5_materials_url' => '',
        's6_acts_url' => '', 's6_photos_url' => '',
        's7_waste_level' => 'none',
    ];
}

function save_oc_monitoring(array $d): void
{
    db()->prepare('
        INSERT INTO oc_monitoring (id, school_name, report_date, s1_url,
            s2_hotline, s2_chat_url, s2_forum_url,
            s3_diet1_type, s3_diet1_url, s3_diet2_type, s3_diet2_url,
            s3_diet3_type, s3_diet3_url, s3_diet4_type, s3_diet4_url,
            s4_survey_url, s4_results_url, s5_page_url, s5_materials_url,
            s6_acts_url, s6_photos_url, s7_waste_level)
        VALUES (1, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            school_name=VALUES(school_name), report_date=VALUES(report_date),
            s1_url=VALUES(s1_url),
            s2_hotline=VALUES(s2_hotline), s2_chat_url=VALUES(s2_chat_url),
            s2_forum_url=VALUES(s2_forum_url),
            s3_diet1_type=VALUES(s3_diet1_type), s3_diet1_url=VALUES(s3_diet1_url),
            s3_diet2_type=VALUES(s3_diet2_type), s3_diet2_url=VALUES(s3_diet2_url),
            s3_diet3_type=VALUES(s3_diet3_type), s3_diet3_url=VALUES(s3_diet3_url),
            s3_diet4_type=VALUES(s3_diet4_type), s3_diet4_url=VALUES(s3_diet4_url),
            s4_survey_url=VALUES(s4_survey_url), s4_results_url=VALUES(s4_results_url),
            s5_page_url=VALUES(s5_page_url), s5_materials_url=VALUES(s5_materials_url),
            s6_acts_url=VALUES(s6_acts_url), s6_photos_url=VALUES(s6_photos_url),
            s7_waste_level=VALUES(s7_waste_level)
    ')->execute([
        $d['school_name'] ?: null,
        $d['report_date']  ?: null,
        $d['s1_url'],
        $d['s2_hotline'], $d['s2_chat_url'], $d['s2_forum_url'],
        $d['s3_diet1_type'], $d['s3_diet1_url'],
        $d['s3_diet2_type'], $d['s3_diet2_url'],
        $d['s3_diet3_type'], $d['s3_diet3_url'],
        $d['s3_diet4_type'], $d['s3_diet4_url'],
        $d['s4_survey_url'], $d['s4_results_url'],
        $d['s5_page_url'], $d['s5_materials_url'],
        $d['s6_acts_url'], $d['s6_photos_url'],
        $d['s7_waste_level'],
    ]);
}
