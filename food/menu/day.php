<?php
/**
 * food/day.php — меню на конкретный день для родителей.
 */
$adminRoot = __DIR__ . '/../../food-app';
require_once $adminRoot . '/config.php';
require_once $adminRoot . '/src/db.php';

$date = $_GET['date'] ?? '';
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || !strtotime($date)) {
    header('Location: index.php');
    exit;
}

$enabledDepts = get_enabled_departments();
$orgName      = get_org_name();
$validTypes   = array_column($enabledDepts, 'code');
$typeLabels   = array_combine(
    array_column($enabledDepts, 'code'),
    array_column($enabledDepts, 'label')
);
$type = in_array($_GET['type'] ?? '', $validTypes) ? $_GET['type'] : ($validTypes[0] ?? 'sm');

$dt = new DateTimeImmutable($date);

$s = db()->prepare(
    'SELECT c.*, mt.day_number, mt.label, mt.is_boarding
       FROM calendar c
       JOIN menu_templates mt ON mt.id = c.template_id
      WHERE c.date = ? AND c.school_type = ? AND c.template_id IS NOT NULL'
);
$s->execute([$date, $type]);
$cal = $s->fetch();

if (!$cal) {
    // Нет меню на этот день
    $noMenu = true;
} else {
    $noMenu = false;
    // Загрузить блюда
    $s2 = db()->prepare(
        'SELECT * FROM menu_items WHERE template_id = ? ORDER BY meal_type, sort_order, id'
    );
    $s2->execute([$cal['template_id']]);
    $items = $s2->fetchAll();

    // Группировка по meal_type
    $byMeal = [];
    foreach ($items as $it) {
        $byMeal[$it['meal_type']][] = $it;
    }
}

$mealNames = [
    'breakfast'       => 'Завтрак',
    'breakfast2'      => 'Второй завтрак',
    'lunch'           => 'Обед',
    'afternoon_snack' => 'Полдник',
    'dinner'          => 'Ужин',
    'dinner2'         => 'Ужин (2-й)',
];

// Форматирование даты на русском
$dayNames  = ['','Понедельник','Вторник','Среда','Четверг','Пятница','Суббота','Воскресенье'];
$monthNamesG = [
    1=>'января',2=>'февраля',3=>'марта',4=>'апреля',5=>'мая',6=>'июня',
    7=>'июля',8=>'августа',9=>'сентября',10=>'октября',11=>'ноября',12=>'декабря',
];
$dow  = (int)$dt->format('N');
$dayStr = $dayNames[$dow] . ', ' . (int)$dt->format('d') . ' ' . $monthNamesG[(int)$dt->format('n')] . ' ' . $dt->format('Y') . ' г.';

$typeLabels = ['sm' => 'Начальная', 'main' => 'Основная', 'ss' => 'Старшая'];
$backUrl = '../index.php?type=' . $type . '&y=' . $dt->format('Y') . '&m=' . (int)$dt->format('n');
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Меню на <?= htmlspecialchars($date) ?><?= $orgName ? ' | ' . htmlspecialchars($orgName) : '' ?></title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
<header>
    <div class="logo"><?= htmlspecialchars($orgName ?: 'Организация питания') ?></div>
    <nav>
        <a href="../index.php?type=<?= $type ?>">Календарь</a>
        <div class="nav-dropdown" tabindex="0">
            <a href="menu.php?type=<?= $type ?>">Типовое меню</a>
            <div class="nav-dropdown-menu">
                <?php foreach ($enabledDepts as $dept): ?>
                <a href="menu.php?type=<?= htmlspecialchars($dept['code']) ?>">
                    <?= htmlspecialchars($dept['label']) ?>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
        <a href="../oc.php">Общ. контроль</a>
    </nav>
</header>

<div class="container">
    <div class="page-title">Меню на день</div>
    <div class="menu-date"><?= $dayStr ?> — <?= htmlspecialchars($typeLabels[$type] ?? $type) ?></div>

    <div class="mb-2">
        <a href="<?= $backUrl ?>" class="btn btn-outline">← Назад к календарю</a>
    </div>

    <?php
    $suffix  = ($type !== 'main') ? "-{$type}" : '';
    $xlsName = $date . $suffix . '.xlsx';
    if (file_exists(FILES_DIR . $xlsName)): ?>
    <div class="mb-2">
        <a href="<?= FILES_URL . htmlspecialchars($xlsName) ?>" class="btn btn-outline" download>
            ↓ Скачать меню (.xlsx)</a>
    </div>
    <?php endif; ?>

    <?php if ($noMenu): ?>
        <div class="notice">Меню на этот день не опубликовано.</div>
    <?php else: ?>

        <?php foreach ($mealNames as $mtype => $mname):
            if (empty($byMeal[$mtype])) continue;
            // Пропустить интернатские приёмы если is_boarding=0
            if (in_array($mtype, ['afternoon_snack','dinner','dinner2']) && !$cal['is_boarding']) continue;
        ?>
        <div class="meal-block">
            <div class="meal-title"><?= $mname ?></div>
            <table class="meal-table">
                <thead>
                    <tr>
                        <th style="width:42%">Блюдо</th>
                        <th style="width:8%">Выход, г</th>
                        <th style="width:9%">Цена, ₽</th>
                        <th style="width:9%">Ккал</th>
                        <th style="width:8%">Белки</th>
                        <th style="width:8%">Жиры</th>
                        <th style="width:8%">Углев.</th>
                    </tr>
                </thead>
                <tbody>
                <?php
                $prevSection = null;
                foreach ($byMeal[$mtype] as $it):
                    if ($it['section'] && $it['section'] !== $prevSection):
                        $prevSection = $it['section'];
                ?>
                    <tr class="section-row">
                        <td colspan="7"><?= htmlspecialchars($it['section']) ?></td>
                    </tr>
                <?php endif; ?>
                    <tr>
                        <td><?= htmlspecialchars($it['dish_name'] ?? '') ?></td>
                        <td class="num"><?= $it['grams']   !== null ? number_format($it['grams'],   1, ',', '') : '' ?></td>
                        <td class="num"><?= $it['price']   !== null ? number_format($it['price'],   2, ',', '') : '' ?></td>
                        <td class="num"><?= $it['kcal']    !== null ? number_format($it['kcal'],    1, ',', '') : '' ?></td>
                        <td class="num"><?= $it['protein'] !== null ? number_format($it['protein'], 1, ',', '') : '' ?></td>
                        <td class="num"><?= $it['fat']     !== null ? number_format($it['fat'],     1, ',', '') : '' ?></td>
                        <td class="num"><?= $it['carbs']   !== null ? number_format($it['carbs'],   1, ',', '') : '' ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endforeach; ?>

    <?php endif; ?>
</div>
<footer>
    <a href="https://github.com/igor-blag/web-food" target="_blank" rel="noopener">github.com/igor-blag/web-food</a>
</footer>
</body>
</html>
