<?php
require_once __DIR__ . '/src/auth.php';
require_once __DIR__ . '/src/db.php';
require_once __DIR__ . '/src/importer.php';
require_auth();

$tplId = (int)($_GET['id'] ?? $_POST['template_id'] ?? 0);
$tpl   = $tplId ? get_template($tplId) : null;
$type  = $tpl['school_type'] ?? 'sm';
$templates = get_templates($type);

$preview = null;
$msg     = '';

// ── Шаг 1: загрузка файла → показываем превью ────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['xlsx'])) {
    $file = $_FILES['xlsx'];

    if ($file['error'] !== UPLOAD_ERR_OK) {
        $msg = 'error:Ошибка загрузки файла.';
    } elseif (!in_array(mime_content_type($file['tmp_name']), [
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/zip', // некоторые серверы так определяют xlsx
        'application/octet-stream',
    ])) {
        $msg = 'error:Загрузите файл в формате .xlsx';
    } else {
        try {
            $items = parse_menu_xlsx($file['tmp_name']);
            if (empty($items)) {
                $msg = 'error:Блюда не найдены. Проверьте структуру файла.';
            } else {
                // Сохраняем во временный файл для подтверждения
                $tmpPath = sys_get_temp_dir() . '/menu_import_' . session_id() . '.xlsx';
                move_uploaded_file($file['tmp_name'], $tmpPath);
                $_SESSION['import_tmp']  = $tmpPath;
                $_SESSION['import_tpl']  = $tplId;
                $preview = $items;
            }
        } catch (\Exception $e) {
            $msg = 'error:Не удалось прочитать файл: ' . $e->getMessage();
        }
    }
}

// ── Шаг 2: подтверждение → сохраняем в БД ────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm'])) {
    $tmpPath = $_SESSION['import_tmp'] ?? null;
    $saveTpl = (int)($_SESSION['import_tpl'] ?? 0);

    if ($tmpPath && file_exists($tmpPath) && $saveTpl) {
        try {
            $items = parse_menu_xlsx($tmpPath);
            save_template_items($saveTpl, $items);
            unlink($tmpPath);
            unset($_SESSION['import_tmp'], $_SESSION['import_tpl']);
            header('Location: template-edit.php?id=' . $saveTpl . '&imported=1');
            exit;
        } catch (\Exception $e) {
            $msg = 'error:Ошибка при сохранении: ' . $e->getMessage();
        }
    } else {
        $msg = 'error:Сессия устарела, загрузите файл заново.';
    }
}

[$msgType, $msgText] = $msg ? explode(':', $msg, 2) : ['', ''];

$mealLabels = ['breakfast' => 'Завтрак', 'breakfast2' => 'Завтрак 2', 'lunch' => 'Обед'];
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Импорт меню</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
<?php include __DIR__ . '/src/header.php'; ?>
<div class="container">
    <div class="flex items-center justify-between mb-2">
        <h1 class="page-title" style="border:none;margin:0">Импорт из Excel</h1>
        <a href="templates.php" class="btn btn-outline btn-sm">&larr; Шаблоны</a>
    </div>

    <?php if ($msgText): ?>
        <div class="alert alert-<?= $msgType ?>"><?= htmlspecialchars($msgText) ?></div>
    <?php endif; ?>

    <?php if (!$preview): ?>
    <!-- ── Форма загрузки ── -->
    <div class="panel" style="max-width:560px">
        <div class="panel-title">Загрузить файл меню (.xlsx)</div>
        <form method="post" enctype="multipart/form-data">
            <div class="form-group">
                <label>Импортировать в шаблон</label>
                <select name="template_id" class="form-control" required>
                    <option value="">— выберите —</option>
                    <?php foreach ($templates as $t): ?>
                        <option value="<?= $t['id'] ?>" <?= $t['id'] == $tplId ? 'selected' : '' ?>>
                            <?= htmlspecialchars($t['label']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Файл Excel</label>
                <input type="file" name="xlsx" class="form-control" accept=".xlsx" required>
            </div>
            <p class="text-muted mt-1" style="font-size:.82rem">
                Файл должен иметь стандартную структуру меню:<br>
                строка 3 — заголовки, колонка A — приём пищи, колонка D — название блюда.
            </p>
            <div class="mt-2">
                <button type="submit" class="btn btn-primary">Загрузить и проверить</button>
            </div>
        </form>
    </div>

    <?php else: ?>
    <!-- ── Превью ── -->
    <?php
    $grouped = ['breakfast' => [], 'breakfast2' => [], 'lunch' => []];
    foreach ($preview as $item) {
        $grouped[$item['meal_type']][] = $item;
    }
    ?>
    <div class="alert alert-success">
        Найдено блюд: <strong><?= count($preview) ?></strong>.
        Проверьте данные и нажмите «Сохранить».
    </div>

    <?php foreach ($grouped as $meal => $rows):
        if (empty($rows)) continue; ?>
    <div class="panel" style="margin-bottom:16px">
        <div class="panel-title"><?= $mealLabels[$meal] ?> — <?= count($rows) ?> блюд</div>
        <table class="menu-table">
            <thead>
                <tr>
                    <th>Раздел</th><th>№ рец.</th><th>Блюдо</th>
                    <th>Выход</th><th>Ккал</th><th>Б / Ж / У</th><th>Цена</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($rows as $item): ?>
                <tr>
                    <td><?= htmlspecialchars($item['section'] ?? '') ?></td>
                    <td class="center"><?= htmlspecialchars($item['recipe_num'] ?? '') ?></td>
                    <td><?= htmlspecialchars($item['dish_name']) ?></td>
                    <td class="num"><?= $item['grams']   !== null ? (int)$item['grams']   : '' ?></td>
                    <td class="num"><?= $item['kcal']    !== null ? (int)$item['kcal']    : '' ?></td>
                    <td class="num" style="white-space:nowrap">
                        <?= $item['protein'] !== null ? (int)$item['protein'] : '?' ?> /
                        <?= $item['fat']     !== null ? (int)$item['fat']     : '?' ?> /
                        <?= $item['carbs']   !== null ? (int)$item['carbs']   : '?' ?>
                    </td>
                    <td class="num"><?= $item['price'] !== null ? number_format((float)$item['price'], 2) : '' ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endforeach; ?>

    <div class="flex gap-2">
        <form method="post">
            <input type="hidden" name="confirm" value="1">
            <button type="submit" class="btn btn-primary">
                &#x2713; Сохранить в <?= htmlspecialchars($tpl['label'] ?? 'шаблон') ?>
            </button>
        </form>
        <a href="import.php?id=<?= $tplId ?>" class="btn btn-outline">Загрузить другой файл</a>
        <a href="templates.php" class="btn btn-outline">Отмена</a>
    </div>
    <?php endif; ?>
</div>
</body>
</html>
