# api/ — JSON API

Все эндпоинты требуют авторизации (`require_auth()`). Отвечают `application/json`.

---

## `save-day.php`

Эндпоинт календаря. Принимает POST с JSON-телом.

Все ошибки: `{"ok": false, "error": "..."}`.

### `action: save` — сохранить один день

```json
{
  "action": "save",
  "date": "2026-03-10",
  "type": "sm",
  "is_school": true,
  "day_num": 3,
  "school": "Школа №1",
  "dept": "Корпус А",
  "is_cycle_start": 0
}
```

- `is_school: false` → запись с `template_id=null` (явный выходной)
- `is_school: true` + `day_num: N` → назначает шаблон дня N

**Ответ:** `{"ok": true, "template_id": 5, "day_number": 3, "label": "День 3"}`

### `action: delete` — удалить день

```json
{"action": "delete", "date": "2026-03-10", "type": "sm"}
```

**Ответ:** `{"ok": true}`

### `action: apply_cycle` — расставить цикл

```json
{
  "action": "apply_cycle",
  "date": "2026-09-01",
  "type": "sm",
  "start_day": 1,
  "school": "Школа №1",
  "dept": "",
  "end_date": "2026-12-31"
}
```

**Ответ:** `{"ok": true, "count": 85}`

### `action: bulk_save` — массовое обновление

Основная операция из `calendar.php`. Вызывается при каждом изменении чекбокса дня.

```json
{
  "action": "bulk_save",
  "type": "sm",
  "days": [
    {"date": "2026-03-10", "day_num": 1, "school": "Школа №1", "dept": "", "is_cycle_start": 1},
    {"date": "2026-03-11", "day_num": 2, "school": "Школа №1", "dept": ""},
    {"date": "2026-03-12", "day_num": 0, "school": "", "dept": ""},
    {"date": "2026-03-13", "day_num": -1}
  ]
}
```

Семантика `day_num`: `-1` → DELETE, `0` → явный выходной, `N > 0` → шаблон дня N

**Ответ:** `{"ok": true, "saved": 4}`

---

## `save-oc.php`

Сохраняет данные формы общественного контроля питания и перегенерирует `food/findex.xlsx`.

**Метод:** POST, Content-Type: `application/json`

**Тело запроса:**
```json
{
  "school_name": "ГБОУ СОШ №320",
  "report_date": "2026-03-13",
  "s1_url": "https://school.ru/docs/prikaz.pdf",
  "s2_hotline": "8(812)123-45-67",
  "s2_chat_url": "",
  "s2_forum_url": "https://school.ru/forum",
  "s3_diet1_type": "целиакия",
  "s3_diet1_url": "https://school.ru/menu-diet.pdf",
  "s3_diet2_type": "", "s3_diet2_url": "",
  "s3_diet3_type": "", "s3_diet3_url": "",
  "s3_diet4_type": "", "s3_diet4_url": "",
  "s4_survey_url": "https://forms.yandex.ru/...",
  "s4_results_url": "",
  "s5_page_url": "",
  "s5_materials_url": "",
  "s6_acts_url": "/food/oc/oc_abc123.pdf",
  "s6_photos_url": "/food/oc/oc_xyz456.jpg",
  "s7_waste_level": "20"
}
```

`s7_waste_level`: `"20"` | `"30"` | `"40"` | `"50"` | `"none"`

**Ответ:** `{"ok": true}` или `{"ok": false, "error": "..."}`

---

## `upload-oc.php`

Загружает файл (PDF или изображение) для страницы общественного контроля.

**Метод:** POST, Content-Type: `multipart/form-data`

**Поле:** `file` — загружаемый файл

**Допустимые типы:** `application/pdf`, `image/jpeg`, `image/png`, `image/webp`

**Ограничения:**
- Максимальный размер: 20 МБ
- Изображения автоматически оптимизируются: ресайз до 2000 px по длинной стороне, конвертация в JPEG (качество 82), PNG с прозрачностью — белый фон
- PDF сохраняется без изменений

**Файлы сохраняются в:** `food/oc/oc_{uniqid}.{ext}`

**Ответ при успехе:**
```json
{"ok": true, "url": "/food/oc/oc_67d3a1b2c4e56.jpg", "name": "photo.png"}
```

**Ответ при ошибке:**
```json
{"ok": false, "error": "Недопустимый тип файла. Разрешены: PDF, JPEG, PNG, WebP"}
```
