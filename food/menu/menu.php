<?php
/**
 * food/menu.php — типовое (цикловое) меню для родителей.
 * Показывает все N дней цикла с разбивкой по приёмам пищи.
 */
$adminRoot = __DIR__ . '/../../food-app';
require_once $adminRoot . '/config.php';
require_once $adminRoot . '/src/db.php';

$enabledDepts = get_enabled_departments();
$validTypes   = array_column($enabledDepts, 'code');
$typeLabels   = array_combine(
    array_column($enabledDepts, 'code'),
    array_column($enabledDepts, 'label')
);
$type = in_array($_GET['type'] ?? '', $validTypes) ? $_GET['type'] : ($validTypes[0] ?? 'sm');

// Получить все шаблоны для типа, отсортированные по day_number
$s = db()->prepare(
    'SELECT id, day_number, label, is_boarding
       FROM menu_templates WHERE school_type = ? ORDER BY day_number'
);
$s->execute([$type]);
$templates = $s->fetchAll();

$cycleLen = count($templates);

// Выбранный день (таб)
$selId = (int)($_GET['day'] ?? ($templates[0]['id'] ?? 0));

// Получить блюда для выбранного шаблона
$items  = [];
$selTpl = null;
foreach ($templates as $t) {
    if ($t['id'] == $selId) {
        $selTpl = $t;
        break;
    }
}

if ($selTpl) {
    $s2 = db()->prepare(
        'SELECT * FROM menu_items WHERE template_id = ? ORDER BY meal_type, sort_order, id'
    );
    $s2->execute([$selTpl['id']]);
    $items = $s2->fetchAll();
}

$byMeal = [];
foreach ($items as $it) {
    $byMeal[$it['meal_type']][] = $it;
}

$mealNames = [
    'breakfast'       => 'Завтрак',
    'breakfast2'      => 'Второй завтрак',
    'lunch'           => 'Обед',
    'afternoon_snack' => 'Полдник',
    'dinner'          => 'Ужин',
    'dinner2'         => 'Ужин (2-й)',
];
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Типовое меню</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
<header>
    <div class="logo">Мониторинг питания</div>
    <nav>
        <a href="../index.php?type=<?= $type ?>">Календарь</a>
        <a href="menu.php?type=<?= $type ?>" class="active">Типовое меню</a>
    </nav>
</header>

<div class="container">
    <div class="page-title">Типовое меню — <?= $cycleLen ?>-дневный цикл</div>

    <?php if (empty($templates)): ?>
        <div class="notice">Шаблоны меню ещё не созданы.</div>
    <?php else: ?>

    <!-- Табы по дням цикла -->
    <div class="cycle-tabs">
        <?php foreach ($templates as $t): ?>
            <a href="menu.php?day=<?= $t['id'] ?>"
               class="cycle-tab<?= $t['id'] == $selId ? ' active' : '' ?>">
                <?= htmlspecialchars($t['label']) ?>
            </a>
        <?php endforeach; ?>
    </div>

    <?php if ($selTpl && empty($items)): ?>
        <div class="notice">Блюда для этого дня ещё не добавлены.</div>
    <?php elseif ($selTpl): ?>

        <?php foreach ($mealNames as $mtype => $mname):
            if (empty($byMeal[$mtype])) continue;
            if (in_array($mtype, ['afternoon_snack','dinner','dinner2']) && !$selTpl['is_boarding']) continue;
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
    <?php endif; ?>
</div>
</body>
</html>
