<?php
require_once __DIR__ . '/src/auth.php';
require_once __DIR__ . '/src/db.php';
require_auth();

$id  = (int)($_GET['id'] ?? 0);
$tpl = get_template($id);
if (!$tpl) { header('Location: templates.php'); exit; }

// Проверяем, залочен ли boarding для этого отделения
$deptInfo = get_department($tpl['school_type']);
$forcedBoarding = $deptInfo && $deptInfo['is_boarding'];

$msg = '';

// ── Сохранение ───────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Флаг интерната (залочен, если отделение требует)
    $isBoarding = $forcedBoarding ? 1 : (!empty($_POST['is_boarding']) ? 1 : 0);
    set_template_boarding($id, $isBoarding);

    $allItems  = [];
    $mealTypes = ['breakfast', 'breakfast2', 'lunch'];
    if ($isBoarding) {
        $mealTypes[] = 'afternoon_snack';
        $mealTypes[] = 'dinner';
        $mealTypes[] = 'dinner2';
    }

    foreach ($mealTypes as $meal) {
        $sections   = $_POST[$meal]['section']    ?? [];
        $recipes    = $_POST[$meal]['recipe_num'] ?? [];
        $dishes     = $_POST[$meal]['dish_name']  ?? [];
        $grams      = $_POST[$meal]['grams']      ?? [];
        $prices     = $_POST[$meal]['price']      ?? [];
        $kcals      = $_POST[$meal]['kcal']       ?? [];
        $proteins   = $_POST[$meal]['protein']    ?? [];
        $fats       = $_POST[$meal]['fat']        ?? [];
        $carbsArr   = $_POST[$meal]['carbs']      ?? [];

        foreach ($dishes as $i => $dish) {
            $dish = trim($dish);
            if ($dish === '') continue;
            $allItems[] = [
                'meal_type'  => $meal,
                'section'    => trim($sections[$i]  ?? ''),
                'recipe_num' => trim($recipes[$i]   ?? ''),
                'dish_name'  => $dish,
                'grams'      => $grams[$i]    !== '' ? $grams[$i]    : null,
                'price'      => $prices[$i]   !== '' ? $prices[$i]   : null,
                'kcal'       => $kcals[$i]    !== '' ? $kcals[$i]    : null,
                'protein'    => $proteins[$i] !== '' ? $proteins[$i] : null,
                'fat'        => $fats[$i]     !== '' ? $fats[$i]     : null,
                'carbs'      => $carbsArr[$i] !== '' ? $carbsArr[$i] : null,
            ];
        }
    }
    save_template_items($id, $allItems);

    // Автогенерация tm-файла при сохранении sm-шаблона
    if ($tpl['school_type'] === 'sm') {
        require_once __DIR__ . '/src/tm_generator.php';
        $approveDate = trim($_POST['tm_approve_date'] ?? '');
        if ($approveDate) {
            save_tm_approve_date($approveDate);
        }
        generate_typical_menu_excel('sm', (int)date('Y'));
    }

    $msg = 'success:Шаблон сохранён.';
    // Обновляем tpl чтобы отразить новый is_boarding
    $tpl = get_template($id);
}

$items = get_template_items($id);
$isBoarding = !empty($tpl['is_boarding']);

$sectionMeta = [
    'breakfast'  => ['label' => 'Завтрак',   'hint' => 'гор.блюдо, напиток, хлеб…',           'boarding' => false],
    'breakfast2' => ['label' => 'Завтрак 2', 'hint' => 'фрукты и т.д.',                        'boarding' => false],
    'lunch'      => ['label' => 'Обед',       'hint' => 'закуска, 1 блюдо, 2 блюдо, гарнир…', 'boarding' => false],
    'afternoon_snack' => ['label' => 'Полдник', 'hint' => 'фрукты, напиток…',                  'boarding' => true],
    'dinner'     => ['label' => 'Ужин',       'hint' => 'гор.блюдо, напиток…',                 'boarding' => true],
    'dinner2'    => ['label' => 'Ужин 2',     'hint' => 'кефир, выпечка…',                     'boarding' => true],
];
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($tpl['label']) ?> — Меню</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
<?php include __DIR__ . '/src/header.php'; ?>
<div class="container">
    <div class="flex items-center justify-between mb-2">
        <h1 class="page-title" style="border:none;margin:0">
            Редактор: <?= htmlspecialchars($tpl['label']) ?>
        </h1>
        <div class="flex gap-2">
            <a href="import.php?id=<?= $id ?>" class="btn btn-dark btn-sm">&#x2191; Импорт из Excel</a>
            <a href="templates.php?type=<?= htmlspecialchars($tpl['school_type']) ?>" class="btn btn-outline btn-sm">&larr; Все шаблоны</a>
        </div>
    </div>

    <?php if ($msg): [$t, $m] = explode(':', $msg, 2); ?>
        <div class="alert alert-<?= $t ?>"><?= htmlspecialchars($m) ?></div>
    <?php endif; ?>
    <?php if (isset($_GET['imported'])): ?>
        <div class="alert alert-success">&#x2713; Меню успешно импортировано из Excel.</div>
    <?php endif; ?>

    <form method="post" id="tpl-form">

    <!-- Флаг интерната -->
    <div class="panel" style="margin-bottom:12px;padding:12px 16px">
        <label style="display:flex;align-items:center;gap:10px;cursor:pointer;font-weight:500">
            <input type="checkbox" name="is_boarding" id="boardingCheck" value="1"
                   <?= $isBoarding ? 'checked' : '' ?>
                   <?= $forcedBoarding ? 'disabled checked' : '' ?>
                   style="width:16px;height:16px">
            <?php if ($forcedBoarding): ?>
                <input type="hidden" name="is_boarding" value="1">
            <?php endif; ?>
            Интернат (расширенное питание: Полдник, Ужин, Ужин 2)
            <?php if ($forcedBoarding): ?>
                <span style="font-size:.78em;color:var(--muted);margin-left:8px">(задано в настройках отделения)</span>
            <?php endif; ?>
        </label>
    </div>

    <?php foreach ($sectionMeta as $meal => $meta):
        $rows = $items[$meal] ?? [];
        $rows[] = []; $rows[] = []; // две пустые строки
        $hidden = ($meta['boarding'] && !$isBoarding) ? ' style="display:none"' : '';
        $boardingClass = $meta['boarding'] ? ' boarding-section' : '';
    ?>
    <div class="meal-section<?= $boardingClass ?>"<?= $hidden ?>>
        <div class="meal-section-title">
            <?= $meta['label'] ?>
            <span style="font-weight:normal;opacity:.6;font-size:.85em;margin-left:8px"><?= $meta['hint'] ?></span>
        </div>
        <table class="items-table" id="tbl-<?= $meal ?>">
            <thead>
                <tr>
                    <th style="width:100px">Раздел</th>
                    <th style="width:90px">№ рец.</th>
                    <th>Блюдо</th>
                    <th style="width:60px">Выход</th>
                    <th style="width:65px">Цена</th>
                    <th style="width:60px">Ккал</th>
                    <th style="width:55px">Белки</th>
                    <th style="width:55px">Жиры</th>
                    <th style="width:55px">Углев.</th>
                    <th style="width:32px"></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($rows as $row): ?>
                <tr>
                    <td><input type="text" name="<?= $meal ?>[section][]"
                               value="<?= htmlspecialchars($row['section'] ?? '') ?>"></td>
                    <td><input type="text" name="<?= $meal ?>[recipe_num][]"
                               value="<?= htmlspecialchars($row['recipe_num'] ?? '') ?>"></td>
                    <td><input type="text" name="<?= $meal ?>[dish_name][]"
                               value="<?= htmlspecialchars($row['dish_name'] ?? '') ?>"></td>
                    <td><input type="number" step="0.1" name="<?= $meal ?>[grams][]"
                               value="<?= $row['grams'] ?? '' ?>"></td>
                    <td><input type="number" step="0.01" name="<?= $meal ?>[price][]"
                               value="<?= $row['price'] ?? '' ?>"></td>
                    <td><input type="number" step="0.01" name="<?= $meal ?>[kcal][]"
                               value="<?= $row['kcal'] ?? '' ?>"></td>
                    <td><input type="number" step="0.01" name="<?= $meal ?>[protein][]"
                               value="<?= $row['protein'] ?? '' ?>"></td>
                    <td><input type="number" step="0.01" name="<?= $meal ?>[fat][]"
                               value="<?= $row['fat'] ?? '' ?>"></td>
                    <td><input type="number" step="0.01" name="<?= $meal ?>[carbs][]"
                               value="<?= $row['carbs'] ?? '' ?>"></td>
                    <td><button type="button" class="del-row" title="Удалить строку">✕</button></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <button type="button" class="btn btn-outline btn-sm add-row-btn" data-target="tbl-<?= $meal ?>" data-meal="<?= $meal ?>">
            + Добавить блюдо
        </button>
    </div>
    <?php endforeach; ?>

    <?php if ($tpl['school_type'] === 'sm'): ?>
    <?php $storedDate = get_kitchen_settings()['tm_approve_date'] ?? ''; ?>
    <div class="panel" style="margin-top:12px;padding:12px 16px">
        <div style="font-size:.82rem;text-transform:uppercase;letter-spacing:.06em;color:var(--muted);margin-bottom:8px">
            Типовое меню — дата утверждения
        </div>
        <div style="display:flex;align-items:center;gap:12px">
            <input type="date" name="tm_approve_date"
                   value="<?= htmlspecialchars($storedDate) ?>"
                   style="padding:6px 10px;font-family:Georgia,serif;font-size:.9rem;border:1px solid var(--border-light);border-radius:var(--radius);color:var(--text)">
            <span style="font-size:.82rem;color:var(--muted)">Будет записана в шапку tm-файла при сохранении</span>
        </div>
    </div>
    <?php endif; ?>

    <div class="flex gap-2 mt-3">
        <button type="submit" class="btn btn-primary">Сохранить шаблон</button>
        <a href="templates.php?type=<?= htmlspecialchars($tpl['school_type']) ?>" class="btn btn-outline">Отмена</a>
    </div>
    </form>
</div>

<script>
// Переключение секций интерната
document.getElementById('boardingCheck').addEventListener('change', function() {
    var show = this.checked;
    document.querySelectorAll('.boarding-section').forEach(function(el) {
        el.style.display = show ? '' : 'none';
    });
});

// Удалить строку
document.addEventListener('click', function(e) {
    if (e.target.classList.contains('del-row')) {
        e.target.closest('tr').remove();
    }
});

// Добавить пустую строку
document.querySelectorAll('.add-row-btn').forEach(function(btn) {
    btn.addEventListener('click', function() {
        var meal  = btn.dataset.meal;
        var tbody = document.querySelector('#' + btn.dataset.target + ' tbody');
        var tr    = document.createElement('tr');
        var fields = ['section','recipe_num','dish_name'];
        var nums   = ['grams','price','kcal','protein','fat','carbs'];
        var html   = '';
        fields.forEach(function(f) {
            html += '<td><input type="text" name="' + meal + '[' + f + '][]"></td>';
        });
        nums.forEach(function(f) {
            html += '<td><input type="number" step="0.01" name="' + meal + '[' + f + '][]"></td>';
        });
        html += '<td><button type="button" class="del-row" title="Удалить">✕</button></td>';
        tr.innerHTML = html;
        tbody.appendChild(tr);
        tr.querySelector('input').focus();
    });
});
</script>
</body>
</html>
