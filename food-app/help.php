<?php
require_once __DIR__ . '/src/auth.php';
require_once __DIR__ . '/src/db.php';
require_auth();
$orgName = get_org_name();
?><!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Справка — <?= htmlspecialchars($orgName) ?></title>
<link rel="stylesheet" href="assets/style.css">
<style>
.help-section { margin-bottom: 36px; }
.help-section h2 {
    font-size: 1rem;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    border-left: 4px solid var(--orange);
    padding-left: 12px;
    margin-bottom: 14px;
    color: var(--orange-dark);
}
.help-section p,
.help-section li { margin-bottom: 8px; color: var(--text); line-height: 1.6; }
.help-section ul,
.help-section ol { padding-left: 22px; }
.help-note {
    background: var(--bg-note);
    border-left: 3px solid var(--orange);
    padding: 10px 14px;
    margin-top: 10px;
    font-size: 0.9rem;
}
.help-step {
    display: flex;
    gap: 12px;
    align-items: flex-start;
    margin-bottom: 10px;
}
.help-step-num {
    background: var(--orange);
    color: #fff;
    width: 24px;
    height: 24px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.8rem;
    flex-shrink: 0;
    margin-top: 2px;
}
</style>
</head>
<body>
<?php include __DIR__ . '/src/header.php'; ?>
<div class="container">
    <div class="page-title">Справка</div>

    <!-- 1. Первый запуск -->
    <div class="help-section">
        <h2>1. Первый запуск и вход</h2>
        <p>При первом открытии системы нажмите <strong>«Настроить»</strong>: введите адрес электронной почты, получите шестизначный код, придумайте логин и пароль.</p>
        <p>При последующих входах используйте страницу <strong>«Войти»</strong>. Если пароль утерян — воспользуйтесь ссылкой <strong>«Забыли пароль?»</strong>: на почту придёт код для сброса.</p>
    </div>

    <!-- 2. Шаблоны меню -->
    <div class="help-section">
        <h2>2. Шаблоны меню</h2>
        <p>Шаблон — это набор блюд для одного дня цикла. Система работает по <strong>цикличному меню</strong>: например, 10-дневный цикл повторяется каждые 10 учебных дней.</p>
        <div class="help-step"><div class="help-step-num">1</div><div>Перейдите в раздел <strong>«Шаблоны»</strong> и выберите тип отделения (Начальная / Основная / Старшая).</div></div>
        <div class="help-step"><div class="help-step-num">2</div><div>Нажмите <strong>«Добавить день»</strong>, чтобы добавить новый день в цикл.</div></div>
        <div class="help-step"><div class="help-step-num">3</div><div>Нажмите на название дня (например, «День 1»), чтобы открыть редактор блюд.</div></div>
        <div class="help-step"><div class="help-step-num">4</div><div>Заполните блюда по приёмам пищи: Завтрак, Завтрак 2, Обед. Для интерната дополнительно: Полдник, Ужин, Ужин 2.</div></div>
        <div class="help-step"><div class="help-step-num">5</div><div>Нажмите <strong>«Сохранить»</strong>. Для начальной школы (sm) автоматически пересоздаётся типовое меню.</div></div>
        <div class="help-note">Вместо ручного ввода можно импортировать блюда из файла Excel — кнопка <strong>«Импортировать»</strong> рядом с шаблоном (раздел «Импорт»).</div>
    </div>

    <!-- 3. Календарь -->
    <div class="help-section">
        <h2>3. Календарь питания</h2>
        <p>Здесь назначаются шаблоны на конкретные даты. Родители видят календарь на публичном сайте.</p>
        <div class="help-step"><div class="help-step-num">1</div><div>Откройте <strong>«Календарь»</strong>, выберите тип отделения и месяц.</div></div>
        <div class="help-step"><div class="help-step-num">2</div><div>Отметьте чекбоксами <strong>учебные дни</strong> в сетке месяца. Система автоматически распределяет дни цикла по порядку.</div></div>
        <div class="help-step"><div class="help-step-num">3</div><div>Чтобы начать цикл с нужного дня — кликните на ячейку дня, введите номер в боковой панели и нажмите <strong>«Применить цикл»</strong>.</div></div>
        <div class="help-step"><div class="help-step-num">4</div><div>Кнопка <strong>«Сохранить»</strong> в боковой панели генерирует файл меню (.xlsx) для мониторингового бота.</div></div>
        <div class="help-note">Файлы меню (.xlsx) создаются автоматически каждое утро скриптом планировщика. Если срочно нужен файл — нажмите «Сохранить» вручную.</div>
    </div>

    <!-- 4. Общественный контроль -->
    <div class="help-section">
        <h2>4. Общественный контроль питания</h2>
        <p>Раздел <strong>«ОК питания»</strong> предназначен для заполнения сведений, обязательных для ФИС ФРДО (7 разделов).</p>
        <ul>
            <li><strong>Раздел 1</strong> — ссылка на приказ о создании комиссии.</li>
            <li><strong>Раздел 2</strong> — формы обратной связи: горячая линия, чат, форум.</li>
            <li><strong>Раздел 3</strong> — лечебные и диетические меню (до 4 видов).</li>
            <li><strong>Раздел 4</strong> — анкетирование родителей.</li>
            <li><strong>Раздел 5</strong> — материалы о здоровом питании.</li>
            <li><strong>Раздел 6</strong> — акты и фотоматериалы контрольных мероприятий.</li>
            <li><strong>Раздел 7</strong> — оценка пищевых отходов (выберите уровень).</li>
        </ul>
        <p>В поля URL можно вставить ссылку или загрузить файл (PDF, JPEG, PNG, WebP, до 20 МБ) — кнопка <strong>«📎 Загрузить»</strong>.</p>
        <p>Нажмите <strong>«Сохранить»</strong> — данные запишутся в базу и автоматически обновится файл <code>findex.xlsx</code> для ФИС ФРДО.</p>
        <div class="help-note">Заполненные разделы автоматически появляются на публичной странице <strong>«Общ. контроль»</strong>. Пустые разделы скрыты от посетителей.</div>
    </div>

    <!-- 5. Настройки -->
    <div class="help-section">
        <h2>5. Настройки пищеблока</h2>
        <p>Раздел <strong>«Настройки»</strong> содержит:</p>
        <ul>
            <li><strong>Название ОО</strong> — отображается в шапке публичного сайта и в генерируемых файлах Excel.</li>
            <li><strong>Отделения</strong> — список корпусов / ступеней школы. Включённые отделения отображаются на публичном сайте.</li>
            <li><strong>Каникулы</strong> — периоды, которые пропускаются при автоматическом подсчёте заполненных дней меню.</li>
        </ul>
    </div>

    <!-- 6. Напоминания -->
    <div class="help-section">
        <h2>6. Email-напоминания</h2>
        <p>Каждое утро скрипт проверяет, на сколько рабочих дней вперёд заполнено меню. Если заполненных дней <strong>3 или меньше</strong> — на адрес <code>ADMIN_EMAIL</code> из настроек приходит письмо-напоминание.</p>
        <p>Чтобы напоминания работали, в файле <code>config.php</code> должен быть указан <code>ADMIN_EMAIL</code>.</p>
    </div>

    <!-- 7. Публичный сайт -->
    <div class="help-section">
        <h2>7. Что видят родители</h2>
        <p>Публичный сайт доступен по основному адресу сервера (папка <code>food/</code>). Он содержит:</p>
        <ul>
            <li><strong>Календарь питания</strong> — текущий и будущие месяцы с кликабельными днями.</li>
            <li><strong>Типовое меню</strong> — все дни цикла с составом блюд по приёмам пищи.</li>
            <li><strong>Общественный контроль</strong> — заполненные разделы ФИС ФРДО.</li>
        </ul>
    </div>

</div>
</body>
</html>
