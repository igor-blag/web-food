<?php
require_once __DIR__ . '/src/auth.php';
require_once __DIR__ . '/src/db.php';
require_once __DIR__ . '/src/importer.php';
require_once __DIR__ . '/vendor/autoload.php';
require_auth();

use PhpOffice\PhpSpreadsheet\IOFactory;

$results = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_FILES['xlsx']['name'][0])) {
    $files = $_FILES['xlsx'];
    $count = count($files['name']);

    for ($i = 0; $i < $count; $i++) {
        $fname = $files['name'][$i];

        if ($files['error'][$i] !== UPLOAD_ERR_OK) {
            $results[] = ['file'=>$fname,'status'=>'error','msg'=>'Ошибка загрузки файла'];
            continue;
        }

        try {
            // Определяем school_type из имени файла
            if (preg_match('/-sm\.xlsx$/i', $fname)) {
                $schoolType = 'sm';
            } elseif (preg_match('/-ss\.xlsx$/i', $fname)) {
                $schoolType = 'ss';
            } else {
                $schoolType = 'main';
            }

            // Читаем название вкладки
            $spreadsheet = IOFactory::load($files['tmp_name'][$i]);
            $sheetTitle  = $spreadsheet->getActiveSheet()->getTitle();

            // Извлекаем номер дня из названия вкладки (напр. "День 11", "11 день", "11")
            preg_match('/(\d+)/', $sheetTitle, $m);
            $dayNum = isset($m[1]) ? (int)$m[1] : null;

            if (!$dayNum || $dayNum < 1 || $dayNum > 14) {
                $results[] = [
                    'file'  => $fname,
                    'status'=> 'error',
                    'msg'   => 'Не определён номер дня из вкладки «' . htmlspecialchars($sheetTitle) . '» (ожидается 1–14)',
                ];
                continue;
            }

            // Находим шаблон с учётом school_type
            $s = db()->prepare('SELECT id, label FROM menu_templates WHERE day_number = ? AND school_type = ?');
            $s->execute([$dayNum, $schoolType]);
            $tpl = $s->fetch();

            if (!$tpl) {
                $results[] = ['file'=>$fname,'status'=>'error','msg'=>'Шаблон «День '.$dayNum.'» ('.$schoolType.') не найден в базе'];
                continue;
            }

            // Парсим и сохраняем
            $items = parse_menu_xlsx($files['tmp_name'][$i]);
            save_template_items((int)$tpl['id'], $items);

            $results[] = [
                'file'   => $fname,
                'status' => 'success',
                'msg'    => 'Импортировано в «' . $tpl['label'] . '» (' . $schoolType . '): ' . count($items) . ' блюд',
                'tpl_id' => $tpl['id'],
            ];
        } catch (\Exception $e) {
            $results[] = ['file'=>$fname,'status'=>'error','msg'=>$e->getMessage()];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Массовый импорт — Меню</title>
    <link rel="stylesheet" href="assets/style.css">
    <style>
        .drop-zone {
            border: 3px dashed var(--border-light);
            border-radius: 4px;
            padding: 48px 24px;
            text-align: center;
            cursor: pointer;
            transition: border-color .2s, background .2s;
            background: #fff;
        }
        .drop-zone:hover,
        .drop-zone.drag-over {
            border-color: var(--orange);
            background: var(--orange-pale);
        }
        .drop-zone .drop-icon {
            font-size: 2.5rem;
            color: var(--orange);
            line-height: 1;
            margin-bottom: 12px;
        }
        .drop-zone .drop-title {
            font-size: 1.05rem;
            color: var(--black);
            margin-bottom: 6px;
        }
        .drop-zone .drop-sub {
            font-size: 0.82rem;
            color: var(--muted);
        }
        .drop-zone .file-list {
            margin-top: 16px;
            font-size: 0.85rem;
            color: var(--text);
            text-align: left;
            display: inline-block;
        }
        .drop-zone .file-list li {
            list-style: none;
            padding: 2px 0;
        }
        .drop-zone .file-list li::before { content: '✦ '; color: var(--orange); }
        .result-row-success { color: var(--success); }
        .result-row-error   { color: var(--error); }
    </style>
</head>
<body>
<?php include __DIR__ . '/src/header.php'; ?>
<div class="container">
    <div class="flex items-center justify-between mb-2">
        <h1 class="page-title" style="border:none;margin:0">Массовый импорт меню</h1>
        <a href="templates.php" class="btn btn-outline btn-sm">&larr; Шаблоны</a>
    </div>

    <div class="panel" style="max-width:680px">
        <div class="panel-title">Как это работает</div>
        <p class="text-muted" style="font-size:.88rem">
            Перетащите сюда сразу несколько файлов меню (.xlsx).<br>
            Программа определит нужный шаблон по названию вкладки в файле —
            например, вкладка <strong>«День 11»</strong> заполнит шаблон № 11.<br>
            Тип школы определяется по имени файла:
            <strong>-sm.xlsx</strong> → начальная,
            <strong>-ss.xlsx</strong> → старшая,
            иначе → основная.<br>
            Существующие данные шаблона будут заменены.
        </p>
    </div>

    <form method="post" enctype="multipart/form-data" id="importForm">
        <div class="panel">
            <div class="drop-zone" id="dropZone">
                <div class="drop-icon">&#x2B06;</div>
                <div class="drop-title">Перетащите файлы .xlsx сюда</div>
                <div class="drop-sub">или кликните для выбора файлов</div>
                <ul class="file-list" id="fileList"></ul>
                <input type="file" name="xlsx[]" id="fileInput"
                       multiple accept=".xlsx" style="display:none">
            </div>

            <div class="mt-2 flex gap-2" id="submitWrap" style="display:none!important">
                <button type="submit" class="btn btn-primary" id="submitBtn">
                    &#x2713; Импортировать выбранные файлы
                </button>
                <button type="button" class="btn btn-outline" id="clearBtn">Очистить</button>
            </div>
        </div>
    </form>

    <?php if (!empty($results)): ?>
    <div class="panel">
        <div class="panel-title">Результаты импорта</div>
        <table class="menu-table">
            <thead>
                <tr>
                    <th style="text-align:left">Файл</th>
                    <th style="text-align:left">Результат</th>
                    <th style="width:100px"></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($results as $r): ?>
                <tr class="result-row-<?= $r['status'] ?>">
                    <td><?= htmlspecialchars($r['file']) ?></td>
                    <td>
                        <?= $r['status'] === 'success' ? '✓' : '✗' ?>
                        <?= htmlspecialchars($r['msg']) ?>
                    </td>
                    <td class="center">
                        <?php if ($r['status'] === 'success'): ?>
                            <a href="template-edit.php?id=<?= $r['tpl_id'] ?>"
                               class="btn btn-outline btn-sm">Посмотреть</a>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php
        $ok  = count(array_filter($results, fn($r) => $r['status'] === 'success'));
        $err = count($results) - $ok;
        ?>
        <p class="text-muted mt-1" style="font-size:.85rem">
            Успешно: <strong style="color:var(--success)"><?= $ok ?></strong>
            &nbsp;|&nbsp;
            Ошибок: <strong style="color:var(--error)"><?= $err ?></strong>
        </p>
    </div>
    <?php endif; ?>
</div>

<script>
(function () {
    const zone   = document.getElementById('dropZone');
    const input  = document.getElementById('fileInput');
    const list   = document.getElementById('fileList');
    const wrap   = document.getElementById('submitWrap');
    const clear  = document.getElementById('clearBtn');
    const form   = document.getElementById('importForm');

    zone.addEventListener('click', function(e) {
        if (e.target !== clear) input.click();
    });

    ['dragenter','dragover'].forEach(ev =>
        zone.addEventListener(ev, function(e) {
            e.preventDefault();
            zone.classList.add('drag-over');
        })
    );
    ['dragleave','dragend'].forEach(ev =>
        zone.addEventListener(ev, function() {
            zone.classList.remove('drag-over');
        })
    );

    zone.addEventListener('drop', function(e) {
        e.preventDefault();
        zone.classList.remove('drag-over');
        const dt = new DataTransfer();
        Array.from(e.dataTransfer.files)
            .filter(f => f.name.endsWith('.xlsx'))
            .forEach(f => dt.items.add(f));
        input.files = dt.files;
        updateList(dt.files);
    });

    input.addEventListener('change', function() {
        updateList(input.files);
    });

    clear.addEventListener('click', function(e) {
        e.stopPropagation();
        input.value = '';
        list.innerHTML = '';
        wrap.style.display = 'none';
    });

    function updateList(files) {
        list.innerHTML = '';
        if (!files.length) { wrap.style.display = 'none'; return; }
        Array.from(files).forEach(function(f) {
            const li = document.createElement('li');
            li.textContent = f.name;
            list.appendChild(li);
        });
        wrap.style.removeProperty('display');
    }
})();
</script>
</body>
</html>
