<?php
require_once __DIR__ . '/src/auth.php';
require_once __DIR__ . '/src/db.php';
require_auth();

$enabledDepts = get_enabled_departments();
$validTypes   = array_column($enabledDepts, 'code');
$typeLabels   = array_combine(
    array_column($enabledDepts, 'code'),
    array_column($enabledDepts, 'label')
);
$type = in_array($_GET['type'] ?? '', $validTypes) ? $_GET['type'] : ($validTypes[0] ?? 'sm');

// ── Действия ─────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $newId = add_template($type);
        header('Location: template-edit.php?id=' . $newId);
        exit;
    }

    if ($action === 'delete') {
        $delId = (int)($_POST['id'] ?? 0);
        $tpl   = get_template($delId);
        if ($tpl && $tpl['school_type'] === $type) {
            delete_template($delId);
        }
        header('Location: templates.php?type=' . $type);
        exit;
    }
}

// ── Данные ───────────────────────────────────────────────────────────────────
$templates   = get_templates($type);
$cycleLen    = count($templates);

// Обнаружить пропуски в нумерации
$dayNums = array_column($templates, 'day_number');
$gaps    = [];
for ($i = 0; $i < count($dayNums) - 1; $i++) {
    for ($g = (int)$dayNums[$i] + 1; $g < (int)$dayNums[$i + 1]; $g++) {
        $gaps[] = $g;
    }
}

// Название цикла
$cycleTitle = $cycleLen > 0 ? $cycleLen . '-дневный цикл' : 'Нет шаблонов';

// tm-файл для sm
$tmFile = FILES_DIR . 'tm' . date('Y') . '-sm.xlsx';
$hasTm  = file_exists($tmFile);
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Шаблоны — Меню</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
<?php include __DIR__ . '/src/header.php'; ?>
<div class="container">
    <div class="flex items-center justify-between mb-2">
        <h1 class="page-title" style="border:none;margin:0"><?= htmlspecialchars($cycleTitle) ?></h1>
        <div class="flex gap-2">
            <?php if ($type === 'sm' && $hasTm): ?>
            <a href="<?= FILES_URL ?>tm<?= date('Y') ?>-sm.xlsx"
               class="btn btn-outline btn-sm" download>&#x2193; tm-файл</a>
            <?php endif; ?>
            <form method="post" style="display:inline">
                <input type="hidden" name="action" value="add">
                <button type="submit" class="btn btn-primary btn-sm">+ Добавить день</button>
            </form>
        </div>
    </div>

    <!-- Табы типов школ -->
    <div class="tab-bar">
        <?php foreach ($typeLabels as $t => $label): ?>
        <a href="templates.php?type=<?= $t ?>"
           class="tab-item<?= $type === $t ? ' active' : '' ?>"><?= $label ?></a>
        <?php endforeach; ?>
    </div>

    <?php if (!empty($gaps)): ?>
    <div class="alert alert-error" style="margin-bottom:16px">
        ⚠ В цикле пропущены шаблоны: <strong>№<?= implode(', №', $gaps) ?></strong>.
        Дни с такими номерами не будут назначаться в календаре.
    </div>
    <?php endif; ?>

    <?php if ($cycleLen === 0): ?>
    <div class="alert alert-error">Шаблоны не добавлены. Нажмите «+ Добавить день» чтобы начать.</div>
    <?php else: ?>
    <div class="panel">
        <table class="menu-table">
            <thead>
                <tr>
                    <th style="width:50px">№</th>
                    <th>Название</th>
                    <th style="width:110px">Завтрак</th>
                    <th style="width:110px">Завтрак 2</th>
                    <th style="width:110px">Обед</th>
                    <th style="width:120px"></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($templates as $t):
                    $items = get_template_items((int)$t['id']);
                    $c1 = count($items['breakfast']);
                    $c2 = count($items['breakfast2']);
                    $c3 = count($items['lunch']);
                    $isGap = in_array((int)$t['day_number'] + 1, $gaps)
                          || ($t !== end($templates) && !in_array((int)$t['day_number'], $dayNums));
                ?>
                <tr>
                    <td class="center"><?= (int)$t['day_number'] ?></td>
                    <td>
                        <?= htmlspecialchars($t['label']) ?>
                        <?php if ($t['is_boarding']): ?>
                            <span style="font-size:.72em;background:#e8f0fe;color:#1a56db;padding:1px 5px;border-radius:3px;margin-left:6px;vertical-align:middle">ИНТЕРНАТ</span>
                        <?php endif; ?>
                    </td>
                    <td class="center">
                        <?= $c1 ? '<span style="color:var(--success)">✓ '.$c1.'</span>' : '<span class="text-muted">—</span>' ?>
                    </td>
                    <td class="center">
                        <?= $c2 ? '<span style="color:var(--success)">✓ '.$c2.'</span>' : '<span class="text-muted">—</span>' ?>
                    </td>
                    <td class="center">
                        <?= $c3 ? '<span style="color:var(--success)">✓ '.$c3.'</span>' : '<span class="text-muted">—</span>' ?>
                    </td>
                    <td class="center" style="white-space:nowrap">
                        <a href="template-edit.php?id=<?= $t['id'] ?>"
                           class="btn btn-outline btn-sm">Изменить</a>
                        <a href="import.php?id=<?= $t['id'] ?>"
                           class="btn btn-dark btn-sm" style="margin-left:4px">&#x2191; Импорт</a>
                        <form method="post" class="del-tpl-form"
                              data-label="<?= htmlspecialchars($t['label'], ENT_QUOTES) ?>"
                              style="display:inline;margin-left:4px">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= $t['id'] ?>">
                            <button type="submit" class="btn btn-danger btn-sm"
                                    title="Удалить шаблон">✕</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<script>
document.querySelectorAll('.del-tpl-form').forEach(function (form) {
    var btn = form.querySelector('button');
    var timer = null;
    var armed = false;

    form.addEventListener('submit', function (e) {
        if (!armed) {
            e.preventDefault();
            armed = true;
            btn.textContent = 'Удалить?';
            btn.style.minWidth = '72px';
            clearTimeout(timer);
            timer = setTimeout(function () {
                armed = false;
                btn.textContent = '✕';
                btn.style.minWidth = '';
            }, 3000);
        }
        // armed=true → форма отправляется
    });
});
</script>
</body>
</html>
