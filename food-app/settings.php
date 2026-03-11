<?php
require_once __DIR__ . '/src/auth.php';
require_once __DIR__ . '/src/db.php';
require_auth();

$settings    = get_kitchen_settings();
$departments = get_all_departments();
$aySettings  = get_academic_year_settings();
$saved       = !empty($_GET['saved']);

// Текущее количество шаблонов (длина цикла) по каждому отделению
$cycleLengths = [];
foreach ($departments as $d) {
    $cycleLengths[$d['id']] = get_cycle_length($d['code']);
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Настройки пищеблока</title>
    <link rel="stylesheet" href="assets/style.css">
    <style>
        .dept-card {
            background: #fff;
            border: 1px solid var(--border-light);
            margin-bottom: 12px;
            transition: border-color .15s;
        }
        .dept-card.enabled {
            border-color: var(--orange);
            border-left: 4px solid var(--orange);
        }
        .dept-header {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 16px;
            cursor: pointer;
            user-select: none;
        }
        .dept-header input[type=checkbox] {
            width: 18px;
            height: 18px;
            accent-color: var(--orange);
        }
        .dept-title {
            flex: 1;
            font-size: 0.95rem;
            text-transform: uppercase;
            letter-spacing: 0.06em;
        }
        .dept-note {
            font-size: 0.78rem;
            color: var(--muted);
            font-style: italic;
        }
        .dept-body {
            display: none;
            padding: 0 16px 16px 48px;
            border-top: 1px solid var(--border-light);
        }
        .dept-card.enabled .dept-body { display: block; }
        .dept-body .form-row {
            display: flex;
            gap: 16px;
            margin-bottom: 12px;
            flex-wrap: wrap;
        }
        .dept-body .form-row .field {
            flex: 1;
            min-width: 180px;
        }
        .dept-body .form-row .field label {
            display: block;
            font-size: 0.78rem;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: var(--muted);
            margin-bottom: 4px;
        }
        .dept-body .form-row .field input[type=text] {
            width: 100%;
            padding: 6px 10px;
            font-family: Georgia, serif;
            font-size: 0.9rem;
            border: 1px solid var(--border-light);
            border-radius: var(--radius);
            color: var(--text);
        }
        .dept-body .form-row .field input[type=text]:focus {
            outline: none;
            border-color: var(--orange);
        }
        .wd-group {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }
        .wd-label {
            display: flex;
            align-items: center;
            gap: 4px;
            font-size: 0.85rem;
            cursor: pointer;
        }
        .wd-label input { accent-color: var(--orange); }
        .opt-row {
            display: flex;
            gap: 24px;
            flex-wrap: wrap;
            margin-bottom: 8px;
        }
        .opt-label {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 0.88rem;
            cursor: pointer;
        }
        .opt-label input { accent-color: var(--orange); }
        .opt-label.disabled { opacity: 0.5; pointer-events: none; }
        .suffix-display {
            font-size: 0.82rem;
            color: var(--muted);
            font-family: monospace;
        }
        .custom-dept-form {
            background: var(--bg-panel);
            padding: 16px;
            margin-top: 8px;
            display: none;
        }
        .custom-dept-form.show { display: block; }
        .badge-custom {
            font-size: 0.7rem;
            background: var(--orange-pale);
            color: var(--orange);
            padding: 2px 8px;
            border-radius: 2px;
            text-transform: uppercase;
            letter-spacing: 0.06em;
        }
        .btn-delete-dept {
            background: none;
            border: none;
            color: var(--error);
            cursor: pointer;
            font-size: 0.82rem;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            padding: 4px 8px;
        }
        .btn-delete-dept:hover { text-decoration: underline; }

        /* Каникулы */
        .vac-year-select { font-size: 0.92rem; padding: 6px 10px; border: 1px solid var(--border-light); border-radius: var(--radius); font-family: Georgia, serif; }
        .vac-table { width: 100%; border-collapse: collapse; margin-top: 12px; }
        .vac-table th { text-align: left; font-size: 0.78rem; text-transform: uppercase; letter-spacing: 0.06em; color: var(--muted); padding: 6px 8px; border-bottom: 1px solid var(--border-light); }
        .vac-table td { padding: 6px 8px; border-bottom: 1px solid var(--border-light); font-size: 0.9rem; }
        .vac-table input[type=text], .vac-table input[type=date] {
            width: 100%; padding: 4px 8px; font-family: Georgia, serif; font-size: 0.88rem;
            border: 1px solid var(--border-light); border-radius: var(--radius); color: var(--text);
        }
        .vac-table input:focus { outline: none; border-color: var(--orange); }
        .vac-table .btn-del-vac { background: none; border: none; color: var(--error); cursor: pointer; font-size: 1.1rem; padding: 2px 6px; }
        .vac-table .btn-del-vac:hover { background: #fee; border-radius: 2px; }
        .vac-actions { display: flex; gap: 12px; margin-top: 12px; flex-wrap: wrap; }
        .ay-fields { display: flex; gap: 16px; flex-wrap: wrap; align-items: end; margin-bottom: 12px; }
        .ay-fields .field { min-width: 120px; }
        .ay-fields .field label { display: block; font-size: 0.78rem; text-transform: uppercase; letter-spacing: 0.06em; color: var(--muted); margin-bottom: 4px; }
        .ay-fields .field input[type=text] { width: 80px; padding: 6px 10px; font-family: Georgia, serif; font-size: 0.9rem; border: 1px solid var(--border-light); border-radius: var(--radius); }
    </style>
</head>
<body>
<?php include __DIR__ . '/src/header.php'; ?>
<div class="container">
    <h1 class="page-title">Настройки пищеблока</h1>

    <?php if ($saved): ?>
    <div class="alert alert-success">Настройки сохранены.</div>
    <?php endif; ?>

    <div id="msg"></div>

    <!-- Название организации -->
    <div class="panel">
        <div class="panel-title">Образовательная организация</div>
        <div class="form-group">
            <label>Название</label>
            <input type="text" id="org-name" class="form-control"
                   value="<?= htmlspecialchars($settings['org_name']) ?>"
                   placeholder="Например: ГБОУ Школа №1234">
        </div>
    </div>

    <!-- Отделения -->
    <div class="panel">
        <div class="panel-title">Отделения</div>
        <p class="text-muted mb-2">Отметьте отделения вашей организации и настройте параметры каждого.</p>

        <div id="dept-list">
        <?php foreach ($departments as $d): ?>
            <?php $wd = explode(',', $d['workdays']); ?>
            <div class="dept-card<?= $d['is_enabled'] ? ' enabled' : '' ?>" data-id="<?= $d['id'] ?>">
                <div class="dept-header">
                    <input type="checkbox" class="dept-toggle" <?= $d['is_enabled'] ? 'checked' : '' ?>>
                    <span class="dept-title"><?= htmlspecialchars($d['label']) ?></span>
                    <?php if (!$d['is_builtin']): ?>
                        <span class="badge-custom">кастомное</span>
                        <button type="button" class="btn-delete-dept" data-id="<?= $d['id'] ?>" title="Удалить отделение">&times; Удалить</button>
                    <?php endif; ?>
                    <?php if ($d['note']): ?>
                        <span class="dept-note"><?= htmlspecialchars($d['note']) ?></span>
                    <?php endif; ?>
                </div>
                <div class="dept-body">
                    <!-- Названия -->
                    <div class="form-row">
                        <div class="field">
                            <label>Полное название</label>
                            <input type="text" class="inp-label" value="<?= htmlspecialchars($d['label']) ?>">
                        </div>
                        <div class="field">
                            <label>Краткое (для вкладок)</label>
                            <input type="text" class="inp-label-short" value="<?= htmlspecialchars($d['label_short']) ?>">
                        </div>
                        <div class="field">
                            <label>Отд./корпус</label>
                            <input type="text" class="inp-dept-name" value="<?= htmlspecialchars($d['dept_name']) ?>" placeholder="Корпус 1">
                        </div>
                    </div>

                    <!-- Рабочие дни -->
                    <div class="form-row">
                        <div class="field">
                            <label>Рабочие дни</label>
                            <div class="wd-group">
                                <?php
                                $dayNames = ['Пн','Вт','Ср','Чт','Пт','Сб','Вс'];
                                for ($i = 1; $i <= 7; $i++):
                                ?>
                                <label class="wd-label">
                                    <input type="checkbox" class="wd-chk" value="<?= $i ?>"
                                           <?= in_array((string)$i, $wd) ? 'checked' : '' ?>>
                                    <?= $dayNames[$i-1] ?>
                                </label>
                                <?php endfor; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Длина цикла -->
                    <div class="form-row">
                        <div class="field" style="max-width:200px">
                            <label>Дней в цикле</label>
                            <input type="number" class="inp-cycle-len" min="0" max="99"
                                   value="<?= $cycleLengths[$d['id']] ?>"
                                   style="width:80px;padding:6px 10px;font-family:Georgia,serif;font-size:0.9rem;border:1px solid var(--border-light);border-radius:var(--radius);color:var(--text)">
                            <span class="text-muted" style="font-size:0.78rem;margin-left:6px">шаблонов: <?= $cycleLengths[$d['id']] ?></span>
                        </div>
                    </div>

                    <!-- Опции -->
                    <div class="opt-row">
                        <label class="opt-label<?= $d['code'] === 'preschool' ? ' disabled' : '' ?>">
                            <input type="checkbox" class="chk-boarding"
                                   <?= $d['is_boarding'] ? 'checked' : '' ?>
                                   <?= $d['code'] === 'preschool' ? 'disabled checked' : '' ?>>
                            Интернат (доп. приёмы пищи)
                        </label>
                        <label class="opt-label">
                            <input type="checkbox" class="chk-publish"
                                   <?= $d['publish_xlsx'] ? 'checked' : '' ?>>
                            Публикация файлов меню
                        </label>
                        <label class="opt-label">
                            <input type="checkbox" class="chk-ignore-vac"
                                   <?= !empty($d['ignore_vacations']) ? 'checked' : '' ?>>
                            Без каникул (круглый год)
                        </label>
                    </div>

                    <!-- Постфикс -->
                    <div class="form-row">
                        <div class="field" style="max-width:300px">
                            <label>Постфикс файлов</label>
                            <?php if ($d['is_builtin']): ?>
                                <span class="suffix-display"><?= $d['file_suffix'] ?: '(без постфикса)' ?> &rarr; ГГГГ-ММ-ДД<?= htmlspecialchars($d['file_suffix']) ?>.xlsx</span>
                            <?php else: ?>
                                <input type="text" class="inp-suffix" value="<?= htmlspecialchars($d['file_suffix']) ?>" placeholder="-custom">
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
        </div>

        <!-- Добавить кастомное отделение -->
        <div class="mt-2">
            <button type="button" class="btn btn-outline btn-sm" id="btn-add-dept">+ Добавить отделение</button>
        </div>
        <div class="custom-dept-form" id="add-dept-form">
            <div class="form-row">
                <div class="field">
                    <label>Код (латиница, без пробелов)</label>
                    <input type="text" id="new-dept-code" class="form-control" placeholder="nursery" pattern="[a-z0-9_]+">
                </div>
                <div class="field">
                    <label>Название</label>
                    <input type="text" id="new-dept-label" class="form-control" placeholder="Ясельная группа">
                </div>
                <div class="field">
                    <label>Постфикс файлов</label>
                    <input type="text" id="new-dept-suffix" class="form-control" placeholder="-nursery">
                </div>
            </div>
            <div class="mt-1">
                <button type="button" class="btn btn-primary btn-sm" id="btn-confirm-add">Создать</button>
                <button type="button" class="btn btn-outline btn-sm" id="btn-cancel-add" style="margin-left:8px">Отмена</button>
            </div>
        </div>
    </div>

    <!-- Учебный год и каникулы -->
    <div class="panel">
        <div class="panel-title">Учебный год и каникулы</div>

        <div class="ay-fields">
            <div class="field">
                <label>Начало уч. года (ММ-ДД)</label>
                <input type="text" id="ay-start" value="<?= htmlspecialchars($aySettings['academic_year_start']) ?>" placeholder="09-01">
            </div>
            <div class="field">
                <label>Конец уч. года (ММ-ДД)</label>
                <input type="text" id="ay-end" value="<?= htmlspecialchars($aySettings['academic_year_end']) ?>" placeholder="05-31">
            </div>
            <div class="field" style="min-width:auto">
                <label class="opt-label" style="margin-top:20px">
                    <input type="checkbox" id="ay-reset" <?= $aySettings['reset_cycle_after_vacation'] ? 'checked' : '' ?>>
                    Сбрасывать цикл после каникул
                </label>
            </div>
        </div>

        <hr style="border:none; border-top:1px solid var(--border-light); margin: 16px 0;">

        <div style="display:flex; align-items:center; gap:12px; margin-bottom:8px;">
            <label style="font-size:0.88rem;">Учебный год:</label>
            <select class="vac-year-select" id="vac-year"></select>
        </div>

        <table class="vac-table">
            <thead>
                <tr><th style="width:35%">Название</th><th style="width:25%">С</th><th style="width:25%">По</th><th style="width:15%"></th></tr>
            </thead>
            <tbody id="vac-body"></tbody>
        </table>

        <div class="vac-actions">
            <button type="button" class="btn btn-outline btn-sm" id="btn-add-vac">+ Добавить каникулы</button>
            <button type="button" class="btn btn-outline btn-sm" id="btn-fill-default">Типовые каникулы РФ</button>
        </div>
    </div>

    <!-- Сохранить -->
    <div class="mt-2" style="text-align:right">
        <button type="button" class="btn btn-primary" id="btn-save">Сохранить настройки</button>
    </div>
</div>

<script>
(function() {
    var msgEl = document.getElementById('msg');

    // Тоггл карточек
    document.getElementById('dept-list').addEventListener('change', function(e) {
        if (e.target.classList.contains('dept-toggle')) {
            var card = e.target.closest('.dept-card');
            card.classList.toggle('enabled', e.target.checked);
        }
    });

    // Добавление кастомного отделения
    var addForm = document.getElementById('add-dept-form');
    document.getElementById('btn-add-dept').addEventListener('click', function() {
        addForm.classList.toggle('show');
    });
    document.getElementById('btn-cancel-add').addEventListener('click', function() {
        addForm.classList.remove('show');
    });
    document.getElementById('btn-confirm-add').addEventListener('click', function() {
        var code   = document.getElementById('new-dept-code').value.trim();
        var label  = document.getElementById('new-dept-label').value.trim();
        var suffix = document.getElementById('new-dept-suffix').value.trim();
        if (!code || !label) { alert('Укажите код и название'); return; }
        if (!/^[a-z0-9_]+$/.test(code)) { alert('Код: только строчные латинские буквы, цифры, _'); return; }
        apiPost({action: 'add_department', code: code, label: label, file_suffix: suffix}, function(r) {
            if (r.ok) location.reload();
            else showMsg(r.error || 'Ошибка', true);
        });
    });

    // Удаление кастомного отделения
    document.getElementById('dept-list').addEventListener('click', function(e) {
        var btn = e.target.closest('.btn-delete-dept');
        if (!btn) return;
        if (!confirm('Удалить это отделение?')) return;
        apiPost({action: 'delete_department', id: parseInt(btn.dataset.id)}, function(r) {
            if (r.ok) location.reload();
            else showMsg(r.error || 'Ошибка', true);
        });
    });

    // Сохранение
    document.getElementById('btn-save').addEventListener('click', function() {
        var deps = [];
        document.querySelectorAll('.dept-card').forEach(function(card) {
            var wdChecks = card.querySelectorAll('.wd-chk:checked');
            var workdays = Array.from(wdChecks).map(function(c) { return parseInt(c.value); });
            var suffixInput = card.querySelector('.inp-suffix');
            var ignoreVacChk = card.querySelector('.chk-ignore-vac');
            var cycleLenInput = card.querySelector('.inp-cycle-len');
            deps.push({
                id:               parseInt(card.dataset.id),
                is_enabled:       card.querySelector('.dept-toggle').checked ? 1 : 0,
                label:            card.querySelector('.inp-label').value,
                label_short:      card.querySelector('.inp-label-short').value,
                dept_name:        card.querySelector('.inp-dept-name').value,
                workdays:         workdays.join(','),
                is_boarding:      card.querySelector('.chk-boarding').checked ? 1 : 0,
                publish_xlsx:     card.querySelector('.chk-publish').checked ? 1 : 0,
                ignore_vacations: ignoreVacChk && ignoreVacChk.checked ? 1 : 0,
                file_suffix:      suffixInput ? suffixInput.value : undefined,
                cycle_length:     cycleLenInput ? parseInt(cycleLenInput.value) || 0 : undefined
            });
        });
        apiPost({
            action:   'save_settings',
            org_name: document.getElementById('org-name').value,
            academic_year_start: document.getElementById('ay-start').value.trim(),
            academic_year_end:   document.getElementById('ay-end').value.trim(),
            reset_cycle_after_vacation: document.getElementById('ay-reset').checked ? 1 : 0,
            departments: deps
        }, function(r) {
            if (r.ok) {
                var msg = 'Настройки сохранены.';
                if (r.sync) {
                    var parts = [];
                    for (var code in r.sync) {
                        var s = r.sync[code];
                        if (s.added > 0) parts.push(code + ': +' + s.added + ' шабл.');
                        if (s.removed > 0) parts.push(code + ': −' + s.removed + ' шабл.');
                    }
                    if (parts.length > 0) msg += ' Шаблоны: ' + parts.join(', ') + '.';
                }
                showMsg(msg, false);
                // Обновить отображение текущего количества
                if (r.sync) location.reload();
                else window.scrollTo({top: 0, behavior: 'smooth'});
            } else {
                showMsg(r.error || 'Ошибка сохранения', true);
            }
        });
    });

    function showMsg(text, isError) {
        msgEl.innerHTML = '<div class="alert ' + (isError ? 'alert-error' : 'alert-success') + '">' + text + '</div>';
        setTimeout(function() { msgEl.innerHTML = ''; }, 4000);
    }

    // ─── Каникулы ───────────────────────────────────────────────
    var vacYear = document.getElementById('vac-year');
    var vacBody = document.getElementById('vac-body');

    // Заполнить список учебных годов
    (function() {
        var now = new Date();
        var curYear = now.getFullYear();
        var m = now.getMonth() + 1; // 1-12
        // Текущий учебный год
        var startY = m >= 8 ? curYear : curYear - 1;
        for (var y = startY + 1; y >= startY - 2; y--) {
            var opt = document.createElement('option');
            opt.value = y + '-' + (y + 1);
            opt.textContent = y + '-' + (y + 1);
            vacYear.appendChild(opt);
        }
        vacYear.value = startY + '-' + (startY + 1);
        loadVacations();
    })();

    vacYear.addEventListener('change', loadVacations);

    function loadVacations() {
        apiPost({action: 'get_vacations', academic_year: vacYear.value}, function(r) {
            if (!r.ok) return;
            renderVacations(r.vacations);
        });
    }

    function renderVacations(list) {
        vacBody.innerHTML = '';
        if (!list || list.length === 0) {
            vacBody.innerHTML = '<tr><td colspan="4" style="color:var(--muted);text-align:center;padding:16px">Каникулы не заданы</td></tr>';
            return;
        }
        list.forEach(function(v) {
            var tr = document.createElement('tr');
            tr.dataset.id = v.id;
            tr.innerHTML =
                '<td><input type="text" class="vac-label" value="' + escHtml(v.label) + '"></td>' +
                '<td><input type="date" class="vac-from" value="' + v.date_from + '"></td>' +
                '<td><input type="date" class="vac-to" value="' + v.date_to + '"></td>' +
                '<td style="text-align:right"><button class="btn-del-vac" title="Удалить">&times;</button>' +
                '<button class="btn btn-outline btn-sm vac-save-btn" style="margin-left:4px;padding:2px 8px;font-size:0.78rem">ОК</button></td>';
            vacBody.appendChild(tr);
        });
    }

    // Делегирование: удаление и сохранение строки
    vacBody.addEventListener('click', function(e) {
        var btn = e.target;
        var tr = btn.closest('tr');
        if (!tr) return;
        var id = parseInt(tr.dataset.id);

        if (btn.classList.contains('btn-del-vac')) {
            if (!confirm('Удалить эти каникулы?')) return;
            apiPost({action: 'delete_vacation', id: id}, function(r) {
                if (r.ok) loadVacations();
                else showMsg(r.error || 'Ошибка', true);
            });
        }

        if (btn.classList.contains('vac-save-btn')) {
            apiPost({
                action: 'update_vacation',
                id: id,
                label:     tr.querySelector('.vac-label').value,
                date_from: tr.querySelector('.vac-from').value,
                date_to:   tr.querySelector('.vac-to').value
            }, function(r) {
                if (r.ok) showMsg('Сохранено', false);
                else showMsg(r.error || 'Ошибка', true);
            });
        }
    });

    // Добавить каникулы
    document.getElementById('btn-add-vac').addEventListener('click', function() {
        var label = prompt('Название каникул:', 'Каникулы');
        if (!label) return;
        var parts = vacYear.value.split('-');
        apiPost({
            action: 'add_vacation',
            academic_year: vacYear.value,
            label: label,
            date_from: parts[0] + '-10-28',
            date_to: parts[0] + '-11-05'
        }, function(r) {
            if (r.ok) loadVacations();
            else showMsg(r.error || 'Ошибка', true);
        });
    });

    // Типовые каникулы РФ
    document.getElementById('btn-fill-default').addEventListener('click', function() {
        if (!confirm('Добавить типовые каникулы для ' + vacYear.value + '?\n(Существующие не удаляются)')) return;
        apiPost({action: 'fill_default_vacations', academic_year: vacYear.value}, function(r) {
            if (r.ok) {
                renderVacations(r.vacations);
                showMsg('Типовые каникулы добавлены', false);
            } else {
                showMsg(r.error || 'Ошибка', true);
            }
        });
    });

    function escHtml(s) {
        var d = document.createElement('div');
        d.textContent = s;
        return d.innerHTML;
    }

    function apiPost(data, cb) {
        var xhr = new XMLHttpRequest();
        xhr.open('POST', 'api/save-settings.php');
        xhr.setRequestHeader('Content-Type', 'application/json');
        xhr.onload = function() {
            try { cb(JSON.parse(xhr.responseText)); }
            catch(e) { cb({ok:false, error: 'Ошибка сервера'}); }
        };
        xhr.onerror = function() { cb({ok:false, error: 'Сетевая ошибка'}); };
        xhr.send(JSON.stringify(data));
    }
})();
</script>
</body>
</html>
