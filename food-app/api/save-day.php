<?php
require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/db.php';
require_auth();

header('Content-Type: application/json; charset=utf-8');

$data   = json_decode(file_get_contents('php://input'), true) ?? [];
$action = $data['action'] ?? ($_POST['action'] ?? '');

$enabledDepts = get_enabled_departments();
$validTypes   = array_column($enabledDepts, 'code');
$deptByCode   = [];
foreach ($enabledDepts as $dep) $deptByCode[$dep['code']] = $dep;

try {
    if ($action === 'save') {
        $date    = $data['date']   ?? '';
        $type    = in_array($data['type'] ?? '', $validTypes) ? $data['type'] : 'sm';
        $school  = $data['school'] ?? null;
        $dept    = $data['dept']   ?? null;
        $is_school      = !empty($data['is_school']);
        $day_num        = isset($data['day_num']) ? (int)$data['day_num'] : 0;
        $is_cycle_start = !empty($data['is_cycle_start']) ? 1 : 0;

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            echo json_encode(['ok' => false, 'error' => 'Неверная дата']);
            exit;
        }

        if (!$is_school) {
            // Явный выходной — запись с template_id=null
            save_calendar_day($date, null, $school ?: null, $dept ?: null, $type, 0);
            echo json_encode(['ok' => true, 'template_id' => null, 'day_number' => null]);
            exit;
        }

        if ($day_num < 1) {
            echo json_encode(['ok' => false, 'error' => 'Укажите день меню']);
            exit;
        }

        // Найти template_id по day_num + type
        $templates = get_templates_ordered($type);
        if (!isset($templates[$day_num])) {
            echo json_encode(['ok' => false, 'error' => "Шаблон дня {$day_num} не найден для типа {$type}"]);
            exit;
        }
        $tpl_id = $templates[$day_num];
        save_calendar_day($date, $tpl_id, $school ?: null, $dept ?: null, $type, $is_cycle_start);

        // Генерация Excel (если публикация включена для отделения)
        $xlsPath = null;
        if (!empty($deptByCode[$type]['publish_xlsx'])) {
            require_once __DIR__ . '/../src/excel.php';
            $xlsPath = generate_menu_excel($date, $type);
        }

        // Получить обновлённую запись для ответа
        $entry = get_calendar_day($date, $type);
        echo json_encode([
            'ok'          => true,
            'template_id' => $tpl_id,
            'day_number'  => $day_num,
            'label'       => $entry['template_label'] ?? "День {$day_num}",
            'xls'         => $xlsPath ? basename($xlsPath) : null,
        ]);

    } elseif ($action === 'delete') {
        $date = $data['date'] ?? '';
        $type = in_array($data['type'] ?? '', $validTypes) ? $data['type'] : 'sm';

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            echo json_encode(['ok' => false, 'error' => 'Неверная дата']);
            exit;
        }

        delete_calendar_day($date, $type);
        echo json_encode(['ok' => true]);

    } elseif ($action === 'apply_cycle') {
        $date      = $data['date']      ?? '';
        $type      = in_array($data['type'] ?? '', $validTypes) ? $data['type'] : 'sm';
        $start_day = isset($data['start_day']) ? (int)$data['start_day'] : 1;
        $school    = $data['school']   ?? null;
        $dept      = $data['dept']     ?? null;
        $end_date  = $data['end_date'] ?? null;

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            echo json_encode(['ok' => false, 'error' => 'Неверная дата']);
            exit;
        }
        if ($end_date !== null && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $end_date)) {
            $end_date = null;
        }

        $workdays = isset($deptByCode[$type]) ? get_workdays($type) : [1,2,3,4,5];
        $count = assign_cycle($date, $start_day, $type, $school ?: null, $dept ?: null, $end_date, $workdays);
        echo json_encode(['ok' => true, 'count' => $count]);

    } elseif ($action === 'bulk_save') {
        $type = in_array($data['type'] ?? '', $validTypes) ? $data['type'] : 'sm';
        $days = is_array($data['days'] ?? null) ? $data['days'] : [];
        bulk_save_calendar($days, $type);
        echo json_encode(['ok' => true, 'saved' => count($days)]);

    } elseif ($action === 'recalc_period') {
        $type = in_array($data['type'] ?? '', $validTypes) ? $data['type'] : 'sm';
        $dept = $deptByCode[$type] ?? null;
        $school = $data['school'] ?? null;
        $deptName = $data['dept'] ?? null;

        $period = get_current_period($type);
        $templates = get_templates_ordered($type);
        $cycleLen = count($templates);
        if ($cycleLen === 0) {
            echo json_encode(['ok' => false, 'error' => 'Нет шаблонов для типа ' . $type]);
            exit;
        }

        $keys = array_keys($templates);
        $workdays = $dept ? get_workdays($type) : [1,2,3,4,5];
        $aySettings = get_academic_year_settings();
        $resetAfterVac = (bool)$aySettings['reset_cycle_after_vacation'];

        // Получить каникулы для определения точек сброса цикла
        $academicYear = get_academic_year_for_date($period['from']);
        $vacations = get_vacations($academicYear);
        $vacationDays = get_vacation_days_for_range($period['from'], $period['to']);

        // Определить начальный день цикла (продолжение с предыдущего)
        $stmt = db()->prepare(
            'SELECT t.day_number FROM calendar c
             LEFT JOIN menu_templates t ON t.id = c.template_id
             WHERE c.date < ? AND c.school_type = ? AND c.template_id IS NOT NULL
             ORDER BY c.date DESC LIMIT 1'
        );
        $stmt->execute([$period['from'], $type]);
        $lastBefore = $stmt->fetch();
        $startIdx = 0;
        if ($lastBefore && $lastBefore['day_number'] && !$resetAfterVac) {
            $lastDayNum = (int)$lastBefore['day_number'];
            $pos = array_search($lastDayNum, $keys);
            if ($pos !== false) {
                $startIdx = ($pos + 1) % $cycleLen;
            }
        }

        $cur = new DateTime($period['from']);
        $end = new DateTime($period['to']);
        $idx = $startIdx;
        $days = [];

        while ($cur <= $end) {
            $dateStr = $cur->format('Y-m-d');
            $wday = (int)$cur->format('N');
            $isWorkday = in_array($wday, $workdays, true);
            $isVacation = isset($vacationDays[$dateStr]);

            if ($isVacation) {
                // Пропускаем каникулы
            } elseif ($isWorkday) {
                $dayNum = $keys[$idx % $cycleLen];
                $days[] = [
                    'date'           => $dateStr,
                    'day_num'        => $dayNum,
                    'school'         => $school,
                    'dept'           => $deptName,
                    'is_cycle_start' => $idx === $startIdx ? 1 : 0,
                ];
                $idx++;
            }
            $cur->modify('+1 day');
        }

        if (!empty($days)) {
            bulk_save_calendar($days, $type);
        }

        echo json_encode([
            'ok'     => true,
            'count'  => count($days),
            'period' => $period,
        ]);

    } elseif ($action === 'generate_files') {
        $type  = in_array($data['type'] ?? '', $validTypes) ? $data['type'] : 'sm';
        $year  = isset($data['year'])  ? (int)$data['year']  : (int)date('Y');
        $month = isset($data['month']) ? (int)$data['month'] : (int)date('n');

        if (!isset($deptByCode[$type]) || empty($deptByCode[$type]['publish_xlsx'])) {
            echo json_encode(['ok' => false, 'error' => 'Публикация xlsx отключена для этого отделения']);
            exit;
        }

        require_once __DIR__ . '/../src/excel.php';
        $generated = [];
        $daysInMonth = (int)(new DateTimeImmutable("$year-$month-01"))->format('t');
        for ($d = 1; $d <= $daysInMonth; $d++) {
            $dateStr = sprintf('%04d-%02d-%02d', $year, $month, $d);
            $path = generate_menu_excel($dateStr, $type);
            if ($path) {
                $generated[] = basename($path);
            }
        }
        // Генерация kp-файла
        require_once __DIR__ . '/../src/kp_generator.php';
        generate_kp_excel($year, $type);

        echo json_encode(['ok' => true, 'count' => count($generated), 'files' => $generated]);

    } else {
        echo json_encode(['ok' => false, 'error' => 'Неизвестное действие']);
    }
} catch (Exception $e) {
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
