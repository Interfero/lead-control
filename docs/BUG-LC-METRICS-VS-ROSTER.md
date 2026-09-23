# ТЗ: API заявок и metrics GM ↔ LC (дыры статусов, финансы, отписки, возраст + metrics≠roster)

**Кому:** Стёпа (Lead Control)  
**От:** команда GM (repair-guild.ru)  
**Дата:** 2026-09-09  
**Статус:** к реализации  
**Приоритет:** высокий (ломает кабинет заявок и отчёт профиля мастера)  
**Префикс:** `https://lead-control.space/api/v1/gm`  
**Авторизация:** `Authorization: Bearer <GM_API_BEARER_TOKEN>` + `X-GM-User-Id` / `X-GM-Email` / `X-GM-Login`

**Состав документа:**

1. Дыры API заявок (статусы, финансы, отписки, возраст, `in-progress`).
2. Баг `GET /users/me/metrics` ≠ roster / orders (бывший `docs/BUG-LC-METRICS-VS-ROSTER.md`).

После закрытия этого ТЗ на стороне LC команда GM допилит кабинет: статусы/плашки, кнопки по статусу, модалки СД/закрытия, архив Готов/Отказ, скрытие прозвона и отмен КЦ/города; уберёт обход metrics через roster.

Мастер для всех проверок ниже: **#146** Торин (`maksim.torinn@lc.ru`, Нижний Новгород, `city_id=50`).

---

## A. Баг: metrics мастера ≠ roster / orders

**Приоритет:** высокий (ломает отчёт в профиле мастера)

### A.1. Симптом

В кабинете GM у #146:

- закрыто за месяц = **0**
- средний чек = **0**

При этом у него живые закрытия в сентябре.

### A.2. Доказательства (prod, `X-GM-User-Id: 146`)

#### `GET /users/me/metrics` — неверно

```json
{
  "primaryClosedOrdersMonth": 0,
  "primaryAvgCheckRub": 0,
  "masterMonthRefusals": 0,
  "masterMonthCancelledApplications": 2,
  "masterEarnedRub": 49145
}
```

#### `GET /branch/roster?city_id=50` — тот же мастер, верно

В `items` для `id: 146`:

- `closedOrdersMonth`: **14**
- `avgCheckRub`: **7915**
- `roleTier`: `master`
- `trainingBadgeFromReg`: `false`

#### `GET /orders` — подтверждает roster

За сентябрь 2026 (Europe/Moscow) у #146: **14** заявок `status=completed` с `closedAt` в `2026-09-*` (billingCategory `new`).

Отмены **2** (`cancelled_cc` созданные в сентябре) — с metrics сходятся.

### A.3. Ожидание

`GET /users/me/metrics` для текущего месяца (MSK):

- `primaryClosedOrdersMonth` = число закрытых (`completed`) мастера за месяц — **как в roster `closedOrdersMonth`**
- `primaryAvgCheckRub` = средний чек по этим закрытиям — **как в roster `avgCheckRub`**
- оказы / отмены оставить как есть, если формула уже верная

Один источник правды: metrics и roster по одному мастеру не должны расходиться.

### A.4. Не баг GM

GM уже читает metrics; нули приходят из LC. Временный обход в GM — подставлять closed/avg из roster, пока metrics не починят. После фикса обход снимем.

### A.5. Приёмка по metrics

1. У #146 `GET /users/me/metrics` → `primaryClosedOrdersMonth` и `primaryAvgCheckRub` совпадают с `GET /branch/roster?city_id=50` для того же мастера (±округление avg).
2. Числа сходятся с подсчётом `completed` за текущий месяц MSK из `GET /orders`.

---

## B. Дыры API заявок (статусы, финансы, отписки, возраст)

После закрытия блока B команда GM допилит кабинет заявок по согласованному дизайну.

### B.0. Контекст (проверено на prod, 2026-09-09)

#### Список `GET /orders`

Плоский объект заявки, поля есть: `id`, `orderNumber`, `status`, `description`, `address`, `clientName`, `clientPhone`, `equipmentType`, `previousContactsSummary`, `clientContactType`, `techDirectorComment`, `amountRub`, `billingCategory`, `distanceKm`, `createdAt`, `updatedAt`, `closedAt`, `contactUri`.

**Нет в списке:** возраст клиента, `financialSnapshot`, документы, точное время входа в статус.

#### Карточка `GET /orders/{id}`

Дополнительно есть: `documents`, `financialSnapshot`, `timeline`, `assignedMaster`, `branch`, `canCallClient`, `canMessageClient`.

`financialSnapshot` (уже живой):

| Поле | Смысл |
|------|--------|
| `amountPaidRub` | сумма взятых средств |
| `amountCompRub` | сумма комплектующих (ЗПЧ) |
| `netAmountRub` | чистыми (часто = `amountRub` в списке) |
| `masterSalaryRub` | ЗП мастера |
| `orderType` | напр. `new` / `warranty` |
| `orderCore` | напр. `non_core` |

Пример: заявка `540` — snapshot `{ amountPaidRub: 5500, amountCompRub: 1500, netAmountRub: 4000 }` совпадает с отпиской `5500/1500`.

#### Действия, которые уже есть

| Метод | Поведение сейчас |
|-------|------------------|
| `POST /orders/{id}/accept` | перевод в `on_way` |
| `POST /orders/{id}/sd` | перевод в `in_progress_sd`, **тело не принимается / не используется** |
| `POST /orders/{id}/review` | закрытие/на проверку; при пробных телах — Server Error / контракт полей не ясен |
| `POST /orders/{id}/documents` | multipart: `file` + `category` ∈ `receipts` \| `contract` \| `storage_receipt` \| `parts_photos` |

#### Известные баги/дыры в данных списка

1. **`clientContactType`** у всех проверенных заявок = `"phone"` — бесполезен для UI «первичка / повтор / гарантия».  
   В LC UI «Гарантия» фактически сидит в **`billingCategory: warranty`** (иначе `new` и т.п.). Нужен явный стабильный тип контакта/заявки для GM.
2. **`updatedAt`** не всегда меняется при смене статуса (на `489` после `POST …/sd` `updatedAt` остался старым) — нельзя надёжно строить таймер «10 минут в статусе».
3. Возраст клиента в API **не отдаётся** ни в list, ни в detail.

### B.1. Цель

1. GM может честно показывать и двигать заявки мастера без догадок и локальных костылей.
2. В карточке заявки **в самом LC** мастерские отписки СД/закрытия видны в **комментарии филиала** по фиксированному шаблону.
3. В карточке заявки в LC видно **время перевода в «В работе»** (`in_progress`).
4. Финансы (взято / комплектующие) и возраст доступны GM API без обхода через парсинг текста отписки.

### B.2. Статусы для Гильдии (контракт)

GM будет показывать только:

| LC `status` | В гильдии | Видимость |
|-------------|-----------|-----------|
| `callback` (прозвон) | — | **скрыть** |
| `cancelled_cc` / `cancelled_city` (и синонимы отмен КЦ/города) | — | **скрыть** |
| `pending` / ожидает (как сейчас у «новой») | Ожидает | да |
| `on_way` | В пути | да |
| `in_progress` | В работе | да |
| `in_progress_sd` | В работе СД | да |
| `completed` (Готов) | Готов | да (архив) |
| `rejected` (Отказ) | Отказ | да (архив) |

Остальные статусы (не оформлена, черновик и т.п.) — по текущей политике скрытия / не для мастера.

**Просьба:** зафиксировать канонические строки `status` в доке API (без сюрпризов `gotov`/`отказ` vs `completed`/`rejected`).

### B.3. Дыры, которые нужно закрыть

#### B.3.1. `POST /orders/{id}/in-progress` (новый)

**Зачем:** в GM у статуса «В пути» кнопка **«В работе»**. Сейчас эндпоинта нет (`…/in-progress`, `…/work` → 404).

**Поведение:**

- доступно мастеру, за которым заявка;
- только из `on_way` → `in_progress`;
- иначе `422` с понятным кодом (`invalid_status_transition`);
- ответ: `{ "ok": true, "order": { …карточка… } }` в том же shape, что detail.

**Побочный эффект в LC UI:** записать/обновить время входа в «В работе» (см. §B.4).

#### B.3.2. Возраст клиента

**Зачем:** в карточке заявки GM рядом с именем клиента.

Добавить в **list и detail**:

| Поле | Тип | Правило |
|------|-----|---------|
| `clientAge` | number \| null | полных лет; `null` если неизвестно |

Источник — персона/карточка клиента в LC (как в UI CRM, если возраст там уже есть).

#### B.3.3. Тип заявки: первичка / повтор / гарантия

Сейчас `clientContactType: "phone"` ломает UI (Гарантия в LC → «Первичная» в GM).

**Нужно одно стабильное поле** в list и detail, например:

| Поле | Тип | Значения |
|------|-----|----------|
| `orderKind` | string | `first` \| `repeat` \| `warranty` |

Либо починить `clientContactType`, чтобы он реально нёс эти значения (не `phone`).  
`billingCategory` при этом можно оставить как сейчас (`new` / `warranty` / …), но для бейджа «Гарантия/Первичка» GM должен опираться на `orderKind` (или исправленный contact type), не на эвристики.

#### B.3.4. Финансы в list + запись при закрытии

##### Чтение

`financialSnapshot` уже есть в **detail**. Нужно:

1. **Включить тот же объект в `GET /orders` (list)** — иначе GM либо N+1 ходит в detail, либо показывает нули.
2. Либо query `?include=financialSnapshot` с тем же shape.

Минимум полей snapshot (уже есть — сохранить имена):

- `amountPaidRub`, `amountCompRub`, `netAmountRub`, `masterSalaryRub`, `orderType`, `orderCore`

##### Запись при закрытии (`POST /orders/{id}/review`)

Сейчас GM шлёт пустой POST; суммы/отписка некуда передать; на части заявок — Server Error.

**Контракт тела (JSON), минимум:**

```json
{
  "amountPaidRub": 5500,
  "amountCompRub": 1500,
  "masterComment": "текст отписки мастера при закрытии"
}
```

Правила:

- `amountPaidRub` ≥ 0, `amountCompRub` ≥ 0;
- `netAmountRub` = `amountPaidRub - amountCompRub` (считает LC, не доверять клиенту);
- обновить `financialSnapshot` и итог заявки (`amountRub` / net — как принято в LC);
- после успеха статус → `completed` или ваш канон «Готов» / review-flow — **зафиксировать в ответе**;
- `masterComment` обязателен (непустая строка) и уходит в комментарий филиала по шаблону §B.5.2.

Отказ клиента (если закрывается как отказ) — отдельный код/флаг или отдельный статус `rejected`; не смешивать с `cancelled_*`.

#### B.3.5. Отписка при переводе в СД (`POST /orders/{id}/sd`)

Расширить приём JSON (или multipart рядом с уже существующей загрузкой `storage_receipt`):

```json
{
  "masterComment": "текст отписки мастера при переводе в СД"
}
```

Правила:

- `masterComment` обязателен;
- статус → `in_progress_sd`;
- комментарий филиала — по шаблону §B.5.1;
- документы (сохранка) по-прежнему через `POST …/documents` с `category=storage_receipt` (можно до/сразу после sd — описать порядок в ответе/доке).

#### B.3.6. Время входа в статус (для таймера и UI)

**Зачем:**

- в LC в карточке заявки показать, **когда перевели в «В работе»**;
- в GM у «В пути» кнопка «Позвонить» появляется через **10 минут** в статусе — нужен надёжный timestamp, не `updatedAt`.

Добавить в list и detail:

| Поле | Тип | Смысл |
|------|-----|--------|
| `statusChangedAt` | string ISO-8601 | момент последней смены `status` |
| `inProgressAt` | string ISO-8601 \| null | момент перевода именно в `in_progress` |

`inProgressAt` не затирать при уходе в СД/закрытие (история входа в работу).  
При каждом переходе обновлять `statusChangedAt`.

Опционально удобно для timeline: события `status_changed` с `from`/`to`/`at`.

### B.4. Отображение внутри заявки в UI LC

В карточке заявки Lead Control должно быть видно:

1. **Время перевода в статус «В работе»** (`inProgressAt`) — отдельное поле/строка в карточке, не только в общем updated.
2. **Отписка СД** и **отписка при закрытии** — через комментарий филиала (шаблоны ниже), чтобы диспетчер/директор видели текст без отдельного канала.

### B.5. Комментарий филиала — шаблоны отписок мастера

В LC у заявки есть параметр **«комментарий филиала»**.  
Когда мастер из GM отправляет отписку, LC **дописывает** в этот комментарий блоки (append, не затирая предыдущее содержимое; между блоками — пустая строка).

#### B.5.1. При переводе в СД (`POST …/sd` + `masterComment`)

Добавить в комментарий филиала:

```text
**Отписка мастера при переводе в СД**
{текст masterComment}
```

Заголовок — **точно** эта строка (markdown `**…**` как в примере).

#### B.5.2. При закрытии (`POST …/review` + `masterComment`)

Добавить в комментарий филиала:

```text
**Отписка мастера при закрытии заявки**
{текст masterComment}
```

Тоже дописывать снизу, не удаляя блок СД и прочие служебные пометки.

#### B.5.3. Отдача в API

Чтобы GM мог показать те же отписки у себя:

- либо отдельное поле `branchComment` (полный текст) в detail/list;
- либо structured:

```json
{
  "masterSdComment": "…",
  "masterCloseComment": "…"
}
```

Предпочтительно **и** полный `branchComment`, **и** структурированные поля (GM не парсит markdown).

### B.6. Документы (без изменений категорий, только док)

Оставить как есть:

| UI GM | `category` |
|-------|------------|
| Чек СМЗ | `receipts` |
| БСО | `contract` |
| Сохранка | `storage_receipt` |
| Чеки ЗПЧ | `parts_photos` |

В detail `documents[]` уже приходит — ок. В list достаточно `hasDocuments: boolean` или короткого массива (по желанию).

### B.7. Сводка эндпоинтов после доработки

| Метод | Назначение |
|-------|------------|
| `GET /orders` | список + `clientAge`, `orderKind` (или починенный contact type), `statusChangedAt`, `inProgressAt`, `financialSnapshot`, отписки |
| `GET /orders/{id}` | то же + documents/timeline |
| `POST /orders/{id}/accept` | → `on_way` |
| `POST /orders/{id}/in-progress` | **новый** → `in_progress`, пишет `inProgressAt` |
| `POST /orders/{id}/sd` | body `{ masterComment }` → `in_progress_sd` + блок в комментарий филиала |
| `POST /orders/{id}/review` | body `{ amountPaidRub, amountCompRub, masterComment }` → закрытие/готов + финансы + блок в комментарий филиала |
| `POST /orders/{id}/documents` | без смены категорий |
| `GET /users/me/metrics` | см. блок A — closed/avg как roster |

### B.8. Критерии приёмки (заявки)

1. У мастера #146 в `GET /orders` у завершённых с комплектующими `financialSnapshot.amountCompRub > 0` без доп. `GET /orders/{id}`.
2. `clientAge` присутствует (number или null) в list/detail.
3. Бейдж гарантии/первички однозначно читается из нового/починенного поля (не всегда `phone`).
4. `POST …/in-progress` из `on_way` → `in_progress`, в карточке LC видно `inProgressAt`.
5. `POST …/sd` с `masterComment` дописывает в комментарий филиала блок **Отписка мастера при переводе в СД**.
6. `POST …/review` с суммами и `masterComment` обновляет snapshot и дописывает блок **Отписка мастера при закрытии заявки**.
7. `statusChangedAt` обновляется при каждой смене статуса (проверка: после sd/in-progress timestamp свежий).
8. Короткая секция в `docs/API.md` LC с примерами request/response.

---

## C. Вне скоупа

- Скрытие прозвона/отмен в UI GM (сделаем на стороне GM после вашего API).
- Вёрстка кабинета GM / модалки / пагинация архива.
- Изменение категорий документов.
- Снятие временного обхода metrics→roster в GM (сделаем после фикса блока A).

---

## D. Контакты

Вопросы по контракту — в тот же канал, что ТЗ профиля.  
После выкладки на prod — пинг GM: прогоним приёмку по §A.5 и §B.8 и включим кабинет заявок / уберём обход metrics.
