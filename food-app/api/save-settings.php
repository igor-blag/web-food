<?php
require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/db.php';
require_auth();

header('Content-Type: application/json; charset=utf-8');

$input  = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? '';

if ($action === 'save_settings') {
    $org_name = trim($input['org_name'] ?? '');
    save_kitchen_settings($org_name);

    if (isset($input['tm_approver_position']) || isset($input['tm_approver_name'])) {
        save_tm_approver(
            trim($input['tm_approver_position'] ?? ''),
            trim($input['tm_approver_name'] ?? '')
        );
    }

    // Учебный год
    if (isset($input['academic_year_start'])) {
        save_academic_year_settings(
            $input['academic_year_start'] ?? '09-01',
            $input['academic_year_end'] ?? '05-31',
            !empty($input['reset_cycle_after_vacation']) ? 1 : 0
        );
    }

    $syncResults = [];
    $allDepts = get_all_departments();
    $deptCodeById = [];
    foreach ($allDepts as $dep) $deptCodeById[(int)$dep['id']] = $dep['code'];

    foreach ($input['departments'] ?? [] as $d) {
        $id = (int)($d['id'] ?? 0);
        if (!$id) continue;
        $data = [
            'is_enabled'       => !empty($d['is_enabled']) ? 1 : 0,
            'label'            => trim($d['label'] ?? ''),
            'label_short'      => trim($d['label_short'] ?? ''),
            'dept_name'        => trim($d['dept_name'] ?? ''),
            'workdays'         => $d['workdays'] ?? '1,2,3,4,5',
            'is_boarding'      => !empty($d['is_boarding']) ? 1 : 0,
            'publish_xlsx'     => !empty($d['publish_xlsx']) ? 1 : 0,
            'ignore_vacations' => !empty($d['ignore_vacations']) ? 1 : 0,
        ];
        if (isset($d['file_suffix'])) {
            $data['file_suffix'] = trim($d['file_suffix']);
        }
        save_department($id, $data);

        // Синхронизация шаблонов с заданной длиной цикла
        if (isset($d['cycle_length'])) {
            $desired = (int)$d['cycle_length'];
            $deptCode = $deptCodeById[$id] ?? null;
            if ($desired >= 0 && $deptCode) {
                $syncResults[$deptCode] = sync_templates_to_cycle_length($deptCode, $desired);
            }
        }
    }
    echo json_encode(['ok' => true, 'sync' => $syncResults]);
    exit;
}

if ($action === 'add_department') {
    $code   = preg_replace('/[^a-z0-9_]/', '', strtolower(trim($input['code'] ?? '')));
    $label  = trim($input['label'] ?? '');
    $suffix = trim($input['file_suffix'] ?? '');
    if (!$code || !$label) {
        echo json_encode(['ok' => false, 'error' => 'Укажите код и название']);
        exit;
    }
    if (get_department($code)) {
        echo json_encode(['ok' => false, 'error' => 'Отделение с кодом «' . $code . '» уже существует']);
        exit;
    }
    $id = add_department($code, $label, $suffix ?: '-' . $code);
    echo json_encode(['ok' => true, 'id' => $id]);
    exit;
}

if ($action === 'delete_department') {
    $id = (int)($input['id'] ?? 0);
    if ($id) delete_department($id);
    echo json_encode(['ok' => true]);
    exit;
}

// ─── Каникулы ───────────────────────────────────────────────

if ($action === 'get_vacations') {
    $year = trim($input['academic_year'] ?? '');
    if (!preg_match('/^\d{4}-\d{4}$/', $year)) {
        echo json_encode(['ok' => false, 'error' => 'Укажите учебный год']);
        exit;
    }
    echo json_encode(['ok' => true, 'vacations' => get_vacations($year)]);
    exit;
}

if ($action === 'add_vacation') {
    $year  = trim($input['academic_year'] ?? '');
    $label = trim($input['label'] ?? '');
    $from  = trim($input['date_from'] ?? '');
    $to    = trim($input['date_to'] ?? '');
    if (!$year || !$label || !$from || !$to) {
        echo json_encode(['ok' => false, 'error' => 'Заполните все поля']);
        exit;
    }
    $id = add_vacation($year, $label, $from, $to);
    echo json_encode(['ok' => true, 'id' => $id]);
    exit;
}

if ($action === 'update_vacation') {
    $id    = (int)($input['id'] ?? 0);
    $label = trim($input['label'] ?? '');
    $from  = trim($input['date_from'] ?? '');
    $to    = trim($input['date_to'] ?? '');
    if (!$id || !$label || !$from || !$to) {
        echo json_encode(['ok' => false, 'error' => 'Заполните все поля']);
        exit;
    }
    update_vacation($id, $label, $from, $to);
    echo json_encode(['ok' => true]);
    exit;
}

if ($action === 'delete_vacation') {
    $id = (int)($input['id'] ?? 0);
    if ($id) delete_vacation($id);
    echo json_encode(['ok' => true]);
    exit;
}

if ($action === 'fill_default_vacations') {
    $year = trim($input['academic_year'] ?? '');
    if (!preg_match('/^\d{4}-\d{4}$/', $year)) {
        echo json_encode(['ok' => false, 'error' => 'Укажите учебный год']);
        exit;
    }
    [$y1, $y2] = explode('-', $year);

    $defaults = [
        ['label' => 'Осенние каникулы',   'from' => "$y1-10-28", 'to' => "$y1-11-05"],
        ['label' => 'Зимние каникулы',     'from' => "$y1-12-28", 'to' => "$y2-01-08"],
        ['label' => 'Весенние каникулы',    'from' => "$y2-03-24", 'to' => "$y2-03-31"],
        ['label' => 'Летние каникулы',      'from' => "$y2-06-01", 'to' => "$y2-08-31"],
    ];

    $ids = [];
    foreach ($defaults as $d) {
        $ids[] = add_vacation($year, $d['label'], $d['from'], $d['to']);
    }
    echo json_encode(['ok' => true, 'ids' => $ids, 'vacations' => get_vacations($year)]);
    exit;
}

echo json_encode(['ok' => false, 'error' => 'Неизвестное действие']);
