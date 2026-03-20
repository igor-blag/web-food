<?php
/**
 * food/index.php — публичный календарь питания для родителей.
 * Показывает только текущий и будущие месяцы (прошлые скрыты).
 */
$adminRoot = __DIR__ . '/../food-app';
require_once $adminRoot . '/config.php';
require_once $adminRoot . '/src/db.php';

$enabledDepts = get_enabled_departments();
$orgName      = get_org_name();
$validTypes   = array_column($enabledDepts, 'code');
$typeLabels   = array_combine(
    array_column($enabledDepts, 'code'),
    array_column($enabledDepts, 'label')
);
$type = in_array($_GET['type'] ?? '', $validTypes) ? $_GET['type'] : ($validTypes[0] ?? 'sm');

// Текущий месяц как минимальный
$todayDt      = new DateTimeImmutable(date('Y-m-d'));
$firstAllowed = new DateTimeImmutable($todayDt->format('Y-m-01'));

$year  = (int)($_GET['y'] ?? $todayDt->format('Y'));
$month = (int)($_GET['m'] ?? $todayDt->format('n'));

$reqDt = new DateTimeImmutable("$year-$month-01");
if ($reqDt < $firstAllowed) {
    $reqDt = $firstAllowed;
    $year  = (int)$reqDt->format('Y');
    $month = (int)$reqDt->format('n');
}

$daysInMonth = (int)$reqDt->format('t');
$firstDow    = (int)$reqDt->format('N');

$s = db()->prepare(
    'SELECT c.date, c.template_id, mt.day_number
       FROM calendar c
       LEFT JOIN menu_templates mt ON mt.id = c.template_id
      WHERE c.school_type = ? AND YEAR(c.date) = ? AND MONTH(c.date) = ?'
);
$s->execute([$type, $year, $month]);
$calRows = [];
foreach ($s->fetchAll() as $r) {
    $calRows[$r['date']] = $r;
}

$prevDt = $reqDt->modify('-1 month');
$nextDt = $reqDt->modify('+1 month');
$today  = $todayDt->format('Y-m-d');

$monthFrom = sprintf('%04d-%02d-01', $year, $month);
$monthTo   = sprintf('%04d-%02d-%02d', $year, $month, $daysInMonth);
$vacationDays = get_vacation_days_for_range($monthFrom, $monthTo);

$monthNames = [
    1=>'Январь',2=>'Февраль',3=>'Март',4=>'Апрель',5=>'Май',6=>'Июнь',
    7=>'Июль',8=>'Август',9=>'Сентябрь',10=>'Октябрь',11=>'Ноябрь',12=>'Декабрь',
];

// Формирует URL с сохранением текущего месяца и типа
function calUrl(string $type, int $y, int $m): string {
    return 'index.php?type=' . $type . '&y=' . $y . '&m=' . $m;
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Организация питания<?= $orgName ? ' | ' . htmlspecialchars($orgName) : '' ?></title>
    <link rel="stylesheet" href="menu/assets/style.css">
</head>
<body>
<header>
    <div class="logo"><?= htmlspecialchars($orgName ?: 'Организация питания') ?></div>
    <nav>
        <a href="index.php?type=<?= $type ?>" class="active">Календарь</a>
        <div class="nav-dropdown" tabindex="0">
            <a href="menu/menu.php?type=<?= $type ?>">Типовое меню</a>
            <div class="nav-dropdown-menu">
                <?php foreach ($enabledDepts as $dept): ?>
                <a href="menu/menu.php?type=<?= htmlspecialchars($dept['code']) ?>">
                    <?= htmlspecialchars($dept['label']) ?>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
        <a href="oc.php">Общ. контроль</a>
    </nav>
</header>

<div class="container">
    <div class="page-title">Календарь питания</div>

    <!-- Табы типов школ -->
    <div class="cycle-tabs">
        <?php foreach ($typeLabels as $t => $label): ?>
            <a href="<?= calUrl($t, $year, $month) ?>"
               class="cycle-tab<?= $t === $type ? ' active' : '' ?>">
                <?= htmlspecialchars($label) ?>
            </a>
        <?php endforeach; ?>
    </div>

    <div class="cal-nav">
        <?php if ($prevDt >= $firstAllowed): ?>
            <a href="<?= calUrl($type, (int)$prevDt->format('Y'), (int)$prevDt->format('n')) ?>">
                ← <?= $monthNames[(int)$prevDt->format('n')] ?>
            </a>
        <?php else: ?>
            <a class="disabled">←</a>
        <?php endif; ?>

        <h2><?= $monthNames[$month] ?> <?= $year ?></h2>

        <a href="<?= calUrl($type, (int)$nextDt->format('Y'), (int)$nextDt->format('n')) ?>">
            <?= $monthNames[(int)$nextDt->format('n')] ?> →
        </a>
    </div>

    <div class="cal-grid">
        <?php foreach (['Пн','Вт','Ср','Чт','Пт','Сб','Вс'] as $h): ?>
            <div class="cal-head"><?= $h ?></div>
        <?php endforeach; ?>

        <?php for ($e = 1; $e < $firstDow; $e++): ?>
            <div class="cal-cell empty"></div>
        <?php endfor;

        for ($d = 1; $d <= $daysInMonth; $d++):
            $dateStr   = sprintf('%04d-%02d-%02d', $year, $month, $d);
            $dow       = (int)(new DateTimeImmutable($dateStr))->format('N');
            $isWeekend = $dow >= 6;
            $entry     = $calRows[$dateStr] ?? null;
            $hasMenu   = $entry && $entry['template_id'] !== null;
            $isHoliday = $entry && $entry['template_id'] === null;

            $isVacation = isset($vacationDays[$dateStr]);

            $classes = ['cal-cell'];
            if ($dateStr === $today) $classes[] = 'today';
            if ($hasMenu)            $classes[] = 'has-menu';
            elseif ($isHoliday)      $classes[] = 'holiday';
            elseif ($isVacation)     $classes[] = 'vacation';
            elseif ($isWeekend)      $classes[] = 'weekend';
        ?>
            <div class="<?= implode(' ', $classes) ?>">
                <div class="day-num"><?= $d ?></div>
                <?php if ($hasMenu): ?>
                    <a class="day-link" href="menu/day.php?date=<?= $dateStr ?>&type=<?= $type ?>">Меню</a>
                <?php elseif ($isHoliday): ?>
                    <div class="no-school">Выходной</div>
                <?php endif; ?>
            </div>
        <?php endfor;

        $lastDow = (int)(new DateTimeImmutable("$year-$month-$daysInMonth"))->format('N');
        for ($e = $lastDow + 1; $e <= 7; $e++): ?>
            <div class="cal-cell empty"></div>
        <?php endfor; ?>
    </div>

    <div class="legend">
        <div class="legend-item"><div class="legend-dot has-menu"></div> Меню опубликовано</div>
        <div class="legend-item"><div class="legend-dot no-menu"></div> Нет данных</div>
        <div class="legend-item"><div class="legend-dot holiday"></div> Выходной / праздник</div>
        <div class="legend-item"><div class="legend-dot vacation"></div> Каникулы</div>
    </div>

</div>
<footer>
    <a href="https://github.com/igor-blag/web-food" target="_blank" rel="noopener">github.com/igor-blag/web-food</a>
</footer>
</body>
</html>
