<?php
require_once __DIR__ . '/src/auth.php';
require_once __DIR__ . '/src/db.php';
require_auth();

$enabledDepts = get_enabled_departments();
$validTypes   = array_column($enabledDepts, 'code');
$typeLabels   = array_combine(
    array_column($enabledDepts, 'code'),
    array_column($enabledDepts, 'label_short')
);
$deptMap = [];
foreach ($enabledDepts as $dep) $deptMap[$dep['code']] = $dep;

$type = in_array($_GET['type'] ?? '', $validTypes) ? $_GET['type'] : ($validTypes[0] ?? 'sm');
$curDept  = $deptMap[$type] ?? null;
$orgName  = get_org_name();
$deptName = $curDept['dept_name'] ?? '';

$today = new DateTime();
$year  = (int)($_GET['y'] ?? $today->format('Y'));
$month = (int)($_GET['m'] ?? $today->format('n'));
$year  = max(2020, min(2035, $year));
$month = max(1, min(12, $month));

$calData   = get_calendar_month($year, $month, $type);
$firstDay  = new DateTime(sprintf('%04d-%02d-01', $year, $month));
$lastDay   = (int)$firstDay->format('t');
$startWday = (int)$firstDay->format('N');

$prevDt = (clone $firstDay)->modify('-1 month');
$nextDt = (clone $firstDay)->modify('+1 month');

$todayStr = $today->format('Y-m-d');
$monthRu  = ['','Январь','Февраль','Март','Апрель','Май','Июнь',
              'Июль','Август','Сентябрь','Октябрь','Ноябрь','Декабрь'];
$weekdays = ['Пн','Вт','Ср','Чт','Пт','Сб','Вс'];
$suffix   = $curDept ? $curDept['file_suffix'] : '';

// Карта существующих xlsx-файлов для текущего месяца/отделения
$existingFiles = [];
$fPattern = FILES_DIR . sprintf('%04d-%02d-*%s.xlsx', $year, $month, $suffix);
$otherSuffixes = [];
foreach ($enabledDepts as $dep) {
    if ($dep['file_suffix'] !== '' && $dep['file_suffix'] !== $suffix) {
        $otherSuffixes[] = $dep['file_suffix'];
    }
}
foreach (glob($fPattern) ?: [] as $f) {
    $nameNoExt = basename($f, '.xlsx');
    if ($suffix === '') {
        $skip = false;
        foreach ($otherSuffixes as $os) {
            if (str_ends_with($nameNoExt, $os)) { $skip = true; break; }
        }
        if ($skip) continue;
    }
    $existingFiles[substr(basename($f), 0, 10)] = true;
}

$cycleLen    = get_cycle_length($type);
$tplsOrdered = get_templates_ordered($type);
$templatesMap = [];
foreach ($tplsOrdered as $dn => $tid) {
    $templatesMap[$tid] = $dn;
}

$tplsAll   = get_templates($type);
$dayNumToLabel = [];
foreach ($tplsAll as $t) {
    $dayNumToLabel[(int)$t['day_number']] = $t['label'];
}
$dayNumKeys = array_values(array_keys($tplsOrdered));

$gaps = [];
for ($i = 0; $i < count($dayNumKeys) - 1; $i++) {
    for ($g = $dayNumKeys[$i] + 1; $g < $dayNumKeys[$i + 1]; $g++) {
        $gaps[] = $g;
    }
}

// Начальный день цикла: продолжение с последней записи предыдущего периода
$stmt = db()->prepare(
    'SELECT t.day_number FROM calendar c
     LEFT JOIN menu_templates t ON t.id = c.template_id
     WHERE c.date < ? AND c.school_type = ? AND c.template_id IS NOT NULL
     ORDER BY c.date DESC LIMIT 1'
);
$stmt->execute([$firstDay->format('Y-m-d'), $type]);
$lastBefore = $stmt->fetch();
$defaultStartDay = $dayNumKeys[0] ?? 1;
if ($lastBefore && $lastBefore['day_number']) {
    $lastDayNum = (int)$lastBefore['day_number'];
    $pos = array_search($lastDayNum, $dayNumKeys);
    if ($pos !== false) {
        $defaultStartDay = $dayNumKeys[($pos + 1) % count($dayNumKeys)];
    }
}

$curWorkdays = $curDept ? explode(',', $curDept['workdays']) : ['1','2','3','4','5'];

// Каникулы за месяц
$monthFrom = sprintf('%04d-%02d-01', $year, $month);
$monthTo   = date('Y-m-t', strtotime($monthFrom));
$vacationDays = get_vacation_days_for_range($monthFrom, $monthTo);
$curPeriod = get_current_period($type, $todayStr);
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Календарь — Меню</title>
    <link rel="stylesheet" href="assets/style.css">
    <style>
        /* ── Ячейки ─────────────────────────────────────────── */
        .cal-cell { cursor: pointer; user-select: none; }
        .cal-cell.cycle-start .cal-day::after {
            content: '●';
            font-size: 0.55rem;
            color: var(--orange);
            margin-left: 3px;
            vertical-align: middle;
        }
        .cal-cell[data-has-file="1"] .cal-day::before {
            content: '';
            display: inline-block;
            width: 5px;
            height: 5px;
            background: var(--success);
            border-radius: 50%;
            margin-right: 2px;
            vertical-align: middle;
        }
        .cal-cell.selected {
            outline: 2px solid var(--orange);
            outline-offset: -2px;
        }
        .cal-cell.vacation { background: #f3eef8; }
        .cal-cell .cal-vacation { font-size: 0.72rem; color: #7a5c9a; line-height: 1.2; }
        .tab-bar + .panel { border-top: none; }

        /* ── Инфо-строка цикла ─────────────────────────────── */
        .cycle-info {
            font-size: 0.78rem;
            color: var(--muted);
            padding: 6px 0 0;
        }
        .cycle-info a { font-size: 0.78rem; }

        /* ── Попап дня ─────────────────────────────────────── */
        .day-popup {
            position: fixed;
            z-index: 500;
            background: #fff;
            border: 1px solid var(--border-light);
            border-top: 3px solid var(--orange);
            padding: 14px 18px;
            min-width: 200px;
            max-width: 280px;
            box-shadow: 0 4px 16px rgba(0,0,0,.15);
        }
        .day-popup-title {
            font-size: 0.88rem;
            font-weight: bold;
            color: var(--text);
            margin-bottom: 4px;
        }
        .day-popup-status {
            font-size: 0.82rem;
            color: var(--muted);
            margin-bottom: 12px;
        }
        .day-popup-status strong {
            color: var(--orange);
        }
        .day-popup-actions {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }
        .day-popup-actions .btn {
            width: 100%;
            text-align: center;
        }

        /* ── Модалка файлов ─────────────────────────────────── */
        .fm-overlay { position:fixed; inset:0; background:rgba(0,0,0,.45); z-index:1000; display:flex; align-items:center; justify-content:center; }
        .fm-dialog  { background:#fff; border-top:3px solid var(--orange); width:95%; max-width:700px; max-height:80vh; display:flex; flex-direction:column; }
        .fm-header  { display:flex; justify-content:space-between; align-items:center; padding:14px 20px; border-bottom:1px solid var(--border-light); }
        .fm-header span { font-weight:bold; font-size:.95rem; }
        .fm-close   { background:none; border:none; font-size:1.4rem; cursor:pointer; color:var(--muted); }
        .fm-close:hover { color:var(--text); }
        .fm-tabs    { display:flex; gap:0; border-bottom:1px solid var(--border-light); padding:0 20px; }
        .fm-tab     { padding:8px 16px; cursor:pointer; border-bottom:2px solid transparent; font-size:.85rem; color:var(--muted); background:none; border-top:none; border-left:none; border-right:none; }
        .fm-tab:hover { color:var(--text); }
        .fm-tab.active { border-bottom-color:var(--orange); color:var(--orange); font-weight:bold; }
        .fm-body    { padding:16px 20px; overflow-y:auto; flex:1; }
        .fm-table   { width:100%; border-collapse:collapse; font-size:.85rem; }
        .fm-table th, .fm-table td { padding:6px 8px; text-align:left; border-bottom:1px solid var(--border-light); }
        .fm-table th { color:var(--muted); font-weight:normal; text-transform:uppercase; font-size:.75rem; letter-spacing:.05em; }
        .fm-table .fm-del { color:var(--error); cursor:pointer; background:none; border:none; font-size:.8rem; }
        .fm-table .fm-del:hover { text-decoration:underline; }
        .fm-empty   { color:var(--muted); text-align:center; padding:24px; font-size:.9rem; }
        .fm-footer  { display:flex; justify-content:space-between; align-items:center; padding:10px 20px; border-top:1px solid var(--border-light); }
    </style>
</head>
<body>
<?php include __DIR__ . '/src/header.php'; ?>
<div class="container" style="max-width:960px">
    <div class="flex items-center justify-between mb-2">
        <h1 class="page-title" style="border:none;margin:0">Календарь меню</h1>
        <div class="flex gap-2">
            <button type="button" id="btn-recalc" class="btn btn-outline btn-sm">Пересчитать</button>
            <?php if (!empty($curDept['publish_xlsx'])): ?>
            <button type="button" id="btn-gen-files" class="btn btn-primary btn-sm">Создать файлы</button>
            <button type="button" id="btn-files" class="btn btn-outline btn-sm">Файлы</button>
            <?php endif; ?>
            <a href="templates.php?type=<?= $type ?>" class="btn btn-outline btn-sm">Шаблоны</a>
        </div>
    </div>

    <!-- Табы типов школ -->
    <div class="tab-bar">
        <?php foreach ($typeLabels as $t => $label): ?>
        <a href="calendar.php?type=<?= $t ?>&y=<?= $year ?>&m=<?= $month ?>"
           class="tab-item<?= $type === $t ? ' active' : '' ?>"><?= $label ?></a>
        <?php endforeach; ?>
    </div>

    <div class="panel">
        <!-- Инфо цикла -->
        <div class="cycle-info">
            <?php if ($cycleLen === 0): ?>
                <span style="color:var(--error)">Шаблоны не созданы.</span>
                <a href="templates.php?type=<?= $type ?>">Добавить →</a>
            <?php elseif (!empty($gaps)): ?>
                Цикл: <?= $cycleLen ?> дн. (<?= $dayNumKeys[0] ?>–<?= end($dayNumKeys) ?>)
                · <span style="color:var(--error)">⚠ Пропущены №<?= implode(', №', $gaps) ?>.</span>
                <a href="templates.php?type=<?= $type ?>">Исправить →</a>
            <?php else: ?>
                Цикл: <?= $cycleLen ?> дн. (<?= $dayNumKeys[0] ?>–<?= end($dayNumKeys) ?>)
                · Начало: день <?= (int)$defaultStartDay ?>
                <?php if (!empty($curPeriod['label'])): ?>
                · <?= htmlspecialchars($curPeriod['label']) ?>
                  (<?= date('d.m', strtotime($curPeriod['from'])) ?> – <?= date('d.m', strtotime($curPeriod['to'])) ?>)
                <?php endif; ?>
            <?php endif; ?>
        </div>

        <div class="cal-nav">
            <a href="calendar.php?type=<?= $type ?>&y=<?= $prevDt->format('Y') ?>&m=<?= $prevDt->format('n') ?>"
               class="btn btn-dark btn-sm">&larr;</a>
            <h2><?= $monthRu[$month] ?> <?= $year ?></h2>
            <a href="calendar.php?type=<?= $type ?>&y=<?= $nextDt->format('Y') ?>&m=<?= $nextDt->format('n') ?>"
               class="btn btn-dark btn-sm">&rarr;</a>
        </div>

        <div class="cal-grid" id="cal-grid">
            <?php foreach ($weekdays as $wd): ?>
                <div class="cal-head"><?= $wd ?></div>
            <?php endforeach; ?>

            <?php
            for ($i = 1; $i < $startWday; $i++) {
                echo '<div class="cal-cell empty"></div>';
            }
            for ($d = 1; $d <= $lastDay; $d++):
                $dateStr = sprintf('%04d-%02d-%02d', $year, $month, $d);
                $wday    = (int)(new DateTime($dateStr))->format('N');
                $entry   = $calData[$dateStr] ?? null;
                $isToday = ($dateStr === $todayStr);

                $isVacation = isset($vacationDays[$dateStr]);
                $vacLabel   = $isVacation ? $vacationDays[$dateStr] : '';

                $cls = 'cal-cell';
                if ($wday >= 6) $cls .= ' weekend';
                if ($isVacation) $cls .= ' vacation';
                elseif ($entry && $entry['template_id'] === null) $cls .= ' holiday';
                elseif ($entry && $entry['template_id'])          $cls .= ' has-menu';
                if ($isToday) $cls .= ' today';
                if ($entry && !empty($entry['is_cycle_start'])) $cls .= ' cycle-start';

                $entryJson = $entry ? json_encode([
                    'template_id'    => $entry['template_id'],
                    'day_number'     => $entry['template_id'] ? ($templatesMap[(int)$entry['template_id']] ?? null) : null,
                    'label'          => $entry['template_label'] ?? null,
                    'school'         => $entry['school'] ?? '',
                    'dept'           => $entry['dept']   ?? '',
                    'is_cycle_start' => (int)($entry['is_cycle_start'] ?? 0),
                ], JSON_UNESCAPED_UNICODE) : 'null';
            ?>
            <div class="<?= $cls ?>"
                 data-date="<?= $dateStr ?>"
                 data-entry="<?= htmlspecialchars($entryJson, ENT_QUOTES) ?>"
                 <?= $isVacation ? 'data-vacation="' . htmlspecialchars($vacLabel, ENT_QUOTES) . '"' : '' ?>
                 <?= isset($existingFiles[$dateStr]) ? 'data-has-file="1"' : '' ?>>
                <div class="cal-day"><?= $d ?></div>
                <?php if ($isVacation): ?>
                    <div class="cal-vacation">каникулы</div>
                <?php elseif ($entry && $entry['template_id']): ?>
                    <div class="cal-label"><?= htmlspecialchars($entry['template_label']) ?></div>
                <?php elseif ($entry && $entry['template_id'] === null): ?>
                    <div class="cal-no-school">выходной</div>
                <?php endif; ?>
            </div>
            <?php endfor; ?>

            <?php
            $endWday = (int)(new DateTime(sprintf('%04d-%02d-%02d', $year, $month, $lastDay)))->format('N');
            for ($i = $endWday + 1; $i <= 7; $i++) {
                echo '<div class="cal-cell empty"></div>';
            }
            ?>
        </div>

        <div class="legend" style="margin-top:12px">
            <div class="legend-item"><div class="legend-dot has-menu"></div> Меню</div>
            <div class="legend-item"><div class="legend-dot holiday"></div> Выходной</div>
            <div class="legend-item"><div class="legend-dot" style="background:#c4a8e0"></div> Каникулы</div>
            <div class="legend-item"><div class="legend-dot no-menu"></div> Не задан</div>
        </div>
    </div>
</div>

<!-- Попап дня -->
<div id="day-popup" class="day-popup" style="display:none">
    <div class="day-popup-title" id="popup-title"></div>
    <div class="day-popup-status" id="popup-status"></div>
    <div class="day-popup-actions" id="popup-actions"></div>
</div>

<!-- Модалка файлов -->
<div id="files-modal" class="fm-overlay" style="display:none">
    <div class="fm-dialog">
        <div class="fm-header">
            <span>Файлы — <?= $monthRu[$month] ?> <?= $year ?></span>
            <button class="fm-close" id="fm-close-btn">&times;</button>
        </div>
        <div id="fm-tabs" class="fm-tabs"></div>
        <div id="fm-body" class="fm-body"></div>
        <div id="fm-footer" class="fm-footer" style="display:none">
            <label style="font-size:.82rem;color:var(--muted);cursor:pointer">
                <input type="checkbox" id="fm-check-all"> Выбрать все
            </label>
            <button class="btn btn-danger btn-sm" id="fm-del-selected" disabled>Удалить выбранные</button>
        </div>
    </div>
</div>

<script>
(function () {
    'use strict';

    var curType      = <?= json_encode($type) ?>;
    var cycleLen     = <?= (int)$cycleLen ?>;
    var dayNumKeys   = <?= json_encode($dayNumKeys) ?>;
    var dayNumToTplId = <?= json_encode($tplsOrdered, JSON_UNESCAPED_UNICODE) ?>;
    var dayNumToLabel = <?= json_encode($dayNumToLabel, JSON_UNESCAPED_UNICODE) ?>;
    var curMonth     = <?= $month ?>;
    var curYear      = <?= $year ?>;
    var orgName      = <?= json_encode($orgName) ?>;
    var deptName     = <?= json_encode($deptName) ?>;
    var defaultStartDay = <?= (int)$defaultStartDay ?>;
    var defaultWorkdays = <?= json_encode(array_map('intval', $curWorkdays)) ?>;
    var publishXlsx  = <?= !empty($curDept['publish_xlsx']) ? 'true' : 'false' ?>;
    var vacationDays = <?= json_encode(array_keys($vacationDays)) ?>;

    // ── Вспомогательные функции ──────────────────────────────────
    function getSortedCells() {
        return Array.from(document.querySelectorAll('.cal-cell[data-date]:not(.empty)'))
            .sort(function (a, b) { return a.dataset.date.localeCompare(b.dataset.date); });
    }

    function getCellWday(cell) {
        var d = new Date(cell.dataset.date + 'T00:00:00');
        var w = d.getDay();
        return w === 0 ? 7 : w;
    }

    function getCellEntry(cell) {
        return cell.dataset.entry !== 'null' ? JSON.parse(cell.dataset.entry) : null;
    }

    function escHtml(s) {
        return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
    }

    // ── AJAX ─────────────────────────────────────────────────────
    function apiPost(payload, callback) {
        var xhr = new XMLHttpRequest();
        xhr.open('POST', 'api/save-day.php');
        xhr.setRequestHeader('Content-Type', 'application/json');
        xhr.onload = function () {
            try { callback(JSON.parse(xhr.responseText)); }
            catch (e) { callback({ ok: false, error: 'Ошибка ответа' }); }
        };
        xhr.onerror = function () { callback({ ok: false, error: 'Сетевая ошибка' }); };
        xhr.send(JSON.stringify(payload));
    }

    // ── Обновление ячейки в DOM ──────────────────────────────────
    function updateCellDOM(dateStr, data) {
        var cell = document.querySelector('.cal-cell[data-date="' + dateStr + '"]');
        if (!cell) return;

        cell.dataset.entry = data ? JSON.stringify(data) : 'null';
        cell.classList.remove('has-menu', 'holiday', 'cycle-start');

        if (data && data.template_id) {
            cell.classList.add('has-menu');
        } else if (data && data.template_id === null) {
            cell.classList.add('holiday');
        }
        if (data && data.is_cycle_start) cell.classList.add('cycle-start');

        var dayNum = cell.querySelector('.cal-day') ? cell.querySelector('.cal-day').textContent : '';
        var html = '<div class="cal-day">' + dayNum + '</div>';

        if (data && data.template_id) {
            html += '<div class="cal-label">' + escHtml(data.label || '') + '</div>';
        } else if (data && data.template_id === null) {
            html += '<div class="cal-no-school">выходной</div>';
        }

        cell.innerHTML = html;
    }

    // ── Пересчёт цикла ───────────────────────────────────────────
    function recalcCycle() {
        if (cycleLen === 0) return;

        var cells = getSortedCells();
        var days = [];
        var startPos = dayNumKeys.indexOf(defaultStartDay);
        if (startPos === -1) startPos = 0;
        var idx = startPos;

        cells.forEach(function (cell) {
            var date = cell.dataset.date;
            var wday = getCellWday(cell);
            var entry = getCellEntry(cell);
            var isWorkday = defaultWorkdays.indexOf(wday) !== -1;
            var isVacation = vacationDays.indexOf(date) !== -1;
            var isExplicitHoliday = entry !== null && entry.template_id === null;

            if (isVacation || isExplicitHoliday) {
                // Пропускаем каникулы и явные выходные
            } else if (isWorkday) {
                var dayNum = dayNumKeys[idx % cycleLen];
                days.push({
                    date: date,
                    day_num: dayNum,
                    school: orgName,
                    dept: deptName,
                    is_cycle_start: idx === startPos ? 1 : 0
                });
                idx++;
            }
        });

        if (days.length === 0) return;

        apiPost({ action: 'bulk_save', type: curType, days: days }, function (res) {
            if (!res.ok) return;
            days.forEach(function (d) {
                if (d.day_num === -1) {
                    updateCellDOM(d.date, null);
                } else {
                    updateCellDOM(d.date, {
                        template_id: dayNumToTplId[d.day_num] || null,
                        day_number: d.day_num,
                        label: dayNumToLabel[d.day_num] || ('День ' + d.day_num),
                        school: d.school || '',
                        dept: d.dept || '',
                        is_cycle_start: d.is_cycle_start
                    });
                }
            });
            closePopup();
        });
    }

    // ── Пересчёт от выбранной даты ──────────────────────────────
    function recalcFrom(startDate) {
        if (cycleLen === 0) return;
        var sel = document.getElementById('popup-day-select');
        var startDayNum = sel ? parseInt(sel.value) : defaultStartDay;

        var cells = getSortedCells();
        var days = [];
        var startPos = dayNumKeys.indexOf(startDayNum);
        if (startPos === -1) startPos = 0;
        var idx = startPos;
        var started = false;

        cells.forEach(function (cell) {
            var date = cell.dataset.date;
            if (!started) {
                if (date < startDate) return;
                started = true;
            }
            var wday = getCellWday(cell);
            var entry = getCellEntry(cell);
            var isWorkday = defaultWorkdays.indexOf(wday) !== -1;
            var isVacation = vacationDays.indexOf(date) !== -1;
            var isExplicitHoliday = entry !== null && entry.template_id === null;

            if (isVacation || isExplicitHoliday) {
                // Пропускаем каникулы и явные выходные
            } else if (isWorkday) {
                var dayNum = dayNumKeys[idx % cycleLen];
                days.push({
                    date: date,
                    day_num: dayNum,
                    school: orgName,
                    dept: deptName,
                    is_cycle_start: idx === startPos ? 1 : 0
                });
                idx++;
            }
        });

        if (days.length === 0) return;

        apiPost({ action: 'bulk_save', type: curType, days: days }, function (res) {
            if (!res.ok) return;
            days.forEach(function (d) {
                updateCellDOM(d.date, {
                    template_id: dayNumToTplId[d.day_num] || null,
                    day_number: d.day_num,
                    label: dayNumToLabel[d.day_num] || ('День ' + d.day_num),
                    school: d.school || '',
                    dept: d.dept || '',
                    is_cycle_start: d.is_cycle_start
                });
            });
            closePopup();
        });
    }

    // ── Переключение статуса дня ─────────────────────────────────
    function toggleDayStatus(dateStr, makeWorkday) {
        if (makeWorkday) {
            // Если день явно помечен выходным — сначала удалить запись, потом пересчитать
            var cell = document.querySelector('.cal-cell[data-date="' + dateStr + '"]');
            var entry = cell ? getCellEntry(cell) : null;
            if (entry && entry.template_id === null) {
                apiPost({
                    action: 'bulk_save', type: curType,
                    days: [{ date: dateStr, day_num: -1 }]
                }, function (res) {
                    if (!res.ok) return;
                    updateCellDOM(dateStr, null);
                    closePopup();
                    recalcCycle();
                });
            } else {
                recalcCycle();
            }
            return;
        }
        apiPost({
            action: 'bulk_save',
            type: curType,
            days: [{ date: dateStr, day_num: 0, school: orgName, dept: deptName }]
        }, function (res) {
            if (!res.ok) return;
            updateCellDOM(dateStr, {
                template_id: null, day_number: null, label: null,
                school: orgName, dept: deptName, is_cycle_start: 0
            });
            closePopup();
        });
    }

    // ── Попап дня ────────────────────────────────────────────────
    var popupOpen = false;

    function openPopup(cell) {
        var date = cell.dataset.date;
        var entry = getCellEntry(cell);

        var prev = document.querySelector('.cal-cell.selected');
        if (prev) prev.classList.remove('selected');
        cell.classList.add('selected');

        // Заголовок
        var parts = date.split('-');
        var dt = new Date(date + 'T00:00:00');
        var wday = dt.getDay() === 0 ? 7 : dt.getDay();
        var wdayNames = ['','Пн','Вт','Ср','Чт','Пт','Сб','Вс'];
        var monNames = ['','января','февраля','марта','апреля','мая','июня',
                         'июля','августа','сентября','октября','ноября','декабря'];

        document.getElementById('popup-title').textContent =
            wdayNames[wday] + ', ' + parseInt(parts[2]) + ' ' + monNames[parseInt(parts[1])] + ' ' + parts[0];

        // Статус
        var statusEl = document.getElementById('popup-status');
        var vacLabel = cell.dataset.vacation || '';

        if (vacLabel) {
            statusEl.innerHTML = '<span style="color:#7a5c9a">Каникулы — ' + escHtml(vacLabel) + '</span>';
        } else if (entry && entry.template_id) {
            statusEl.innerHTML = 'День меню: <strong>' + (entry.day_number || '?') + '</strong>' +
                (entry.label ? ' — ' + escHtml(entry.label) : '');
        } else if (entry && entry.template_id === null) {
            statusEl.textContent = 'Выходной';
        } else {
            statusEl.textContent = 'Не назначен';
        }

        // Действия
        var actionsEl = document.getElementById('popup-actions');
        actionsEl.innerHTML = '';

        if (!vacLabel) {
            var isWorkday = entry && entry.template_id;

            var toggleBtn = document.createElement('button');
            toggleBtn.className = 'btn btn-outline btn-sm';
            toggleBtn.textContent = isWorkday ? 'Сделать выходным' : 'Сделать рабочим';
            toggleBtn.addEventListener('click', function (ev) {
                ev.stopPropagation();
                toggleDayStatus(date, !isWorkday);
            });
            actionsEl.appendChild(toggleBtn);

            // Селектор дня + пересчёт от этой даты
            if (cycleLen > 0) {
                var curDayNum = (entry && entry.day_number) ? entry.day_number : defaultStartDay;
                var row = document.createElement('div');
                row.style.cssText = 'display:flex;gap:6px;align-items:center;margin-top:2px';

                var sel = document.createElement('select');
                sel.id = 'popup-day-select';
                sel.style.cssText = 'flex:1;padding:4px 8px;font-family:Georgia,serif;font-size:0.88rem;border:1px solid var(--border-light);border-radius:var(--radius)';
                for (var i = 0; i < dayNumKeys.length; i++) {
                    var opt = document.createElement('option');
                    opt.value = dayNumKeys[i];
                    opt.textContent = 'День ' + dayNumKeys[i];
                    if (dayNumKeys[i] === curDayNum) opt.selected = true;
                    sel.appendChild(opt);
                }
                row.appendChild(sel);

                var recalcBtn = document.createElement('button');
                recalcBtn.className = 'btn btn-primary btn-sm';
                recalcBtn.textContent = 'Пересчитать';
                recalcBtn.addEventListener('click', function (ev) {
                    ev.stopPropagation();
                    recalcFrom(date);
                });
                row.appendChild(recalcBtn);

                actionsEl.appendChild(row);
            }
        }

        // Позиционирование
        var popup = document.getElementById('day-popup');
        var rect = cell.getBoundingClientRect();
        var popW = 240;
        var popH = 160;

        var left = rect.right + 8;
        var top = rect.top;

        if (left + popW > window.innerWidth - 16) {
            left = rect.left - popW - 8;
        }
        if (left < 16) {
            left = rect.left;
            top = rect.bottom + 8;
        }
        if (top + popH > window.innerHeight - 16) {
            top = window.innerHeight - popH - 16;
        }
        if (top < 16) top = 16;

        popup.style.left = left + 'px';
        popup.style.top = top + 'px';
        popup.style.display = '';
        popupOpen = true;
    }

    function closePopup() {
        document.getElementById('day-popup').style.display = 'none';
        popupOpen = false;
        var prev = document.querySelector('.cal-cell.selected');
        if (prev) prev.classList.remove('selected');
    }

    // ── Клики по сетке ──────────────────────────────────────────
    document.getElementById('cal-grid').addEventListener('click', function (e) {
        var cell = e.target.closest('.cal-cell:not(.empty)');
        if (!cell) return;
        openPopup(cell);
    });

    // Закрытие попапа по клику снаружи
    document.addEventListener('click', function (e) {
        if (!popupOpen) return;
        var popup = document.getElementById('day-popup');
        if (popup.contains(e.target)) return;
        if (e.target.closest('.cal-cell:not(.empty)')) return;
        closePopup();
    });

    // ── Кнопка «Пересчитать» ─────────────────────────────────────
    document.getElementById('btn-recalc').addEventListener('click', function () {
        recalcCycle();
    });

    // ── Генерация файлов ────────────────────────────────────
    var btnGen = document.getElementById('btn-gen-files');
    if (btnGen) {
        btnGen.addEventListener('click', function () {
            btnGen.disabled = true;
            btnGen.textContent = 'Генерация…';
            apiPost({
                action: 'generate_files',
                type: curType,
                year: curYear,
                month: curMonth
            }, function (res) {
                if (res.ok) {
                    location.reload();
                } else {
                    btnGen.disabled = false;
                    btnGen.textContent = 'Создать файлы';
                }
            });
        });
    }

    // ── Модальное окно «Файлы» ──────────────────────────────
    var fmData = {};
    var fmActiveTab = '';
    var deptLabels = <?= json_encode(array_combine(
        array_column($enabledDepts, 'code'),
        array_column($enabledDepts, 'label_short')
    ), JSON_UNESCAPED_UNICODE) ?>;

    function fmOpen() {
        var modal = document.getElementById('files-modal');
        modal.style.display = '';
        document.getElementById('fm-body').innerHTML = '<div class="fm-empty">Загрузка…</div>';
        document.getElementById('fm-tabs').innerHTML = '';

        var xhr = new XMLHttpRequest();
        xhr.open('POST', 'api/files.php');
        xhr.setRequestHeader('Content-Type', 'application/json');
        xhr.onload = function () {
            try {
                var res = JSON.parse(xhr.responseText);
                if (res.ok) {
                    fmData = res.files;
                    fmRenderTabs();
                } else {
                    document.getElementById('fm-body').innerHTML = '<div class="fm-empty">Ошибка: ' + (res.error || '') + '</div>';
                }
            } catch (e) {
                document.getElementById('fm-body').innerHTML = '<div class="fm-empty">Ошибка ответа</div>';
            }
        };
        xhr.onerror = function () {
            document.getElementById('fm-body').innerHTML = '<div class="fm-empty">Сетевая ошибка</div>';
        };
        xhr.send(JSON.stringify({ action: 'list', year: curYear, month: curMonth }));
    }

    function fmClose() {
        document.getElementById('files-modal').style.display = 'none';
    }

    function fmRenderTabs() {
        var tabs = document.getElementById('fm-tabs');
        var codes = Object.keys(fmData);
        if (codes.length === 0) {
            tabs.innerHTML = '';
            document.getElementById('fm-body').innerHTML = '<div class="fm-empty">Нет файлов за этот месяц</div>';
            return;
        }
        if (!fmActiveTab || !fmData[fmActiveTab]) fmActiveTab = codes[0];

        var html = '';
        for (var i = 0; i < codes.length; i++) {
            var code = codes[i];
            var label = deptLabels[code] || code;
            var count = fmData[code].length;
            html += '<button class="fm-tab' + (code === fmActiveTab ? ' active' : '') + '" data-code="' + code + '">'
                  + label + ' (' + count + ')</button>';
        }
        tabs.innerHTML = html;

        tabs.querySelectorAll('.fm-tab').forEach(function (btn) {
            btn.addEventListener('click', function () {
                fmActiveTab = btn.dataset.code;
                fmRenderTabs();
            });
        });

        fmRenderTable();
    }

    function fmRenderTable() {
        var files = fmData[fmActiveTab] || [];
        var footer = document.getElementById('fm-footer');
        var checkAll = document.getElementById('fm-check-all');
        var delBtn = document.getElementById('fm-del-selected');

        if (files.length === 0) {
            document.getElementById('fm-body').innerHTML = '<div class="fm-empty">Нет файлов</div>';
            footer.style.display = 'none';
            return;
        }

        // Определить «старость» файла по имени
        var today = new Date();
        today.setHours(0,0,0,0);
        var cutoffDate = new Date(today);
        cutoffDate.setDate(cutoffDate.getDate() - 14);

        function isFileOld(name) {
            var m = name.match(/^(\d{4}-\d{2}-\d{2})/);
            if (m) {
                var fileDate = new Date(m[1] + 'T00:00:00');
                return fileDate < cutoffDate;
            }
            var ym = name.match(/^(?:kp|tm)(\d{4})/);
            if (ym) {
                return parseInt(ym[1]) < today.getFullYear();
            }
            return false;
        }

        var oldCount = 0;

        var html = '<table class="fm-table"><thead><tr>'
            + '<th style="width:30px"></th><th>Файл</th><th>Размер</th><th>Создан</th>'
            + '</tr></thead><tbody>';
        for (var i = 0; i < files.length; i++) {
            var f = files[i];
            var isOld = isFileOld(f.name);
            if (isOld) oldCount++;
            html += '<tr data-fname="' + f.name + '"' + (isOld ? ' style="opacity:.6"' : '') + '>'
                + '<td><input type="checkbox" class="fm-chk" data-name="' + f.name + '"' + (isOld ? ' checked' : '') + '></td>'
                + '<td><a href="' + f.url + '" download style="color:var(--orange)">' + f.name + '</a></td>'
                + '<td>' + f.size_kb + ' KB</td>'
                + '<td>' + f.mtime + '</td>'
                + '</tr>';
        }
        html += '</tbody></table>';
        document.getElementById('fm-body').innerHTML = html;
        footer.style.display = '';
        checkAll.checked = oldCount > 0 && oldCount === files.length;
        delBtn.disabled = oldCount === 0;

        function updateDelBtn() {
            var checked = document.querySelectorAll('.fm-chk:checked');
            delBtn.disabled = checked.length === 0;
            delBtn.textContent = checked.length > 0
                ? 'Удалить выбранные (' + checked.length + ')'
                : 'Удалить выбранные';
        }
        updateDelBtn();

        document.querySelectorAll('.fm-chk').forEach(function (chk) {
            chk.addEventListener('change', function () {
                var allChks = document.querySelectorAll('.fm-chk');
                var allChecked = document.querySelectorAll('.fm-chk:checked');
                checkAll.checked = allChks.length === allChecked.length;
                updateDelBtn();
            });
        });

        checkAll.onchange = function () {
            document.querySelectorAll('.fm-chk').forEach(function (chk) {
                chk.checked = checkAll.checked;
            });
            updateDelBtn();
        };

        delBtn.onclick = function () {
            var names = [];
            document.querySelectorAll('.fm-chk:checked').forEach(function (chk) {
                names.push(chk.dataset.name);
            });
            if (names.length === 0) return;
            if (!confirm('Удалить ' + names.length + ' файл(ов)?')) return;
            delBtn.disabled = true;
            delBtn.textContent = 'Удаление…';
            fmDeleteFiles(names, 0);
        };
    }

    function fmDeleteFiles(names, idx) {
        if (idx >= names.length) {
            fmRenderTabs();
            return;
        }
        var name = names[idx];
        var xhr2 = new XMLHttpRequest();
        xhr2.open('POST', 'api/files.php');
        xhr2.setRequestHeader('Content-Type', 'application/json');
        xhr2.onload = function () {
            try {
                var r = JSON.parse(xhr2.responseText);
                if (r.ok) {
                    fmData[fmActiveTab] = (fmData[fmActiveTab] || []).filter(function (x) { return x.name !== name; });
                    if (fmData[fmActiveTab] && fmData[fmActiveTab].length === 0) delete fmData[fmActiveTab];
                    var cell = document.querySelector('.cal-cell[data-date="' + name.substring(0, 10) + '"]');
                    if (cell) cell.removeAttribute('data-has-file');
                }
            } catch (e) {}
            fmDeleteFiles(names, idx + 1);
        };
        xhr2.onerror = function () { fmDeleteFiles(names, idx + 1); };
        xhr2.send(JSON.stringify({ action: 'delete', filename: name }));
    }

    var btnFiles = document.getElementById('btn-files');
    if (btnFiles) {
        btnFiles.addEventListener('click', fmOpen);
    }

    // Закрытие модалки и попапа
    document.getElementById('fm-close-btn').addEventListener('click', fmClose);
    document.getElementById('files-modal').addEventListener('click', function (e) {
        if (e.target === this) fmClose();
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            if (document.getElementById('files-modal').style.display !== 'none') {
                fmClose();
            } else if (popupOpen) {
                closePopup();
            }
        }
    });

}());
</script>
</body>
</html>
