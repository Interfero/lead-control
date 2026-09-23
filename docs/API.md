# Lead Control API

База: `https://lead-control.space`  
Префикс: `/api` (это **не** `/crm` — веб-морда CRM живёт отдельно).  
Формат: JSON. CSRF нет. Сессия браузера не используется.

Единая БД MySQL: пользователи, роли, города, заказы — те же, что в CRM. Внешние приложения (Desk, GM, SuperPart, чаты) ходят сюда server-to-server.

---

## Оргструктура (роли × города)

Отдельной таблицы «этот город принадлежит этому диру» **нет**. Связь **M:N**:

| Таблица | Смысл |
|---------|--------|
| `users` | сотрудник (`user_id`) |
| `roles` / `user_roles` | роли (у человека может быть несколько) |
| `cities` / `user_cities` | города, к которым привязан сотрудник |
| `cities.parent_city_id` | город-спутник материнского филиала (кластер НН разворачивается через `City::operationGroupIds()`) |
| `users.access_all_cities` | флаг «все города» (плюс роли КЦ / гендир / разработчик) |

### Роли (`role_code`)

| Код | Название | Привязка к городам |
|-----|----------|-------------------|
| `master` | Мастер | `user_cities` — города, в которых работает |
| `ad_manager` | Менеджер по рекламе | свои города |
| `order_manager` | Менеджер по заказам | свои города |
| `senior_manager` | Старший менеджер | свои города (филиал) |
| `tech_director` | Технический директор | свои города |
| `branch_head` | Руководитель филиала | свои города = филиал |
| `regional_director` | Региональный директор | несколько городов в `user_cities` |
| `call_center` | Диспетчер КЦ | все города |
| `senior_dispatcher` | Старший диспетчер | все города |
| `investor` | Инвестор | как правило все / касса |
| `general_director` | Генеральный директор | все города |
| `developer` | Разработчик | все города |

Скоуп в коде: `User::cityIdsForScope()` / `cityIdsForOrdersFilter()` (`null` = все города).

HR: рег может создавать `branch_head`, `tech_director`, `senior_manager`, `master`; руководитель филиала — только `master`. Это правило UI, не API.

**Как читать «чей город»:** взять активных пользователей с нужной ролью, у которых в `user_cities` есть этот `city_id` (для НН — ещё спутники).

---

## Org tree для GM (оргструктура)

Канонический эндпоинт для **Guild of Masters** — убрать демо-города и построить чаты по живым данным LC.

```
GET /api/v1/gm/org/tree
```

Подробная инструкция для команды GM: [GM-ORG-INTEGRATION.md](GM-ORG-INTEGRATION.md).

Тот же контракт доступен в Desk (`GET /api/v1/desk/org/tree`, Bearer `DESK_API_TOKEN`) — для других интеграций.

| Контур | Auth | Скоуп |
|--------|------|--------|
| GM | `Authorization: Bearer <GM_API_BEARER_TOKEN>` | без `X-GM-*` — **вся компания**; с identity — города пользователя |
| Desk | `Authorization: Bearer <DESK_API_TOKEN>` | без `X-Desk-User-Id` — вся компания; с заголовком — города зрителя |

Throttle: GM 60/мин, Desk 120/мин.

### Ответ 200

```json
{
  "generated_at": "2026-08-17T17:00:00+00:00",
  "scope": {
    "all_cities": true,
    "city_ids": null,
    "viewer_id": null
  },
  "company": {
    "general_directors": [{ "id": 1, "name": "…", "email": "…", "phone": "9123456789", "roles": ["general_director"], "city_ids": [], "access_all_cities": false, "is_active": true }],
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
      "masters": []
    }
  ]
}
```

Правила:

- В дерево попадают только `is_active`, не ЧС, без `user_fired_at`.
- Компания (`company`) — роли без филиала: гендир, разработчик, КЦ, ст. диспетчер, инвестор. Их **не дублируют** в каждом городе.
- Филиал: человек попадает в город, если город есть в `user_cities` **или** в операционном кластере спутников (НН).
- Рег с несколькими городами повторяется в каждом из них.
- `scope.city_ids === null` значит «все активные города».
- У каждого человека в списках: `access_all_cities: true` = видит всю сеть (роль КЦ/гендир/разработчик или флаг), не только колонка в БД.

Рекомендация для GM: бэкенд раз в N минут тянет **полное** дерево (без identity), строит у себя города и привязки сотрудников. Чаты и права — только в GM.

---

## Контуры и секреты

| Контур | Prefix | Auth | Env |
|--------|--------|------|-----|
| SuperPart | `/api/v1` (ping, reference, partner-orders) | `X-API-Key` + `X-Signature` HMAC-SHA256 тела | `SUPERPART_API_KEY`, `SUPERPART_API_SECRET` |
| Desk | `/api/v1/desk` | `Authorization: Bearer` | `DESK_API_TOKEN` |
| GM | `/api/v1/gm` | `Authorization: Bearer` | `GM_API_BEARER_TOKEN` |
| Health | `GET /api/v1/health` | нет | — |
| Ops health | `GET /api/v1/ops/health` | `X-Ops-Token` или `?token=` если задан | `OPS_HEALTH_TOKEN` (`security.ops_health_token`) |
| Mango | `POST /api/mango/webhook` | подпись `sign` | `MANGO_API_KEY`, `MANGO_API_SALT`, `MANGO_WEBHOOK_SECRET` |

В production пустой SuperPart-ключ → 503. Пустой Desk/GM bearer → 503. SuperPart в non-production без ключей пропускает запросы (dev).

---

## Сущность «заявка» (Order / Lead)

В LC **заявка = запись `orders`**. Отдельных моделей Lead / Application нет. Филиал = город (`cities.city_id` через `addresses.city_id`).

### Идентификаторы

| Поле | Смысл |
|------|--------|
| `order_id` | Первичный ключ в LC (`orders.order_id`) |
| `external_id` | Строковое зеркало `order_id` для Desk / адаптеров |
| `city_id` | Филиал (город) заявки |
| `partner_user_id` | ID партнёра SuperPart (если заказ из партнёрского API) |
| `source_id` | Рекламный канал / РК (`sources`) — **не** путать с `source` ниже |

### Происхождение (`source`)

В теле каждой заявки API:

| Значение | Где |
|----------|-----|
| `LC` | Заказ из Lead Control (все ответы API LC) |
| `KP-LEAD` | Заказ из kp-lead-centre (CRM2); отдаёт Desk при агрегации, **не** хранится в таблице `orders` LC |

Поле `source` ≠ `source_id` (маркетинг).

### Единый контракт create/get (SuperPart)

Ответ `POST` / `GET` / `GET list` `partner-orders` строится через `OrderLeadPresenter` и включает как минимум:

`order_id`, `external_id`, `source` (`LC`), `city_id`, `city_name`, `order_status`, `status_label`, `raw_status`, клиент/телефон/адрес, `datetime_order`, даты создания/закрытия, `partner_user_id`, `source_id`, мастер, суммы, `is_closed`.

### Филиалы

- **SuperPart** `GET /partner-orders` — **все** города (фильтр только явным `?city_id=`).
- **Desk** `GET /desk/orders` — без `X-Desk-User-Id` или с `?all_cities=1` / `?all_branches=1` — **все** филиалы; с заголовком пользователя без флага — скоуп городов сотрудника (UI). Sync Desk → CRM1 всегда запрашивает с `all_cities=1`.

---

## SuperPart

Throttle: 60/мин.  
Подпись: `X-Signature = hash_hmac('sha256', rawBody, SUPERPART_API_SECRET)`. Для GET тело пустое — HMAC от `""`.  
`Content-Type: application/json` для методов с телом.

Подробнее про источники: [SUPERPART_SOURCES.md](SUPERPART_SOURCES.md).

### `GET /api/v1/ping`

```json
{ "ok": true, "app": "levelion" }
```

### `GET /api/v1/reference/cities`

```json
{ "data": [{ "city_id": 1, "city_name": "Москва", "city_type": "city", "city_timezone": "Europe/Moscow" }] }
```

Только `is_active`. Людей нет.

### `GET /api/v1/reference/work-types`

`order_core`: `core` / `non_core` / `other`.  
`equipment_types`: код, подпись, какой `order_core`.

### `GET /api/v1/reference/sources`

Активные источники с `available_for_superpart = true`. Поля: `source_id`, `source_name`, `city_id`, `city_name`, `superpart_partner_id`, `superpart_local_source_id`, `source_kind`, `source_url`, `use_source_url`.

`superpart_partner_id === null` — общий канал; иначе закреплён за партнёром SuperPart.

### `POST /api/v1/reference/sources`

Создать/обновить party-источник партнёра.

Тело: `source_name`, `superpart_partner_id`, `superpart_local_source_id`, опционально `source_url`, `use_source_url`.  
Сопоставление по `superpart_local_source_id`. 201 при создании.

### `PATCH /api/v1/reference/sources/{source_id}`

`superpart_partner_id` (число или `null`), опционально `source_name`, `is_active`, `available_for_superpart`, `deleted: true` (снять с партнёра).

### `DELETE /api/v1/reference/sources/{source_id}`  
### `DELETE /api/v1/reference/sources/by-local/{superpart_local_source_id}`

Если есть заказы — деактивация (`deleted: false`). Иначе физическое удаление.

### `GET /api/v1/partner-orders/statuses`

Каталог статусов заявки:

```json
{
  "statuses": [
    { "code": "pending", "label": "Ожидание", "is_closed": false }
  ],
  "labels": { "pending": "Ожидание" }
}
```

### `GET /api/v1/partner-orders`

Список заявок **по всем филиалам**.

Query: `city_id`, `order_status`, `updated_after`, `limit` (1…5000, по умолчанию 500).

Ответ: `{ "orders": [ /* единый контракт */ ] }`.

### `POST /api/v1/partner-orders`

Обязателен заголовок `Idempotency-Key` (≤ 128). Повтор с тем же ключом не создаёт дубль.

Тело:

| Поле | |
|------|--|
| `client_name`, `client_phone` | обязательно |
| `city_id` | активный город (любой филиал из справочника) |
| `street`, `house` | обязательно; `flat`, `address_adds` опционально |
| `datetime_order` | дата/время визита |
| `partner_user_id` | ID партнёра в SuperPart |
| `order_core` **или** `equipment_type` | одно из двух |
| `order_adds` | комментарий |
| `source_id` | опционально; иначе `SUPERPART_DEFAULT_SOURCE_ID` |
| `source` | опционально; только `LC` (приём в LC) |

**201:** полное тело заявки (единый контракт), в т.ч. `source: "LC"`. Автор в CRM: `SUPERPART_ORDER_AUTHOR_USER_ID`.

### `GET /api/v1/partner-orders/{order_id}`

Полное тело заявки (единый контракт). **404** если нет.

### `GET /api/v1/partner-orders/{order_id}/status`

```json
{
  "order_id": 123,
  "external_id": "123",
  "source": "LC",
  "order_status": "pending",
  "status_label": "Ожидание",
  "raw_status": "pending",
  "is_closed": false,
  "order_closed_at": null,
  "city_id": 10
}
```

### `PATCH /api/v1/partner-orders/{order_id}/status`

Тело: `{ "order_status": "on_way" }`.  
**422** если заказ уже закрыт. **404** если нет.

---

## Desk (lead-desk / хаб)

Throttle: 120/мин.  
`Authorization: Bearer <DESK_API_TOKEN>`.  
Контекст сотрудника: `X-Desk-User-Id: {user_id}` (для `me` обязателен; для org/tree, cities, orders — опционален, режет скоуп **если нет** `all_cities=1`).

### `POST /api/v1/desk/auth/login`

```json
{ "email": "user@example.com", "password": "…" }
```

200: `{ user, projects }` — `user` как в `users/me`; `projects` — `desk` и/или `crm` по ролям.  
403 `needs_2fa: true` если у пользователя включена 2FA.  
422 при неверных данных.

### `POST /api/v1/desk/sso/issue`

```json
{ "user_id": 12, "target": "crm" }
```

`target`: `crm` | `desk`. Код живёт 2 минуты в кэше.  
Ответ: `{ code, redirect, user }`. Для CRM callback: `{APP_URL}/crm/sso/callback?code=…`.

### `POST /api/v1/desk/sso/exchange`

```json
{ "code": "…" }
```

Одноразовый обмен desk-кода. 422 если истёк.

### `GET /api/v1/desk/users/me`

Нужен `X-Desk-User-Id`.

```json
{
  "user": {
    "id": 12,
    "name": "…",
    "email": "…",
    "roles": ["branch_head"],
    "city_ids": [10, 11],
    "cities": [
      { "id": 10, "name": "Казань", "label": "Казань", "parent_id": null, "is_satellite": false }
    ],
    "can_close": true
  }
}
```

`city_ids: null` — все города.

### `GET /api/v1/desk/statuses`

- `statuses` — map `code → label` (legacy для Desk).
- `catalog` — массив `{ code, label, is_closed }` для новых клиентов.

### `GET /api/v1/desk/cities`

Активные города (фильтр по `X-Desk-User-Id`, если передан). `{ id, name, timezone }`.

### `GET /api/v1/desk/org/tree`

См. [Org tree](#org-tree-для-чатов).

### `POST /api/v1/desk/masters/upsert`

Синхронизация мастеров из КП. Тело: опционально `city_id`, массив `masters[]` с `kp_employee_id`, `name`, опционально `email`, `passport`, `status`, `is_active`, `city_ids`, `city_names`. Сопоставление по `kp_employee_id`, иначе email.

### `GET /api/v1/desk/orders`

Активные + закрытые за последние 14 дней. Без `full` ещё ограничение ~30 дней по созданию.

Query:

- `full=1` — без окна по дате создания
- `updated_after=` ISO datetime — инкремент
- `city_id=` — один город
- `all_cities=1` / `all_branches=1` — **все филиалы**, игнорирует скоуп `X-Desk-User-Id` (для sync Desk)
- `X-Desk-User-Id` — скоуп городов (если нет `all_cities`)

Лимит 5000. Ответ `{ "orders": [ … ] }`.

Поля заказа: `order_id`, `external_id`, **`source: "LC"`**, город, `raw_status` / `order_status` / `status_label`, клиент/телефон/адрес, мастер, список мастеров города, суммы, даты, `timezone`, `order_type` (`first`/`repeat`/`warranty`), комментарии, документы.

### `GET /api/v1/desk/orders/{orderId}`

`{ "order": … }`. 403 если пользователь не видит город заказа.

### `GET /api/v1/desk/orders/{orderId}/status`

Компактный статус (как у SuperPart `…/status`).

### `PATCH /api/v1/desk/orders/{orderId}/status`

Тело: `{ "order_status": "…" }` или `{ "raw_status": "…" }`.  
422 если заказ уже закрыт.

### `PATCH /api/v1/desk/orders/{orderId}`

`raw_status`, `paid_amount`, `parts_amount`, `comment` (пишется в `city_adds` со штампом), `master_id` / `master_external_id`.  
422 если заказ уже закрыт.

### `POST /api/v1/desk/orders/{orderId}/close`

Проведение заказа (нужен `X-Desk-User-Id` и право закрывать). Те же проверки, что в CRM.

### Документы заказа

- `POST /api/v1/desk/orders/{orderId}/documents` — multipart: `file` (фото, ≤10 МБ), `category`: `contract` | `receipts` | `parts_photos` | `storage_receipt`. Макс. 20 файлов в категории.
- `GET /api/v1/desk/orders/{orderId}/documents/{documentId}` — скачивание
- `DELETE /api/v1/desk/orders/{orderId}/documents/{documentId}`

---

## Guild Master (GM)

Throttle: 60/мин + отдельно `gm-verify-password` (10/мин на login+IP, 20/мин на IP), `gm-change-password` (5/15 мин на userId+IP), `gm-update-inn` (10/15 мин на userId+IP).

`Authorization: Bearer <GM_API_BEARER_TOKEN>`.

Identity сотрудника (нужен почти везде, **кроме** `org/tree` без скоупа, `verify-password`, `change-password`, `users/{userId}/password-status`, `users/sync`, `guild-applications`):

| Заголовок | Query |
|-----------|--------|
| `X-GM-User-Id` | `userId` |
| `X-GM-Login` | `login` |
| `X-GM-Email` | `email` |

Достаточно одного. Логин без `@` ищется как локальная часть email. 400 без identity, 404 если не найден.

Маппинг ролей при синке в GM: `developer→ADMIN`, `general_director→GENERAL_DIRECTOR`, `regional_director→REGIONAL_DIRECTOR`, `tech_director→DIRECTOR`, КЦ/ст.диспетчер→`DISPATCHER`, иначе `MASTER`. `branch_head` в этом маппинге уходит в `MASTER`; флаг директора филиала — `isBranchDirector` в профиле.

### `GET /api/v1/gm/users/me`

Профиль сотрудника. Поля `role`, `branchId`, `branchCity` сохранены (первая роль / первый город из pivot — для старых клиентов GM).

Добавлено, как в Desk:

| Поле | |
|------|--|
| `roles` | все `role_code`, не одна |
| `city_ids` | города скоупа; `null` = вся сеть (гендир / разработчик / КЦ / `access_all_cities`) |
| `cities` | те же города: `{ id, name, parent_id, is_satellite }` |
| `access_all_cities` | `true`, если `city_ids === null` (видит всю сеть) |

```json
{
  "userId": 12,
  "role": "regional_director",
  "roles": ["regional_director"],
  "city_ids": [10, 11],
  "cities": [
    { "id": 10, "name": "Казань", "parent_id": null, "is_satellite": false },
    { "id": 11, "name": "Набережные Челны", "parent_id": null, "is_satellite": false }
  ],
  "access_all_cities": false,
  "isBranchDirector": false,
  "branchId": 10,
  "branchCity": "Казань",
  "inn": "7707083893",
  "rating": 42,
  "birthDate": "1990-05-01",
  "guildJoinedAt": "2024-01-15",
  "photoUrl": null,
  "isVerifiedByPassport": true,
  "status": "active"
}
```

Поля профиля для GM (контракт 2026-09-08):

| Поле | Тип | Источник |
|------|-----|----------|
| `inn` | string\|null | `users.user_inn` (только 10/12 цифр; пусто → `null`) |
| `rating` | number 0…100 | пока `min(100, число completed-заказов)`; clamp для UI GM |
| `birthDate` / `guildJoinedAt` | date\|null | `user_birth_date` / `user_hired_at` |
| `photoUrl` | string\|null | пока нет аватара → `null` |
| `displayName` / `fullName` | string | `user_name` |
| `isVerifiedByPassport` / `isBranchDirector` / `status` | … | как раньше |

### `GET /api/v1/gm/users/me/metrics`

Счётчики **текущего календарного месяца** (`Europe/Moscow`) по заказам мастера + касса первого города pivot.

| Поле | Тип | Смысл |
|------|-----|--------|
| `masterEarnedRub` | number | ЗП мастера по всем completed за месяц |
| `primaryAvgCheckRub` | number | средний чек по **всем** completed за месяц (как `roster.avgCheckRub`) |
| `primaryClosedOrdersMonth` | number | число таких закрытий (как `roster.closedOrdersMonth`) |
| `masterMonthRefusals` | number | `rejected` за месяц |
| `masterMonthCancelledApplications` | number | `cancelled_cc` + `cancelled_city` |
| `primarySalaryRub` | number | ЗП только по `order_core=core` |
| `branchCashRub` | number | сумма signed CFM операций первого города за месяц |
| `unreadMessages` / `deputyUnreadMessages` | number | пока 0 |

Все счётчики — **number** (не строки); нули допустимы.

### `GET /api/v1/gm/users/me/rating-history`

Заглушка: `{ "items": [] }`.

### `GET /api/v1/gm/users/me/reviews`  
Query: `month=YYYY-MM`, `sentiment=all|positive|neutral|negative`, `page`, `pageSize` (1–100).

### `GET /api/v1/gm/users/me/reviews/negative-open`

### `GET /api/v1/gm/users/me/messenger`

Мини-карточка для мессенджера GM: `externalId`, `name`, `email`, `role`, `title`, `city` (первый город). Для оргструктуры чатов используйте `/org/tree`, не этот метод.

### `PATCH /api/v1/gm/users/me/inn`

Сохранение ИНН текущего мастера (identity только из `X-GM-*`). Источник правды — `users.user_inn` (то же поле, что `GET /users/me` → `inn`).

```json
{ "inn": "772845110643" }
```

200: `{ "ok": true, "userId": 146, "inn": "772845110643" }`.  
404 `{ "error": "user_not_found" }` — нет identity или пользователь не найден.  
422 `{ "error": "invalid_inn", "message": "…" }` — не 10/12 цифр, пробелы/буквы, пустое значение.  
429 `{ "error": "too_many_requests" }` — rate limit `gm-update-inn` (10 / 15 мин на userId + IP).

Повторная запись того же ИНН — 200 (идемпотентно). Очистка ИНН через этот метод не поддерживается.

### `POST /api/v1/gm/users/verify-password`

Без identity-заголовков. См. [GM-VERIFY-PASSWORD.md](GM-VERIFY-PASSWORD.md).

**Login (канон):** `users.email` целиком или локальная часть до `@` (не телефон).

```json
{ "login": "user@example.com", "password": "…" }
```

200: `user.id` / `user.userId`, `displayName`/`name`, `email`, `city`/`branchCity`, `mustChangePassword`.  
401 (нет пользователя / неверный пароль / пароль не задан) / 403 неактивен или ЧС / 422.

### `POST /api/v1/gm/users/change-password`

Без identity-заголовков. GM передаёт `userId` из сессии. См. [GM-CHANGE-PASSWORD.md](GM-CHANGE-PASSWORD.md).

```json
{ "userId": 95, "currentPassword": "…", "newPassword": "…" }
```

200: `{ "ok": true, "userId": 95, "mustChangePassword": false }`.  
401 неверный текущий пароль / 403 неактивен или ЧС / 404 пользователь / 422 валидация / 429 rate limit.

### `GET /api/v1/gm/users/{userId}/password-status`

Без identity. `{ "userId", "hasPassword", "mustChangePassword" }` — для баннера принудительной смены в GM.

### `POST /api/v1/gm/users/sync`

Пуш пользователей **из LC в GM** (не чтение иерархии).

```json
{ "all": true, "includeInactive": false }
```

или `{ "userIds": [1, 2] }`. 422 если нет ни `all`, ни `userIds`. 207 если часть не уехала.

### `GET /api/v1/gm/orders`

Заказы текущего мастера. Query: `status` (через запятую), `from`, `to` (дата создания), `page`, `pageSize` (1–100).

В каждом элементе списка (и в карточке):

| Поле | Тип | Смысл |
|------|-----|--------|
| `clientAge` | number\|null | возраст клиента (`persons.person_age`) |
| `orderKind` | `first`\|`repeat`\|`warranty` | бейдж типа; CRM `new` → `first` |
| `billingCategory` | `new`\|`repeat`\|`warranty` | как в БД CRM |
| `financialSnapshot` | object | `amountPaidRub`, `amountCompRub`, `netAmountRub`, `masterSalaryRub`, `orderType`, `orderCore` |
| `statusChangedAt` | ISO-8601\|null | последняя смена `status` |
| `inProgressAt` | ISO-8601\|null | вход в `in_progress` (не затирается при СД/закрытии) |
| `branchComment` | string\|null | полный комментарий филиала |
| `masterSdComment` / `masterCloseComment` | string\|null | блоки отписок мастера |
| `hasDocuments` | boolean | есть ли вложения |
| `scheduledAt` | ISO-8601\|null | время визита (`datetime_order` в веб-CRM). Не путать с `createdAt`. Нет даты — `null`, не подставлять создание |
| `cityName` | string\|null | отображаемый населённый пункт (для KP — с НП, напр. `Псков (рабочий посёлок Палкино)`) |
| `address` | string\|null | улица/дом без города (как раньше для UI) |
| `addressFull` | string\|null | `cityName` + `address` одной строкой для отображения; текущие поля не меняет |
| `hasClaim` | boolean | есть ли претензия ОКК по этой заявке (таблица `complaints`, не отзыв мастера) |
| `claim` | object\|null | снимок последней открытой претензии, иначе последней: `id`, `status` (`new`\|`in_progress`\|`resolved`\|`rejected`), `createdAt`, `summary` (до 180 символов) |
| `updatedAt` | ISO-8601\|null | `statusChangedAt` → иначе `closedAt` → иначе `createdAt` |

`clientContactType` по-прежнему `phone`|null (канал связи); для UI «Гарантия/Первичка» используйте `orderKind`.

```json
{ "items": [ … ], "page": 1, "pageSize": 20, "total": 0 }
```

### `GET /api/v1/gm/orders/{orderId}`

Карточка: сводка (как в списке) + `documents`, `timeline`, `assignedMaster`, `branch`, флаги звонка/сообщения.

### `POST /api/v1/gm/orders/{orderId}/accept` — статус `on_way`

Обновляет `statusChangedAt`. Ответ: `{ "ok": true, "order": { …карточка… } }`.

### `POST /api/v1/gm/orders/{orderId}/in-progress` — `on_way` → `in_progress`

Только из `on_way`, иначе 422 `status: invalid_status_transition`. Пишет `inProgressAt` (один раз) и `statusChangedAt`.

### `POST /api/v1/gm/orders/{orderId}/sd` — в работу СД

Тело JSON:

```json
{ "masterComment": "текст отписки мастера при переводе в СД" }
```

`masterComment` обязателен. Статус → `in_progress_sd`. В комментарий филиала дописывается блок:

```text
**Отписка мастера при переводе в СД**
{текст}
```

Сохранку по-прежнему грузить через `POST …/documents` (`category=storage_receipt`), до или после sd.

### `POST /api/v1/gm/orders/{orderId}/review` — сразу «Готов» (`completed`)

Тело JSON:

```json
{
  "amountPaidRub": 5500,
  "amountCompRub": 1500,
  "masterComment": "текст отписки мастера при закрытии"
}
```

Правила: суммы ≥ 0; `netAmountRub` считает LC (`paid − comp`); статус → **`completed`** («Готов»): касса, суммы, расчёт — как кнопка «Готов» в вебе. Новые закрытия статус `review` не ставят. Допустимо из `in_progress`, `in_progress_sd` и вчерашних `review`. Иначе 422 `{ "code": "invalid_status_transition" }`. В ответе 2xx и в следующем GET — `status: "completed"` и `financialSnapshot` (взято / ЗПЧ / чистыми / `masterSalaryRub` / `orderCore`: `core` = А, `non_core` = Б). В комментарий филиала:

```text
**Отписка мастера при закрытии заявки**
{текст}
```

404 если заказ не этого мастера. 409 `{ "code": "order_already_final" }` если заказ уже `completed` / отмены / `rejected`. Повтор на уже Готов → 409, не 500.

### `GET /api/v1/gm/orders/{orderId}/documents`  
### `POST /api/v1/gm/orders/{orderId}/documents` — multipart `file` (фото ≤10 МБ)

### `GET /api/v1/gm/guild-applications`

Заглушка `{ "items": [] }`.

### `GET /api/v1/gm/org/tree`

См. [Org tree](#org-tree-для-чатов).

### `GET /api/v1/gm/branch/roster`

Мастера выбранного филиала + KPI месяца.

Query: `city_id=` (опционально). Без параметра — **первый** город из `user_cities` (как раньше). Для рега, КЦ и гендира передавайте `city_id`.

- 404 — города нет
- 403 — нет доступа к городу
- без `city_id` и без pivot-городов: `{ "branchId": null, "branchCity": null, "items": [] }`

Ответ также содержит `branchId` / `branchCity` выбранного филиала.

### `GET /api/v1/gm/branch/stats`

Те же правила `?city_id=`. KPI филиала за текущий месяц (`Europe/Moscow`):

| Поле | Тип |
|------|-----|
| `branchId` / `branchCity` | number / string |
| `ordersClosedMonth` | number |
| `avgCheckRub` | number |
| `cashRub` | number (сумма signed CFM за месяц) |
| `activeMasters` / `expelledMasters` | number |
| `cancellationsTotal` | number (`cancelled_cc` + `cancelled_city`, все статусы города) |
| `negativeReviewsCount` | number (пока 0; UI GM не требует) |

---

## Служебные

### `GET /api/v1/health`

```json
{ "ok": true, "service": "lead-control", "version": "…", "time": "…" }
```

`service` = `GM_API_SERVICE_NAME` (по умолчанию `lead-control`). Без авторизации.

### `GET /api/v1/ops/health`

Снимок очередей/интеграций. Если `OPS_HEALTH_TOKEN` задан — нужен `X-Ops-Token` или `?token=`. 200 или 503.

### `POST /api/mango/webhook`

Входящие события АТС Mango (`call`, `call_result`, `record`). Форма: поля `json` + `sign`.  
`sign = sha256(api_key + json + api_salt)`.  
Всегда отвечает 204, чтобы Mango не ретраил. В production без `MANGO_WEBHOOK_SECRET` обработка выключена.

---

## Статусы заказа

| Код | UI |
|-----|-----|
| `pending` | Ожидание |
| `callback` | Прозвон |
| `not_processed` | Не оформлена |
| `rejected` | Отказ |
| `on_way` | В пути |
| `in_progress` | В работе |
| `in_progress_sd` | В работе (СД) |
| `review` | Проверка |
| `completed` | Готов |
| `cancelled_cc` | Отмена КЦ |
| `cancelled_city` | Отмена Филиала |

Закрытые для Desk: `completed`, `cancelled_cc`, `cancelled_city`, `rejected`.  
`review` («Проверка») в ENUM остаётся для вчерашних заявок; новые закрытия из GM сразу `completed`.

### Претензии ОКК (`complaints`) в GM-ответе заказа

Не путать с `POST …/review` и `GET /users/me/reviews` (отзывы НК / мастера).

Отдельная таблица `complaints`, не флаг на заказе. На заявку может быть несколько претензий; `order_id` необязателен (бывает претензия без заявки). В GM отдаём только претензии **этой** заявки мастера: `hasClaim` + снимок `claim`.

| `claim.status` | UI CRM |
|----------------|--------|
| `new` | Новая |
| `in_progress` | В работе |
| `resolved` | Решена |
| `rejected` | Отклонена |

Веб: `/crm/complaints`, `/crm/complaints/{id}`. Создаёт КЦ/разработчик; филиал смотрит и комментирует.

---

## Что API сознательно не отдаёт

- Пароли, 2FA-секреты, паспорт в org-tree.
- Касса / CFM (кроме агрегатов GM metrics/stats).
- Полный CRUD сотрудников (это веб `/crm/hr`).
- Дерево «кто кому подчиняется» как org-chart с единственным начальником: у человека несколько ролей и несколько городов. Чаты должны строить комнаты по **городу + роли**, а не по единственному `manager_id`.

Код маршрутов: `routes/api.php`.
