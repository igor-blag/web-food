# CLAUDE.md — Мониторинг питания ФЦМПО

## Стек

- **PHP 8.x**, без фреймворков, чистый процедурный код
- **MySQL** через PDO (singleton в `src/db.php`)
- **PhpSpreadsheet** — генерация Excel (composer)
- **PHPMailer** — отправка email (composer)
- `vendor/` — не трогать, управляется composer

## Локальная разработка

```bash
sudo service mysql start
php -S localhost:8000
```

- Админка: http://localhost:8000/food-app/
- Публичный сайт: http://localhost:8000/food/

## Структура проекта

```
web-food-app/
  food-app/        ← Панель администратора (закрытая зона)
  food/            ← Публичный сайт + Excel-файлы для мониторингового бота
```

## food-app/ — панель администратора

### Конфигурация

Все настройки в **`food-app/config.php`**. Перед деплоем заполнить:

```php
DB_HOST, DB_NAME, DB_USER, DB_PASS
APP_SECRET          // случайная строка 32+ символа
FILES_DIR           // абсолютный путь к папке xlsx: __DIR__ . '/../food/'
FILES_URL           // публичный URL: '/food/'
DELETE_AFTER_DAYS   // 14
APP_TZ              // 'Europe/Moscow'
MAIL_FROM, MAIL_FROM_NAME, ADMIN_EMAIL
TOKEN_TTL_MIN       // 30
SMTP_HOST/USER/PASS/PORT/SECURE  // опционально, иначе php mail()
```

### Ключевые файлы

| Файл | Назначение |
|------|-----------|
| `config.php` | Все константы конфигурации |
| `index.php` | Форма входа; если users=0 → редирект на setup.php |
| `setup.php` | Первоначальная настройка (email → код → логин/пароль). Доступен только если таблица users пуста |
| `calendar.php` | Главный рабочий экран: сетка месяца + боковая панель, AJAX через api/save-day.php |
| `templates.php` | Список N-дневного цикла по типу школы |
| `template-edit.php` | Редактор блюд шаблона; при сохранении sm-шаблона автогенерирует tm-файл |
| `day-edit.php` | Детальный редактор дня (не используется напрямую из UI, но рабочий) |
| `kp.php` | Генерирует kp-файл и отдаёт браузеру |
| `import.php` | Импорт меню из xlsx в шаблон |
| `bulk-import.php` | Массовый импорт |
| `forgot-password.php` | Сброс пароля через email-код |
| `logout.php` | Уничтожение сессии |

### src/ — библиотека функций

| Файл | Содержит |
|------|---------|
| `src/db.php` | PDO singleton `db()` + все функции работы с БД |
| `src/auth.php` | `require_auth()`, `try_login()`, `do_logout()`, `current_user()` |
| `src/excel.php` | `generate_menu_excel(date, type)`, `cleanup_old_files()` |
| `src/kp_generator.php` | `generate_kp_excel(year, type)` |
| `src/tm_generator.php` | `generate_typical_menu_excel(type, year)` |
| `src/importer.php` | `parse_menu_xlsx(filepath)` — парсер входящих xlsx |
| `src/mailer.php` | `send_mail()`, `send_token()`, `verify_token()` |
| `src/header.php` | Навигационная панель admin (подключается через include) |

### api/

| Файл | Эндпоинт |
|------|---------|
| `api/save-day.php` | JSON API для calendar.php. Actions: `save` (+ генерирует xlsx), `delete`, `apply_cycle`, `bulk_save` |

### cron/

| Файл | Запуск |
|------|--------|
| `cron/daily.php` | Ежедневно 07:00: генерирует xlsx на 7 дней × 3 типа, чистит старые файлы, отправляет напоминание |

Команда для хостинга Jino:
```
0 7 * * * php /home/LOGIN/public_html/food-app/cron/daily.php
```

## food/ — публичный сайт

| Файл | Назначение |
|------|-----------|
| `index.php` | Публичный календарь питания для родителей |
| `menu/day.php` | Меню на конкретный день |
| `menu/menu.php` | Типовое меню (табы по дням цикла) |
| `menu/assets/style.css` | Стили публичного сайта |
| `.htaccess` | `Options -Indexes` — отключает листинг директории |
| `*.xlsx` | Ежедневные файлы меню и документы (генерируются автоматически) |

Публичные страницы подключают admin через:
```php
$adminRoot = __DIR__ . '/../food-app';   // index.php
$adminRoot = __DIR__ . '/../../food-app'; // menu/day.php, menu/menu.php
```

## База данных

Схема: `food-app/schema.sql`
Миграция для существующих БД: `food-app/migrate.sql`

### Таблицы

**`menu_templates`** — шаблоны дней цикла
```
id, day_number INT, school_type ENUM('sm','main','ss'), is_boarding TINYINT(1), label VARCHAR
```

**`menu_items`** — блюда шаблона
```
id, template_id FK, meal_type ENUM(breakfast,breakfast2,lunch,afternoon_snack,dinner,dinner2),
section, recipe_num, dish_name, grams, price, kcal, protein, fat, carbs, sort_order
```

**`calendar`** — назначение шаблонов на даты
```
PK=(date, school_type), template_id FK NULL, school, dept, is_cycle_start TINYINT(1)
```
- `template_id = NULL` → явный выходной
- запись отсутствует → день не заполнен

**`users`** — учётные записи
```
id, username, password_hash, email, email_verified TINYINT(1)
```

**`email_tokens`** — коды подтверждения
```
id, user_id FK, purpose ENUM('setup','reset'), code CHAR(6), email, expires_at, used TINYINT(1)
```

## Типы школ

| Код | Название | Суффикс xlsx |
|-----|----------|-------------|
| `sm` | Начальная | `-sm.xlsx` |
| `main` | Основная | *(нет суффикса)* |
| `ss` | Старшая | `-ss.xlsx` |

## Генерация Excel-файлов

Все файлы сохраняются в `FILES_DIR` (`food/`), публичный URL — `FILES_URL` (`/food/`).

| Файл | Когда генерируется |
|------|--------------------|
| `YYYY-MM-DD[-type].xlsx` | При `action=save` в `api/save-day.php` (кнопка «Сохранить» в боковой панели) |
| `YYYY-MM-DD[-type].xlsx` | Ежедневно крон: 7 дней вперёд × 3 типа |
| `tm{year}-{type}.xlsx` | При сохранении sm-шаблона в `template-edit.php` |
| `kp{year}-{type}.xlsx` | По запросу через `kp.php` |

Очистка старых файлов — `cleanup_old_files()` в крон-скрипте (удаляет xlsx старше 14 дней).

## Авторизация

Сессионная, однопользовательская. Cookie: `menu_sess`.
Каждая защищённая страница начинается с:
```php
require_once __DIR__ . '/src/auth.php';
require_once __DIR__ . '/src/db.php';
require_auth();
```

## Важные детали кода

- **`calendar.php` JS**: хранит рабочие дни в `localStorage['defaultWorkdays_{type}']`. `dayNumKeys[]` — упорядоченный массив day_number, корректно обрабатывает пропуски в нумерации
- **`assign_cycle()`**: пропускает только явные выходные (`template_id=null`), не трогает пустые даты
- **`bulk_save_calendar()`**: `day_num=-1` → DELETE, `0` → выходной, `N` → шаблон дня N
- **Excel J1**: `Date::PHPToExcel($dateObj)`, формат `m/d/yyyy`
- **Excel F1**: формат `@` (текстовый, для корпуса/отделения)
- **`is_boarding=1`** в шаблоне → в xlsx добавляются секции Полдник, Ужин, Ужин 2

## Деплой на новую машину

1. Скопировать `food-app/` и `food/` на хостинг
2. Создать БД MySQL, импортировать `food-app/schema.sql`
3. Заполнить `food-app/config.php`
4. Убедиться что `food/` доступна по URL и PHP может писать в неё файлы
5. Открыть `food-app/setup.php` → создать учётную запись администратора
6. Настроить крон (см. выше)
7. Проверить что `food-app/.htaccess` запрещает прямой доступ к admin-зоне
