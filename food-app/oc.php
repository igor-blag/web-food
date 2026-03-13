<?php
require_once __DIR__ . '/src/auth.php';
require_once __DIR__ . '/src/db.php';
require_once __DIR__ . '/config.php';
require_auth();

$oc = get_oc_monitoring();

// Выводит поле с возможностью загрузки файла.
// $accept: строка для атрибута accept (<input type="file">), напр. '.pdf,image/*'
// null   : только ввод URL, без кнопки загрузки
function oc_url_field(string $id, string $label, string $value, ?string $accept = null): void
{
    $isLocalFile = $accept !== null && $value !== '' && str_contains($value, '/oc/');
    $fname = $isLocalFile ? basename(parse_url($value, PHP_URL_PATH)) : '';
    $isImg = $isLocalFile && preg_match('/\.(jpe?g|png|webp)$/i', $fname);
    ?>
    <div class="oc-field">
        <label for="<?= $id ?>"><?= htmlspecialchars($label) ?></label>
        <?php if ($accept !== null): ?>
        <div class="url-upload-row">
            <input type="text" id="<?= $id ?>"
                   value="<?= htmlspecialchars($value) ?>"
                   placeholder="https://…">
            <button type="button" class="btn-upload"
                    data-target="<?= $id ?>"
                    data-accept="<?= htmlspecialchars($accept) ?>">&#128206; Загрузить</button>
        </div>
        <div class="file-badge" id="badge_<?= $id ?>"<?= $fname ? '' : ' style="display:none"' ?>>
            <?php if ($isImg): ?>
                <a href="<?= htmlspecialchars($value) ?>" target="_blank">
                    <img src="<?= htmlspecialchars($value) ?>" alt="<?= htmlspecialchars($fname) ?>">
                </a>
            <?php elseif ($fname): ?>
                <a href="<?= htmlspecialchars($value) ?>" target="_blank">&#128196; <?= htmlspecialchars($fname) ?></a>
            <?php endif; ?>
        </div>
        <?php else: ?>
        <input type="text" id="<?= $id ?>"
               value="<?= htmlspecialchars($value) ?>"
               placeholder="https://…">
        <?php endif; ?>
    </div>
    <?php
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Общественный контроль питания</title>
    <link rel="stylesheet" href="assets/style.css">
    <style>
        .oc-section {
            background: #fff;
            border: 1px solid var(--border-light);
            border-left: 4px solid var(--orange);
            margin-bottom: 16px;
            padding: 16px 20px;
        }
        .oc-section-title {
            font-size: 0.78rem;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: var(--muted);
            margin-bottom: 12px;
        }
        .oc-section-num {
            display: inline-block;
            background: var(--orange);
            color: #fff;
            font-size: 0.72rem;
            font-weight: bold;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            line-height: 20px;
            text-align: center;
            margin-right: 6px;
        }
        .oc-field {
            margin-bottom: 12px;
        }
        .oc-field label {
            display: block;
            font-size: 0.78rem;
            color: var(--muted);
            margin-bottom: 4px;
        }
        .oc-field input[type=text],
        .oc-field input[type=date] {
            width: 100%;
            padding: 7px 10px;
            font-family: Georgia, serif;
            font-size: 0.9rem;
            border: 1px solid var(--border-light);
            border-radius: var(--radius);
            color: var(--text);
            box-sizing: border-box;
        }
        .oc-field input:focus {
            outline: none;
            border-color: var(--orange);
        }
        /* Строка URL + кнопка загрузки */
        .url-upload-row {
            display: flex;
            gap: 8px;
            align-items: center;
        }
        .url-upload-row input[type=text] {
            flex: 1;
            min-width: 0;
        }
        .btn-upload {
            flex-shrink: 0;
            background: var(--orange);
            color: #fff;
            border: none;
            padding: 7px 12px;
            border-radius: var(--radius);
            font-family: Georgia, serif;
            font-size: 0.82rem;
            cursor: pointer;
            white-space: nowrap;
        }
        .btn-upload:hover { background: #c0620a; }
        .btn-upload:disabled { background: #ccc; cursor: default; }
        /* Значок загруженного файла */
        .file-badge {
            margin-top: 6px;
            font-size: 0.82rem;
        }
        .file-badge a {
            color: var(--orange);
            text-decoration: none;
        }
        .file-badge a:hover { text-decoration: underline; }
        .file-badge img {
            display: block;
            max-width: 220px;
            max-height: 140px;
            margin-top: 4px;
            border: 1px solid var(--border-light);
            border-radius: var(--radius);
        }
        /* Диетические меню */
        .diet-row {
            display: flex;
            gap: 10px;
            margin-bottom: 8px;
        }
        .diet-row .diet-type { flex: 0 0 200px; }
        .diet-row .diet-url  { flex: 1; min-width: 0; }
        /* Пищевые отходы */
        .waste-options {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            margin-top: 6px;
        }
        .waste-options label {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 0.9rem;
            cursor: pointer;
            color: var(--text);
        }
        .waste-options input[type=radio] {
            accent-color: var(--orange);
            width: 16px;
            height: 16px;
        }
        /* Шапка документа */
        .header-fields {
            display: flex;
            gap: 16px;
            flex-wrap: wrap;
            margin-bottom: 16px;
        }
        .header-fields .oc-field { flex: 1; min-width: 220px; }
        /* Кнопка скачать */
.actions-row {
            display: flex;
            gap: 12px;
            align-items: center;
            flex-wrap: wrap;
            margin-top: 20px;
        }
        /* Прогресс загрузки */
        .upload-progress {
            display: none;
            font-size: 0.8rem;
            color: var(--muted);
            margin-top: 4px;
        }
    </style>
</head>
<body>
<?php include __DIR__ . '/src/header.php'; ?>
<div class="container">
    <h1 class="page-title">Общественный контроль питания</h1>
    <p class="text-muted mb-2">Заполните поля и нажмите «Сохранить». Файлы загружаются сразу при выборе.</p>

    <div id="msg"></div>

    <!-- Шапка документа -->
    <div class="panel">
        <div class="panel-title">Шапка документа</div>
        <div class="header-fields">
            <div class="oc-field">
                <label for="school_name">Название школы</label>
                <input type="text" id="school_name"
                       value="<?= htmlspecialchars($oc['school_name']) ?>"
                       placeholder="ГБОУ СОШ №…">
            </div>
            <div class="oc-field" style="flex:0 0 180px">
                <label for="report_date">Дата документа</label>
                <input type="date" id="report_date"
                       value="<?= htmlspecialchars($oc['report_date'] ?? '') ?>">
            </div>
        </div>
    </div>

    <!-- Раздел 1 -->
    <div class="oc-section">
        <div class="oc-section-title">
            <span class="oc-section-num">1</span>Положение и приказ о создании комиссии по контролю качества питания
        </div>
        <?php oc_url_field('s1_url', 'Ссылка на файл / загрузить PDF', $oc['s1_url'], '.pdf') ?>
    </div>

    <!-- Раздел 2 -->
    <div class="oc-section">
        <div class="oc-section-title">
            <span class="oc-section-num">2</span>Формы интерактивного взаимодействия с родителями
        </div>
        <div class="oc-field">
            <label for="s2_hotline">«Горячая линия» — телефон, e-mail и т.п.</label>
            <input type="text" id="s2_hotline"
                   value="<?= htmlspecialchars($oc['s2_hotline']) ?>"
                   placeholder="8(812)…, school@mail.ru">
        </div>
        <div class="oc-field">
            <label for="s2_chat_url">Чат — интернет-ссылка</label>
            <input type="text" id="s2_chat_url"
                   value="<?= htmlspecialchars($oc['s2_chat_url']) ?>"
                   placeholder="https://…">
        </div>
        <div class="oc-field">
            <label for="s2_forum_url">Форум — интернет-ссылка</label>
            <input type="text" id="s2_forum_url"
                   value="<?= htmlspecialchars($oc['s2_forum_url']) ?>"
                   placeholder="https://…">
        </div>
    </div>

    <!-- Раздел 3 -->
    <div class="oc-section">
        <div class="oc-section-title">
            <span class="oc-section-num">3</span>Наличие лечебных / диетических меню
        </div>
        <p style="font-size:0.82rem;color:var(--muted);margin-bottom:10px">Укажите до 4 видов диетического меню и ссылки / загрузите PDF.</p>
        <?php
        $diets = [
            ['type' => $oc['s3_diet1_type'], 'url' => $oc['s3_diet1_url'], 'n' => 1],
            ['type' => $oc['s3_diet2_type'], 'url' => $oc['s3_diet2_url'], 'n' => 2],
            ['type' => $oc['s3_diet3_type'], 'url' => $oc['s3_diet3_url'], 'n' => 3],
            ['type' => $oc['s3_diet4_type'], 'url' => $oc['s3_diet4_url'], 'n' => 4],
        ];
        foreach ($diets as $dt):
        ?>
        <div class="diet-row">
            <div class="diet-type">
                <div class="oc-field" style="margin:0">
                    <?php if ($dt['n'] === 1): ?><label>Вид</label><?php endif; ?>
                    <input type="text" id="s3_diet<?= $dt['n'] ?>_type"
                           value="<?= htmlspecialchars($dt['type']) ?>"
                           placeholder="Например: антиаллергенное">
                </div>
            </div>
            <div class="diet-url">
                <?php oc_url_field(
                    "s3_diet{$dt['n']}_url",
                    $dt['n'] === 1 ? 'Ссылка / PDF' : '',
                    $dt['url'],
                    '.pdf'
                ); ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Раздел 4 -->
    <div class="oc-section">
        <div class="oc-section-title">
            <span class="oc-section-num">4</span>Проведение регулярного анкетирования обучающихся и родителей
        </div>
        <div class="oc-field">
            <label for="s4_survey_url">Ссылка на форму анкеты</label>
            <input type="text" id="s4_survey_url"
                   value="<?= htmlspecialchars($oc['s4_survey_url']) ?>"
                   placeholder="https://…">
        </div>
        <?php oc_url_field('s4_results_url', 'Файл с результатами — загрузить PDF или указать ссылку', $oc['s4_results_url'], '.pdf') ?>
    </div>

    <!-- Раздел 5 -->
    <div class="oc-section">
        <div class="oc-section-title">
            <span class="oc-section-num">5</span>Информация для родителей о здоровом питании на сайте ОО
        </div>
        <div class="oc-field">
            <label for="s5_page_url">Ссылка на страницу мероприятия на сайте</label>
            <input type="text" id="s5_page_url"
                   value="<?= htmlspecialchars($oc['s5_page_url']) ?>"
                   placeholder="https://…">
        </div>
        <?php oc_url_field(
            's5_materials_url',
            'Файл с информационными материалами (буклет, брошюра, листовка…) — загрузить PDF/фото или указать ссылку',
            $oc['s5_materials_url'],
            '.pdf,image/*'
        ) ?>
    </div>

    <!-- Раздел 6 -->
    <div class="oc-section">
        <div class="oc-section-title">
            <span class="oc-section-num">6</span>Результаты контрольных мероприятий с участием родителей
        </div>
        <?php oc_url_field(
            's6_acts_url',
            'Акты / протоколы проверок (не реже раза в месяц) — загрузить PDF или указать ссылку',
            $oc['s6_acts_url'],
            '.pdf'
        ) ?>
        <?php oc_url_field(
            's6_photos_url',
            'Фото членов комиссии при проверке — загрузить фото или указать ссылку',
            $oc['s6_photos_url'],
            'image/*'
        ) ?>
    </div>

    <!-- Раздел 7 -->
    <div class="oc-section">
        <div class="oc-section-title">
            <span class="oc-section-num">7</span>Оценка количества пищевых отходов / объёма несъедаемых блюд
        </div>
        <div class="waste-options">
            <?php
            $wasteOptions = [
                '20'   => '20 % и менее',
                '30'   => '30 %',
                '40'   => '40 %',
                '50'   => '50 % и более',
                'none' => 'Не ведётся',
            ];
            foreach ($wasteOptions as $val => $label):
                $checked = ($oc['s7_waste_level'] === $val) ? 'checked' : '';
            ?>
            <label>
                <input type="radio" name="s7_waste_level" value="<?= $val ?>" <?= $checked ?>>
                <?= htmlspecialchars($label) ?>
            </label>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="actions-row">
        <button type="button" class="btn btn-primary" id="btn-save">Сохранить</button>
    </div>
</div>

<script>
(function () {
    var msgEl = document.getElementById('msg');

    // ── Загрузка файла ──────────────────────────────────────────────────────
    document.querySelectorAll('.btn-upload').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var targetId = btn.dataset.target;
            var accept   = btn.dataset.accept;

            var picker = document.createElement('input');
            picker.type   = 'file';
            picker.accept = accept;
            picker.addEventListener('change', function () {
                if (!picker.files[0]) return;
                uploadFile(picker.files[0], targetId, btn);
            });
            picker.click();
        });
    });

    function uploadFile(file, targetId, btn) {
        var fd = new FormData();
        fd.append('file', file);

        btn.disabled    = true;
        btn.textContent = '⏳ Загрузка…';

        var xhr = new XMLHttpRequest();
        xhr.open('POST', 'api/upload-oc.php');
        xhr.onload = function () {
            btn.disabled    = false;
            btn.innerHTML   = '&#128206; Загрузить';
            try {
                var r = JSON.parse(xhr.responseText);
                if (r.ok) {
                    document.getElementById(targetId).value = r.url;
                    updateBadge(targetId, r.url, r.name);
                    showMsg('Файл загружен: ' + esc(r.name), false);
                } else {
                    showMsg(r.error || 'Ошибка загрузки', true);
                }
            } catch (e) {
                showMsg('Ошибка сервера', true);
            }
        };
        xhr.onerror = function () {
            btn.disabled    = false;
            btn.innerHTML   = '&#128206; Загрузить';
            showMsg('Сетевая ошибка', true);
        };
        xhr.send(fd);
    }

    function esc(s) {
        var d = document.createElement('div');
        d.textContent = s;
        return d.innerHTML;
    }

    function updateBadge(targetId, url, origName) {
        var badge = document.getElementById('badge_' + targetId);
        if (!badge) return;
        var isImg = /\.(jpe?g|png|webp)$/i.test(url);
        if (isImg) {
            badge.innerHTML = '<a href="' + esc(url) + '" target="_blank"><img src="' + esc(url) + '" alt="' + esc(origName) + '"></a>';
        } else {
            badge.innerHTML = '<a href="' + esc(url) + '" target="_blank">&#128196; ' + esc(url.split('/').pop()) + '</a>';
        }
        badge.style.display = '';
    }

    // ── Сохранение данных ───────────────────────────────────────────────────
    document.getElementById('btn-save').addEventListener('click', function () {
        var waste = document.querySelector('input[name="s7_waste_level"]:checked');
        var data = {
            school_name:      val('school_name'),
            report_date:      val('report_date'),
            s1_url:           val('s1_url'),
            s2_hotline:       val('s2_hotline'),
            s2_chat_url:      val('s2_chat_url'),
            s2_forum_url:     val('s2_forum_url'),
            s3_diet1_type:    val('s3_diet1_type'),
            s3_diet1_url:     val('s3_diet1_url'),
            s3_diet2_type:    val('s3_diet2_type'),
            s3_diet2_url:     val('s3_diet2_url'),
            s3_diet3_type:    val('s3_diet3_type'),
            s3_diet3_url:     val('s3_diet3_url'),
            s3_diet4_type:    val('s3_diet4_type'),
            s3_diet4_url:     val('s3_diet4_url'),
            s4_survey_url:    val('s4_survey_url'),
            s4_results_url:   val('s4_results_url'),
            s5_page_url:      val('s5_page_url'),
            s5_materials_url: val('s5_materials_url'),
            s6_acts_url:      val('s6_acts_url'),
            s6_photos_url:    val('s6_photos_url'),
            s7_waste_level:   waste ? waste.value : 'none'
        };

        var xhr = new XMLHttpRequest();
        xhr.open('POST', 'api/save-oc.php');
        xhr.setRequestHeader('Content-Type', 'application/json');
        xhr.onload = function () {
            try {
                var r = JSON.parse(xhr.responseText);
                showMsg(r.ok ? 'Сохранено.' : (r.error || 'Ошибка'), !r.ok);
            } catch (e) {
                showMsg('Ошибка сервера', true);
            }
        };
        xhr.onerror = function () { showMsg('Сетевая ошибка', true); };
        xhr.send(JSON.stringify(data));
    });

    function val(id) {
        var el = document.getElementById(id);
        return el ? el.value : '';
    }

    function showMsg(text, isError) {
        var div = document.createElement('div');
        div.className = 'alert ' + (isError ? 'alert-error' : 'alert-success');
        div.textContent = text;
        msgEl.innerHTML = '';
        msgEl.appendChild(div);
        setTimeout(function () { msgEl.innerHTML = ''; }, 4000);
    }
})();
</script>
</body>
</html>
