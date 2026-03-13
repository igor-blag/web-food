# src/ — библиотека функций

Все файлы подключаются через `require_once`. Нет автозагрузки — зависимости подключаются явно в каждом скрипте.

---

## `db.php` — база данных

**Singleton PDO-подключение** + все функции работы с БД.

```php
db() → PDO   // Возвращает единственный экземпляр соединения
```

### Функции шаблонов

| Функция | Что делает |
|---------|-----------|
| `get_templates(type)` | Все шаблоны типа школы, отсортированные по `day_number` |
| `get_template(id)` | Один шаблон по ID |
| `add_template(type)` | Создаёт новый шаблон со следующим `day_number` |
| `delete_template(id)` | Удаляет шаблон (блюда удаляются каскадом, в `calendar` → NULL) |
| `set_template_boarding(id, flag)` | Устанавливает флаг интерната |
| `get_templates_ordered(type)` | Возвращает `[day_number => template_id]` |

### Функции блюд

| Функция | Что делает |
|---------|-----------|
| `get_template_items(template_id)` | Блюда шаблона, сгруппированные по `meal_type` |
| `save_template_items(template_id, items[])` | Полная перезапись блюд шаблона (DELETE + INSERT) |

### Функции календаря

| Функция | Что делает |
|---------|-----------|
| `get_calendar_day(date, type)` | Запись на конкретную дату + JOIN с шаблоном |
| `get_calendar_month(year, month, type)` | Все записи месяца, ключ = `'Y-m-d'` |
| `get_calendar_year(year, type)` | Все записи года, ключ = `'Y-m-d'` |
| `save_calendar_day(date, tpl_id, school, dept, type, is_cycle_start)` | INSERT ... ON DUPLICATE KEY UPDATE |
| `delete_calendar_day(date, type)` | Удаляет запись (день становится «не учебным») |
| `get_cycle_length(type)` | Количество шаблонов (= длина цикла) |

### Функции массовых операций

| Функция | Что делает |
|---------|-----------|
| `bulk_save_calendar(days[], type)` | Массовое обновление: `day_num=-1` → удалить, `0` → выходной, `N` → шаблон |
| `assign_cycle(start_date, start_day, type, school, dept, end_date)` | Заполняет Пн–Пт шаблонами цикла. Пропускает явные выходные. Возвращает кол-во назначенных дней |

### Функции настроек

| Функция | Что делает |
|---------|-----------|
| `get_kitchen_settings()` | Все настройки пищеблока из `kitchen_settings` |
| `get_org_name()` | Название ОО (краткое обращение к `kitchen_settings.org_name`) |
| `save_kitchen_settings(org_name)` | Сохраняет название ОО |
| `get_enabled_departments()` | Включённые отделения (`is_enabled=1`), отсортированные по `sort_order` |
| `is_vacation_day(date)` | Проверяет, входит ли дата в каникулярный период |

### Функции общественного контроля питания

| Функция | Что делает |
|---------|-----------|
| `get_oc_monitoring()` | Возвращает запись с id=1 из `oc_monitoring` (или массив с пустыми значениями) |
| `save_oc_monitoring(array $d)` | INSERT ... ON DUPLICATE KEY UPDATE для всех полей ОК питания |

---

## `auth.php` — авторизация

| Функция | Что делает |
|---------|-----------|
| `session_init()` | Запускает сессию с `httponly` куками и `strict_mode`. Имя: `menu_sess` |
| `require_auth()` | Нет `$_SESSION['user_id']` → редирект на `index.php` |
| `try_login(username, password)` | `password_verify()`. При успехе: `session_regenerate_id(true)` |
| `do_logout()` | `session_destroy()` + редирект |
| `current_user()` | Возвращает `$_SESSION['username']` или null |

---

## `excel.php` — генератор меню дня

### `generate_menu_excel(date, type='sm') → ?string`

Генерирует xlsx-файл меню для конкретной даты. Возвращает путь или `null` если день не учебный.

**Структура xlsx:**
- Строка 1: шапка (Школа, Отд./корпус, Дата)
  - J1: дата как Excel Date, формат `m/d/yyyy`
  - F1: формат `@` (текстовый, для корпуса/отделения)
- Строки 4+: блюда по секциям (Завтрак, Обед; для `is_boarding=1` + Полдник, Ужин, Ужин 2)

**Имя файла:** `YYYY-MM-DD.xlsx` (main), `YYYY-MM-DD-sm.xlsx`, `YYYY-MM-DD-ss.xlsx` и т.д.

### `cleanup_old_files() → int`

Удаляет `*.xlsx` из `FILES_DIR` старше `DELETE_AFTER_DAYS` дней. Возвращает кол-во удалённых файлов.

---

## `kp_generator.php` — генератор плана питания

### `generate_kp_excel(year, type='sm') → string`

Генерирует `kp{year}-{type}.xlsx` — годовая сетка-календарь с номерами дней цикла.

**Структура:** строки 4–13 = 10 учебных месяцев, колонки B–AF = числа месяца.

---

## `tm_generator.php` — генератор типового меню

### `generate_typical_menu_excel(school_type, year) → string`

Генерирует `tm{year}-{type}.xlsx` — типовое примерное меню по всем дням цикла.

Вызывается автоматически при сохранении sm-шаблона в `template-edit.php`.

---

## `oc_generator.php` — генератор файла общественного контроля

### `generate_oc_excel(array $d) → string`

Загружает шаблон `oc/findex-template.xlsx`, подставляет данные из `oc_monitoring` и сохраняет как `food/findex.xlsx`.

Данные подставляются в ячейки листа по разделам:
- B1/D1 — название школы и дата
- C4 — ссылка на приказ (раздел 1)
- C6–C8 — горячая линия, чат, форум (раздел 2)
- C10–C17 — диетические меню по 4 позициям (раздел 3)
- C19–C20 — анкетирование (раздел 4)
- C22–C23 — здоровое питание (раздел 5)
- C25–C26 — результаты контроля (раздел 6)
- C28–C32 — оценка отходов: `+` в нужной строке (раздел 7)

Вызывается автоматически из `api/save-oc.php` при каждом сохранении формы.

---

## `importer.php` — парсер xlsx

### `parse_menu_xlsx(filepath) → array`

Парсит xlsx в массив для `save_template_items()`.

**Структура входного файла:**
- Строка 3: заголовки (игнорируются)
- Строки 4+: Колонка A: приём пищи, B: раздел, C: № рецептуры, D: блюдо, E: граммы, F: цена, G: ккал, H–J: Б/Ж/У

Маппинг: «завтрак» → `breakfast`, «завтрак 2» → `breakfast2`, «обед» → `lunch` и т.д.

---

## `mailer.php` — почта

| Функция | Что делает |
|---------|-----------|
| `send_mail(to, subject, html)` | Если задан `SMTP_HOST` — через SMTP (PHPMailer), иначе через `mail()` |
| `send_token(user_id, purpose, email)` | Генерирует 6-значный код, сохраняет в `email_tokens`, отправляет письмо |
| `verify_token(user_id, purpose, code)` | Проверяет код (срок, использование, совпадение). При успехе — `used=1` |

`purpose`: `'setup'` (первоначальная настройка) или `'reset'` (сброс пароля).

---

## `header.php` — навигация

Подключается в начале body всех страниц администратора через `include`.
Выводит навигационную панель: логотип, ссылки (Календарь, Шаблоны, Импорт, ОК питания, Настройки), имя пользователя и кнопку выхода.
