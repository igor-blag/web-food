<?php
// ============================================================
//  Конфигурация приложения — заполнить перед запуском
//  Скопировать в config.php: cp config.example.php config.php
// ============================================================

define('DB_HOST', 'localhost');
define('DB_NAME', 'food_app');
define('DB_USER', 'food_user');
define('DB_PASS', 'your_db_password');

// Секретный ключ сессии — любая случайная строка 32+ символа
define('APP_SECRET', 'замените_на_случайную_строку_32_символа');

// Путь к папке с готовыми Excel файлами (с трейлинг-слешем)
define('FILES_DIR', __DIR__ . '/../food/');

// Публичный URL папки для скачивания (с трейлинг-слешем)
define('FILES_URL', '/food/');

// Удалять файлы старше N дней
define('DELETE_AFTER_DAYS', 14);

// Часовой пояс
define('APP_TZ', 'Europe/Moscow');
date_default_timezone_set(APP_TZ);

// ─ Email ─────────────────────────────────────────────────────
// Адрес отправителя
define('MAIL_FROM',      'noreply@your-school.ru');
define('MAIL_FROM_NAME', 'Мониторинг питания');
// Email ответственного — на него приходят напоминания о незаполненных днях
// Оставьте пустым, чтобы напоминания не отправлялись
define('ADMIN_EMAIL',    '');
// Код подтверждения действует N минут
define('TOKEN_TTL_MIN',  30);
// SMTP (необязательно; если SMTP_HOST пусто — используется PHP mail())
define('SMTP_HOST',    '');
define('SMTP_USER',    '');
define('SMTP_PASS',    '');
define('SMTP_PORT',     587);
define('SMTP_SECURE',  'tls');  // 'tls' или 'ssl'
