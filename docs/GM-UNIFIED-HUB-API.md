# GM API + Единый хаб (LC + KP-Lead)

Статус: реализовано 2026-09-16.
ТЗ: «расширить существующий GM API данными Единого хаба и KP-Lead».

## Принцип

Гильдия мастеров продолжает работать **только** через `https://lead-control.space/api/v1/gm/*`.
GM не ходит в `/api/v1/desk/*` и **не получает** `DESK_API_TOKEN`.
Lead Control внутри себя стал единой точкой агрегации: он читает кэш Единого окна
(`lead_desk.orders_cache`, соединение `hub`, только чтение) и отдаёт GM нормализованную выборку.

```
GM ──Bearer GM_API_BEARER_TOKEN──► Lead Control /api/v1/gm/*
                                        │
                                        ├── orders (CRM1/LC, БД lead_control)
                                        └── hub-соединение (read-only) ──► lead_desk.orders_cache
                                                                             ▲
                                                                   lead-desk ──HTTP-адаптер──► kp-lead-centre
```

## Идентификаторы заявок

| Источник | `source` | `id` | `sourceOrderId` |
|----------|----------|------|-----------------|
| CRM1 / Lead Control | `lc` | число (как раньше, обратная совместимость) | строка с тем же числом |
| KP-Lead | `kp_lead` | `kp-<external_id>`, напр. `kp-2645253` | `2645253` |

Канонический ключ дедупликации — `source + sourceOrderId`. Конфликт ID между LC и KP невозможен:
LC-заявка `2645253` и KP-заявка `2645253` дают разные `id` (`2645253` и `kp-2645253`).

Маршруты принимают оба формата: `GET /api/v1/gm/orders/{id}` где `{id}` = `[0-9]+` или `kp-[0-9]+`.

## Привязка мастера

Фильтрация по единому идентификатору мастера, а **не** по email/ФИО на лету.

1. Таблица `hub_master_links` (БД `lead_control`) — явная карта соответствий:
   `source`, `external_master_id`, `external_master_name`, `name_key`, `city_id`, `user_id`, `link_type`.
2. `users.kp_employee_id` — прямой ID сотрудника КП (используется, когда источник отдал `master_external_id`).
3. Строки `link_type = manual` — истина, автолинковщик их не перетирает.

Наполнение карты: `php artisan hub:link-masters [--city=14] [--dry-run]` (плюс планировщик, ежечасно).
Команда связывает однозначные совпадения и **печатает** неоднозначные ФИО вместо того, чтобы угадывать.
Для них нужна ручная строка с `link_type = manual`.

Пример: Цыганков Кирилл — `users.user_id = 297`, `kp_employee_id = 19303`, город Псков `city_id = 14`.
В выгрузке КП он значится как «Цыганков Кирилл» — связь лежит в `hub_master_links`.

## Таблица соответствия статусов

Адаптер KP-Lead в Едином окне уже приводит статус карточки КП к кодам CRM1, поэтому карта почти 1:1.
Реализация — `App\Support\Hub\HubStatusMap`.

| `raw_status` источника | Статус GM | Поведение в GM |
|------------------------|-----------|----------------|
| `callback` | `callback` | активная |
| `not_processed` | `not_processed` | активная |
| `new`, `pending` | `pending` | «Новая» |
| `on_way` | `on_way` | «В пути» |
| `in_progress` | `in_progress` | «В работе» |
| `in_progress_sd`, `sd` | `in_progress_sd` | «В работе (СД)» |
| `review`, `ready` | `review` | к закрытию |
| `completed`, `closed`, `done` | `completed` | история / «Готово», учитывается в месячной статистике |
| `cancelled_cc`, `canceled_cc` | `cancelled_cc` | отмена, считается в `masterMonthCancelledApplications` |
| `cancelled_city`, `canceled_city` | `cancelled_city` | отмена |
| `rejected`, `refused` | `rejected` | отказ, считается в `masterMonthRefusals` |
| неизвестный код | по `unified_status` кэша: `new→pending`, `on_way→on_way`, `in_progress→in_progress`, `ready→review`, `closed→completed` | страховка, 500 не отдаём |

Финальные статусы (история + месячная статистика): `completed`, `cancelled_cc`, `cancelled_city`, `rejected`.

## Формат заявки

```json
{
  "id": "kp-2645253",
  "source": "kp_lead",
  "sourceOrderId": "2645253",
  "orderNumber": "2645253",
  "masterId": "297",
  "cityId": 14,
  "status": "pending",
  "statusChangedAt": "2026-09-15T15:29:00+03:00",
  "scheduledAt": "2026-09-15T16:33:00+03:00",
  "clientName": "…",
  "clientPhone": "+79001234567",
  "address": "…",
  "description": "…",
  "amountRub": 0,
  "financialSnapshot": {
    "amountPaidRub": 0,
    "amountCompRub": 0,
    "netAmountRub": 0,
    "masterSalaryRub": 0,
    "orderType": "first",
    "orderCore": "core"
  }
}
```

Для LC-заявок формат прежний, добавлены только `source`, `sourceOrderId`, `masterId`, `cityId`.

Особенности данных KP-Lead:

- КП не отдаёт `paid/parts` по списку — «чистыми» берётся `total_amount`;
  `amountPaidRub` в этом случае равен чистой сумме, `amountCompRub` = 0.
- КП не отдаёт время смены статуса: `statusChangedAt`/`closedAt` = `updated_at_local`,
  а при его отсутствии — время создания заявки.
- `masterSalaryRub` считается по той же лестнице процентов, что и в CRM1
  (`App\Support\Hub\HubMasterSalary` = `OrderService::getMasterPercent`).
- **Квартира / офис (`address_office`):** до окна в `address` / `addressFull` только
  улица и дом (как в КП до «Показать квартиру»). В окне — суффикс из кэша Desk.
  Окно: **≥ 30 мин до `call_at`** (таймзона заявки, дефолт Europe/Moscow) **или**
  статус GM `on_way` / `in_progress` / `in_progress_sd`.
  - **detail** (`GET …/orders/kp-N`): если кэша нет — LC один раз зовёт
    `POST /desk/internal/orders/{id}/reveal-office` (KP `get-address-office`,
    побочный эффект: кв. открывается в КП). После accept / in-progress ответ
    action тоже идёт через detail → reveal срабатывает.
  - **list** (`GET …/orders`): только уже закэшированный `address_office`
    (без N× unlock в КП / без loopback LC↔Desk на весь список).
  Пока office в кэше — повторные вызовы КП не дергают.
- **`clientPhone`:** из `orders_cache.phone`. Если `null` — в кэше нет номера
  (синк HTML КП), не отдельная политика маскировки для GM.

## Статистика

`GET /api/v1/gm/users/me/metrics`, `GET /api/v1/gm/branch/stats`, `GET /api/v1/gm/branch/roster`
считаются по объединённому набору LC + KP-Lead.

- Границы месяца — **Europe/Moscow** (`period.from` / `period.to` в ответе метрик).
- Время заявки источника приводится из её `timezone` в МСК.
- Якорь месяца: `closedAt ?? createdAt`.
- Одна заявка учитывается один раз: ключ `source + sourceOrderId`.
- `bySource` показывает вклад каждого источника — по нему сверяются отчёты LC и Единого хаба.
- `branchCashRub` остаётся кассой Lead Control (деньги КП в кассу LC не попадают).

## Признак неполных данных

Каждый ответ с агрегацией содержит:

```json
{
  "sources": [
    {"source": "lc", "name": "Lead Control", "enabled": true, "available": true, "status": "ok", "lastSyncAt": null, "message": null},
    {"source": "kp_lead", "name": "kp-lead-centre", "enabled": true, "available": true, "status": "ok", "lastSyncAt": "2026-09-16T11:56:51+03:00", "message": null}
  ],
  "partial": false,
  "degraded": false
}
```

| `status` | Смысл |
|----------|-------|
| `ok` | кэш читается, синхронизация свежая |
| `degraded` | Единое окно сообщает об ошибке подключения (часть городов может отставать), данные есть |
| `stale` | последняя синхронизация старше `UNIFIED_HUB_STALE_MINUTES` (по умолчанию 180 мин) |
| `unavailable` | кэш хаба не читается — данные **неполные**, `partial = true` |
| `disabled` | источник выключен настройкой `UNIFIED_HUB_ENABLED=false` |

Нули вместо данных при недоступном источнике не отдаются: GM видит `partial = true` и статус источника.

### Время синхронизации по источникам и городам

`GET /api/v1/gm/orders/sources` → `sources`, `partial`, `degraded` и `cities[]`
(`cityId`, `lastSyncAt`, `stale`, `orders`) в пределах городов пользователя.

Свежесть источника в ответах мастера считается по **его** городам: отставание чужого филиала
не помечает его выборку как `stale`. Серверные метки Единого окна (`last_synced_at`,
`crm_connections.last_sync_at`) хранятся в TZ приложения Единого окна (`UNIFIED_HUB_TIMEZONE`,
по умолчанию `UTC`) и приводятся к `Europe/Moscow` при выдаче.

## Действия по заявкам

Действия маршрутизируются по источнику.

| Источник | accept / in-progress | sd / review / documents |
|----------|----------------------|-------------------------|
| `lc` | как раньше — обработчик Lead Control | как раньше |
| `kp_lead` | запись в КП через внутренний API Единого окна (`DESK_INTERNAL_TOKEN`) | HTTP **409** `source_action_not_supported` |

KP-заявка **никогда** не уходит в LC-обработчик как своя. Если заявка не принадлежит мастеру — 404.
Если кэш источника недоступен — 503 `source_unavailable`.

Конфликты accept / in-progress для KP (HTTP 409):

| `code` | Когда |
|--------|--------|
| `order_already_final` | статус completed / отмены / rejected |
| `invalid_status_transition` | in-progress не из `on_way` |
| `source_write_failed` | КП/Desk не приняли запись (текст в `message`) |
| `source_write_not_configured` | нет `DESK_INTERNAL_TOKEN` / URL записи |

Признак `sources[].status = stale` **не блокирует** accept: запись идёт в живой КП, кэш обновляет Desk.

## Безопасность

- Авторизация GM не менялась: `Authorization: Bearer <GM_API_BEARER_TOKEN>` (`VerifyGmApiBearer`).
- `DESK_API_TOKEN` в GM не передаётся и в ответах не встречается; LC читает хаб напрямую из БД.
- Мастер видит только свои заявки: выборка KP ограничена его строками в `hub_master_links`
  (+ `kp_employee_id`), поэтому подмена `X-GM-User-Id` даёт только заявки того мастера,
  чей ID указан, но никогда — чужие заявки в одном ответе.
- Учётные данные источников (`crm_connections.login/password`) Lead Control не читает и не отдаёт.

## Конфигурация

| Переменная | По умолчанию | Назначение |
|------------|--------------|------------|
| `UNIFIED_HUB_ENABLED` | `true` | включить агрегацию KP-Lead |
| `UNIFIED_HUB_CONNECTION` | `hub` | имя соединения БД Единого окна |
| `UNIFIED_HUB_KP_CRM_ID` | `2` | `crm_connections.id` источника KP-Lead |
| `UNIFIED_HUB_STALE_MINUTES` | `180` | порог «протухшей» синхронизации |
| `UNIFIED_HUB_TIMEZONE` | `UTC` | TZ приложения Единого окна для серверных меток времени |
| `HUB_DB_DATABASE` | `lead_desk` | БД Единого окна |
| `HUB_DB_USERNAME` / `HUB_DB_PASSWORD` | от `DB_*` | на проде — отдельный пользователь **только на SELECT** (`lc_hub_ro`) |
| `UNIFIED_HUB_WRITE_DRIVER` | `http` | `http` = Desk internal API; `cache` = только кэш (тесты) |
| `UNIFIED_HUB_WRITE_URL` / `DESK_PUBLIC_URL` | `https://lead-control.space` | база URL Единого окна |
| `DESK_INTERNAL_TOKEN` | — | общий Bearer LC↔Desk для записи статусов КП |

## Запуск тестов (обязательные предосторожности)

Тесты выполняют DDL (`migrate:fresh`, пересоздание схемы хаба) и умеют писать статусы.
Поэтому:

- `tests/TestCase.php` отказывается стартовать, если БД соединения по умолчанию или `hub`
  не содержит `test` в имени. Частая причина срабатывания — закэшированный конфиг
  (`bootstrap/cache/config.php` перекрывает env из `phpunit.xml`): сначала `php artisan config:clear`.
- `HubOrderWriteService` в окружении `testing` всегда пишет только в кэш и никогда не ходит
  в Единое окно/КП, даже если `DESK_INTERNAL_TOKEN` настроен.
- Пользователь БД для соединения `hub` на проде имеет только `SELECT` на `lead_desk`.

## Известные ограничения

- `clientPhone` у KP-заявок сейчас `null`: список КП, из которого Единое окно наполняет кэш,
  не содержит колонки телефона (карточка заявки её содержит, но массово не выкачивается).
  Поле в контракте присутствует и заполнится, как только Единое окно начнёт складывать телефон в кэш.
- `GET /api/v1/gm/orders/{id}/documents` для KP-заявок отдаёт `items: []` — документы КП
  в кэш Единого окна не попадают; загрузка документов по KP-заявке запрещена (409).
