# api/ — JSON API

## `save-day.php`

Единственный эндпоинт. Принимает POST с JSON-телом или form-данными. Требует авторизации (`require_auth()`).

**Content-Type ответа:** `application/json; charset=utf-8`

Все ошибки возвращают `{"ok": false, "error": "..."}`.

---

### `action: save` — сохранить один день

Используется из боковой панели `calendar.php` для сохранения школы/отделения конкретного дня.

**Параметры:**
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
- `is_school: true` + `day_num: N` → ищет `template_id` по `day_num` + `type`, сохраняет

**Ответ:**
```json
{"ok": true, "template_id": 5, "day_number": 3, "label": "День 3"}
```

---

### `action: delete` — удалить день из календаря

```json
{"action": "delete", "date": "2026-03-10", "type": "sm"}
```

**Ответ:** `{"ok": true}`

---

### `action: apply_cycle` — расставить цикл

Вызывает `assign_cycle()` — заполняет Пн–Пт от `date` до конца года (или `end_date`).

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

---

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

Семантика `day_num`:
- `-1` → DELETE (убрать запись)
- `0` → явный выходной (`template_id=null`)
- `N > 0` → назначить шаблон дня N

**Ответ:** `{"ok": true, "saved": 4}`
