# GM: оргструктура из Lead Control

Документ для команды **Guild of Masters (GM)**: как получить живую оргмодель компании из LC и убрать демо-города/демо-сотрудников.

Чаты, комнаты, сообщения и права внутри чатов — **полностью на стороне GM**.  
LC отдаёт только: **кто к каким городам привязан и какие роли в этих городах имеет**.

Полная карта всех GM-эндпоинтов: [API.md](API.md).

---

## Базовый URL и авторизация

```
https://lead-control.space/api/v1/gm/...
```

Все GM-методы (кроме verify-password без identity):

```
Authorization: Bearer <GM_API_BEARER_TOKEN>
```

| Код | Когда |
|-----|--------|
| 200 | Успех, всегда JSON |
| 401 | Нет или неверный Bearer |
| 403 | Нет доступа (например, `branch/*?city_id=` чужой город) |
| 404 | Пользователь / город не найден |
| 422 | Невалидные query/body |
| 503 | `GM_API_BEARER_TOKEN` не задан на сервере LC |

HTML не отдаётся — только JSON.

---

## 1. Главный метод: `GET /api/v1/gm/org/tree`

### Зачем

Единый источник оргструктуры для GM:

- активные города, спутники (`parent_id`, `is_satellite`)
- мастера и руководители по каждому городу
- общекорпоративные роли (гендир, КЦ, …) в блоке `company`

### Identity (опционально)

**Для синхронизации всей компании** вызывайте **без** заголовков:

- без `X-GM-User-Id`
- без `X-GM-Login`
- без `X-GM-Email`

→ LC вернёт **всю сеть** (`scope.all_cities: true`).

С identity — только города в скоупе этого сотрудника (для будущих сценариев).

### Пример запроса

```http
GET /api/v1/gm/org/tree HTTP/1.1
Host: lead-control.space
Authorization: Bearer <GM_API_BEARER_TOKEN>
Accept: application/json
```

### Ответ 200

```json
{
  "generated_at": "2026-08-19T20:00:00+00:00",
  "scope": {
    "all_cities": true,
    "city_ids": null,
    "viewer_id": null
  },
  "company": {
    "general_directors": [],
    "developers": [],
    "call_center": [],
    "senior_dispatchers": [],
    "investors": []
  },
  "cities": [
    {
      "id": 10,
      "name": "Казань",
      "timezone": "Europe/Moscow",
      "type": "city",
      "parent_id": null,
      "is_satellite": false,
      "regional_directors": [],
      "branch_heads": [],
      "tech_directors": [],
      "senior_managers": [],
      "ad_managers": [],
      "order_managers": [],
      "masters": [
        {
          "id": 42,
          "name": "Иванов И.И.",
          "email": "ivanov@example.com",
          "phone": "9123456789",
          "roles": ["master"],
          "city_ids": [10],
          "access_all_cities": false,
          "is_active": true
        }
      ]
    }
  ]
}
```

### Бизнес-правила

**Кто попадает в дерево** — только если одновременно:

- `is_active = true`
- не в чёрном списке (`is_blacklisted = false`)
- не уволен (`user_fired_at IS NULL`)

**Чей это город** — отдельной таблицы «город → директор» нет. Источник:

- `user_cities` — прямая привязка
- `cities.parent_city_id` — спутники; для кластера Н. Новгорода LC разворачивает группу через `City::operationGroupIds()`
- `access_all_cities` и роли КЦ/гендир/разработчик — «видит всю сеть» (`access_all_cities: true` у человека в JSON)

**Секция `company`** — только роли, которые **не дублируются** в каждом городе:

| role_code | ключ в JSON |
|-----------|-------------|
| `general_director` | `general_directors` |
| `developer` | `developers` |
| `call_center` | `call_center` |
| `senior_dispatcher` | `senior_dispatchers` |
| `investor` | `investors` |

**Секция `cities[]`** — филиальные роли:

| role_code | ключ в JSON |
|-----------|-------------|
| `regional_director` | `regional_directors` |
| `branch_head` | `branch_heads` |
| `tech_director` | `tech_directors` |
| `senior_manager` | `senior_managers` |
| `ad_manager` | `ad_managers` |
| `order_manager` | `order_managers` |
| `master` | `masters` |

Региональный директор с несколькими городами **повторяется в каждом** таком городе.

### Рекомендация для GM

1. Периодически (или при старте) вызывать `GET /org/tree` **без identity**.
2. Построить у себя справочник городов и сотрудников.
3. Создать рабочие чаты/комнаты на своей стороне по `city.id`, ролям и `user.id`.
4. Не хранить демо-города и демо-привязки — только кэш LC + пользовательские чаты GM.

---

## 2. Профиль: `GET /api/v1/gm/users/me`

Identity **обязателен** (`X-GM-User-Id` / `X-GM-Login` / `X-GM-Email`).

Помимо legacy-полей `role`, `branchId`, `branchCity` отдаёт полный скоуп:

| Поле | Смысл |
|------|--------|
| `roles` | все роли |
| `city_ids` | города скоупа; `null` = вся сеть |
| `cities` | `{ id, name, parent_id, is_satellite }` |
| `access_all_cities` | `true`, если `city_ids === null` |

Нужно GM, чтобы UI понимал: один город или несколько, доступ ко всей сети или нет.

---

## 3. Филиал: roster и stats с `?city_id=`

### `GET /api/v1/gm/branch/roster?city_id=10`

Мастера **конкретного** города + KPI месяца. Без `city_id` — первый город из pivot (legacy).

Ответ включает `branchId`, `branchCity`, `items[]`.

### `GET /api/v1/gm/branch/stats?city_id=10`

KPI филиала за месяц по выбранному городу.

403 — нет доступа к городу; 404 — город не существует.

---

## Что LC не делает (и не будет)

- чаты, комнаты, сообщения, DM
- права доступа внутри чатов
- org-chart с единственным `manager_id`
- CRUD сотрудников для GM

---

## Чеклист готовности (acceptance)

| # | Критерий | Статус |
|---|----------|--------|
| 1 | `GET /api/v1/gm/org/tree` существует, JSON | ✅ |
| 2 | В ответе `company`, `cities`, `parent_id`, `is_satellite`, списки по ролям | ✅ |
| 3 | Нет неактивных / уволенных / в ЧС | ✅ |
| 4 | Один рег в нескольких городах | ✅ |
| 5 | `users/me`: `roles`, `city_ids`, `cities`, `access_all_cities` | ✅ |
| 6 | `branch/roster?city_id=` и `branch/stats?city_id=` | ✅ |
| 7 | Без identity — вся компания | ✅ |
| 8 | 401 / 403 / 404 / 422 / 503 предсказуемы | ✅ |

Код: `app/Services/OrgTreeService.php`, `routes/api.php` → `api.v1.gm.org.tree`.

После деплоя LC на prod проверка:

```bash
curl -sS -H "Authorization: Bearer $GM_API_BEARER_TOKEN" \
  "https://lead-control.space/api/v1/gm/org/tree" | jq '.scope, (.cities | length)'
```
