<?php
require_once __DIR__ . '/src/auth.php';
require_once __DIR__ . '/src/db.php';
require_auth();

$date = $_GET['date'] ?? date('Y-m-d');
// Валидация даты
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || !strtotime($date)) {
    header('Location: calendar.php');
    exit;
}

$enabledDepts = get_enabled_departments();
$validTypes   = array_column($enabledDepts, 'code');
$typeLabels   = array_combine(
    array_column($enabledDepts, 'code'),
    array_column($enabledDepts, 'label')
);
$type = in_array($_GET['type'] ?? '', $validTypes) ? $_GET['type'] : ($validTypes[0] ?? 'sm');

$templates = get_templates($type);
$entry     = get_calendar_day($date, $type);
$msg       = '';
$suffix    = ($type !== 'main') ? "-{$type}" : '';
$xlsFile   = FILES_DIR . $date . $suffix . '.xlsx';
$hasFile   = file_exists($xlsFile);

// ── Сохранение ──────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
        $tplId  = ($_POST['template_id'] !== '' && $_POST['template_id'] !== '0')
                    ? (int)$_POST['template_id'] : null;
        $school = trim($_POST['school'] ?? '');
        $dept   = trim($_POST['dept']   ?? '');
        save_calendar_day($date, $tplId, $school ?: null, $dept ?: null, $type);
        $entry = get_calendar_day($date, $type);
        if ($tplId) {
            require_once __DIR__ . '/src/excel.php';
            $path = generate_menu_excel($date, $type);
            $hasFile = (bool)$path;
            $msg = 'success:Сохранено' . ($path ? ', файл обновлён.' : '.');
        } else {
            $msg = 'success:Сохранено.';
        }

    } elseif ($action === 'delete') {
        delete_calendar_day($date, $type);
        $msg = 'success:День очищен.';
        $entry = null;

    } elseif ($action === 'generate') {
        require_once __DIR__ . '/src/excel.php';
        $path = generate_menu_excel($date, $type);
        if ($path) {
            $hasFile = true;
            $msg = 'success:Файл сгенерирован: ' . basename($path);
        } else {
            $msg = 'error:Не удалось сгенерировать — проверьте шаблон и меню.';
        }

    } elseif ($action === 'delete_file') {
        if ($hasFile) {
            unlink($xlsFile);
            $hasFile = false;
        }
        $msg = 'success:Файл удалён.';
    }
}

[$msgType, $msgText] = $msg ? explode(':', $msg, 2) : ['', ''];

$dateObj = new DateTime($date);
$weekdays = ['','Понедельник','Вторник','Среда','Четверг','Пятница','Суббота','Воскресенье'];
$monthRu  = ['','января','февраля','марта','апреля','мая','июня','июля','августа','сентября','октября','ноября','декабря'];
$dayTitle = $weekdays[(int)$dateObj->format('N')] . ', '
          . $dateObj->format('j') . ' '
          . $monthRu[(int)$dateObj->format('n')] . ' '
          . $dateObj->format('Y');

$calYear  = $dateObj->format('Y');
$calMonth = $dateObj->format('n');

$xlsFilename = $date . $suffix . '.xlsx';
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($dayTitle) ?> — Меню</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
<?php include __DIR__ . '/src/header.php'; ?>
<div class="container">
    <div class="flex items-center justify-between mb-2">
        <h1 class="page-title" style="border:none;margin:0"><?= htmlspecialchars($dayTitle) ?></h1>
        <div class="flex gap-2">
            <span style="font-size:.85rem;color:var(--muted);align-self:center"><?= $typeLabels[$type] ?></span>
            <a href="calendar.php?type=<?= $type ?>&y=<?= $calYear ?>&m=<?= $calMonth ?>" class="btn btn-outline btn-sm">&larr; Календарь</a>
        </div>
    </div>

    <?php if ($msgText): ?>
        <div class="alert alert-<?= $msgType ?>"><?= htmlspecialchars($msgText) ?></div>
    <?php endif; ?>

    <div class="flex gap-2" style="flex-wrap:wrap">
        <!-- Назначение шаблона -->
        <div class="panel" style="flex:1;min-width:280px">
            <div class="panel-title">Назначить меню</div>
            <form method="post">
                <input type="hidden" name="action" value="save">
                <div class="form-group">
                    <label>Шаблон дня</label>
                    <select name="template_id" class="form-control">
                        <option value="0">— выходной / каникулы —</option>
                        <?php foreach ($templates as $t): ?>
                            <option value="<?= $t['id'] ?>"
                                <?= (($entry['template_id'] ?? null) == $t['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($t['label']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Школа</label>
                    <input type="text" name="school" class="form-control"
                           value="<?= htmlspecialchars($entry['school'] ?? '') ?>"
                           placeholder="Название школы">
                </div>
                <div class="form-group">
                    <label>Отд. / корпус</label>
                    <input type="text" name="dept" class="form-control"
                           value="<?= htmlspecialchars($entry['dept'] ?? '') ?>">
                </div>
                <div class="flex gap-2 mt-1">
                    <button type="submit" class="btn btn-primary">Сохранить</button>
                    <?php if ($entry): ?>
                        <button type="submit" name="action" value="delete"
                                class="btn btn-danger"
                                onclick="return confirm('Очистить этот день?')">Очистить</button>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <!-- Файл Excel -->
        <div class="panel" style="flex:1;min-width:240px">
            <div class="panel-title">Excel файл</div>
            <?php if ($hasFile): ?>
                <p class="text-muted mb-2">&#x2713; Файл готов: <strong><?= $xlsFilename ?></strong></p>
                <div class="flex gap-2" style="flex-wrap:wrap">
                    <a href="<?= FILES_URL . htmlspecialchars($xlsFilename) ?>" class="btn btn-primary btn-sm" download>
                        &#x2193; Скачать
                    </a>
                    <form method="post" style="display:inline">
                        <input type="hidden" name="action" value="generate">
                        <button class="btn btn-outline btn-sm"
                                onclick="return confirm('Перегенерировать файл?')">Перегенерировать</button>
                    </form>
                    <form method="post" style="display:inline">
                        <input type="hidden" name="action" value="delete_file">
                        <button class="btn btn-danger btn-sm"
                                onclick="return confirm('Удалить файл?')">Удалить файл</button>
                    </form>
                </div>
            <?php elseif ($entry && $entry['template_id']): ?>
                <p class="text-muted mb-2">Файл ещё не создан.</p>
                <form method="post">
                    <input type="hidden" name="action" value="generate">
                    <button class="btn btn-primary">&#x2295; Сгенерировать</button>
                </form>
            <?php else: ?>
                <p class="text-muted">Сначала назначьте шаблон.</p>
            <?php endif; ?>
        </div>
    </div>

    <!-- Предпросмотр меню -->
    <?php if ($entry && $entry['template_id']): ?>
        <?php $items = get_template_items((int)$entry['template_id']); ?>
        <div class="panel">
            <div class="panel-title">
                Предпросмотр — <?= htmlspecialchars($entry['template_label']) ?>
                <a href="template-edit.php?id=<?= $entry['template_id'] ?>"
                   class="btn btn-outline btn-sm" style="margin-left:12px">Редактировать шаблон</a>
            </div>
            <?php
            $sectionNames = [
                'breakfast'       => 'Завтрак',
                'breakfast2'      => 'Завтрак 2',
                'lunch'           => 'Обед',
                'afternoon_snack' => 'Полдник',
                'dinner'          => 'Ужин',
                'dinner2'         => 'Ужин 2',
            ];
            foreach ($sectionNames as $key => $label):
                if (empty($items[$key])) continue;
            ?>
            <table class="menu-table mb-2">
                <thead>
                    <tr class="section-head"><td colspan="7"><?= $label ?></td></tr>
                    <tr>
                        <th>Раздел</th><th>№ рец.</th><th>Блюдо</th>
                        <th>Выход, г</th><th>Ккал</th><th>Б / Ж / У</th><th>Цена</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items[$key] as $item): ?>
                    <tr>
                        <td><?= htmlspecialchars($item['section'] ?? '') ?></td>
                        <td class="center"><?= htmlspecialchars($item['recipe_num'] ?? '') ?></td>
                        <td><?= htmlspecialchars($item['dish_name'] ?? '') ?></td>
                        <td class="num"><?= $item['grams'] !== null ? (int)$item['grams'] : '' ?></td>
                        <td class="num"><?= $item['kcal']  !== null ? (int)$item['kcal']  : '' ?></td>
                        <td class="num" style="white-space:nowrap">
                            <?= $item['protein']!==null?(int)$item['protein']:'' ?> /
                            <?= $item['fat']    !==null?(int)$item['fat']    :'' ?> /
                            <?= $item['carbs']  !==null?(int)$item['carbs']  :'' ?>
                        </td>
                        <td class="num"><?= $item['price'] !== null ? number_format((float)$item['price'],2) : '' ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
</body>
</html>
