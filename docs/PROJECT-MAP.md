# Карта проекта Lead Control (LC)

Срез структуры: 17 сентября 2026 года. Карта построена по файлам текущей копии `/var/www/lead-control`; это навигационная схема, а не замена исходному коду и актуальной схеме базы.

## Быстрый маршрут

- Веб-CRM: `public/index.php` → `bootstrap/app.php` → `routes/web.php` → контроллер → сервис → Eloquent-модель → MySQL → Blade в `resources/views/`.
- Внешний API: `public/index.php` → `bootstrap/app.php` → `routes/api.php` → middleware подписи/токена → API-контроллеры и сервисы.
- Фоновые операции: `routes/console.php` → Artisan-команды в `app/Console/Commands/` → jobs/services → очередь и внешние API.
- Асинхронный контур: SuperPart outbox, синхронизация GM, кэш заказов Desk, экспорт отчётов и уведомления.

```mermaid
flowchart LR
    Browser[Браузер] --> Web[/crm/*]
    Partners[SuperPart / GM / Desk / Mango] --> Api[/api/*]
    Web --> Middleware[auth + 2FA + role/city + security]
    Api --> Auth[HMAC / bearer / webhook validation]
    Middleware --> Controllers[HTTP-контроллеры]
    Auth --> Controllers
    Controllers --> Services[Services]
    Services --> Models[Eloquent models]
    Models --> DB[(MySQL LC)]
    Services --> Queue[Jobs / queue]
    Queue --> Integrations[GM / SuperPart / Desk / Mango]
    Scheduler[Laravel scheduler] --> Queue
```

## Технологии и точки входа

| Область | Где смотреть |
|---|---|
| Backend | PHP 8.2+, Laravel 12, `composer.json` |
| Web UI | Blade, `routes/web.php`, `resources/views/` |
| Frontend build | Tailwind CSS 4, Vite 7, `resources/css/`, `resources/js/`, `vite.config.js` |
| Database | MySQL в окружении, `config/database.php`, 70 миграций |
| CLI | `artisan`, `routes/console.php`, 32 команды |
| HTTP entry | `public/index.php`, `bootstrap/app.php` |
| Tests | PHPUnit, `tests/Feature/` и `tests/Unit/` |

`bootstrap/app.php` монтирует веб-маршруты под префиксом `/crm`, API под `/api`, добавляет security headers, абсолютный таймаут сессии, 2FA, bot guard и алиасы `role`, `city`, `desk.api`, `superpart.api`.

## Слои и каталоги

| Каталог | Роль | Текущий масштаб |
|---|---|---:|
| `app/Http/Controllers/` | web, auth и API orchestration | 41 контроллер |
| `app/Http/Middleware/` | роли, города, токены, 2FA, CSRF, throttling, security | 11 middleware |
| `app/Services/` | бизнес-правила, интеграции, отчёты, синхронизация | 55 сервисов |
| `app/Models/` | Eloquent-модели и связи | 48 моделей |
| `app/Jobs/` | отложенная синхронизация, уведомления, экспорт | 9 jobs |
| `app/Console/Commands/` | обслуживание, синхронизации, бэкапы, отчёты | 32 команды |
| `app/Desk/` | адаптеры CRM1/CRM2 и сервисы Единого окна | 5 файлов |
| `app/Support/` | политики, карты статусов, TOTP, helper-классы | 9 файлов |
| `resources/views/` | Blade-страницы и UI-компоненты | 129 файлов |
| `database/migrations/` | эволюция схемы | 70 миграций |
| `database/seeders/` | роли, города, источники, знания, демо/служебные данные | 17 seeders |
| `scripts/` | точечная диагностика и админские операции | 15 скриптов |
| `deploy/` | FTP-деплой и VPS-проверки | `ftp/`, `vps/` |
| `docs/` | API, ТЗ, ADR, runbook, отчёты и история | 69 файлов |

## Пользовательский контур `/crm`

Все рабочие разделы защищены `auth`; доступ дополнительно ограничивают роли и города.

| Раздел | Назначение | Основные точки |
|---|---|---|
| Заказы | список, карточка, создание, проведение, переоткрытие, документы | `OrderController`, `OrderService`, `Order` |
| Персоны | клиенты, телефоны, адреса, поиск, звонок | `PersonController`, `PersonPhoneController`, `AddressController` |
| Касса / CFM | операции, статьи, сводки, зарплата директоров, расчёт с мастерами | `CfmController`, `DirectorSalaryController`, `MasterSettlementController` |
| Управление | города, макеты листовок, источники и импорт источников | `app/Http/Controllers/Management/` |
| HR | сотрудники, мастера, roster, паспортная проверка, чёрный список | `HrController`, `HrService` |
| Отчёты | заказы, отмены, партнёры, города, сотрудники, оборот мастеров, CSV/XLSX | `ReportController`, `ReportExportController` |
| Претензии и отзывы | создание, просмотр, документы, сводный отчёт | `ComplaintController`, `ReviewController` |
| Промо | журнал встреч/назначений, маршруты, действия, выплаты | `PromJournalController`, `PromRoutesController`, `PromActionsController`, `PromPaymentsController` |
| База знаний | статьи и редактор | `KnowledgeController`, `KnowledgeArticle` |
| Настройки | тема, пароль, данные пользователя | `SettingsController` |
| Диспетчерский экран | отдельный dashboard и совместимость со старым `/crm/desk` | `DispatcherDashboardController`, `DeskSsoController` |

Группы web-маршрутов определены в `routes/web.php`: `orders`, `persons`, `cfm`, `management`, `management/sources`, `hr`, `reports`, `complaints`, `prom`, `information`, `settings`, а также auth/SSO/notifications.

## Внешний API `/api`

| Контур | Маршруты | Авторизация и смысл |
|---|---|---|
| Mango Office | `/api/mango/webhook` | webhook телефонии; проверка выполняется обработчиком |
| SuperPart | `/api/v1/partner-orders/*`, `/reference/*`, `/superpart/orders/*` | `VerifySuperPartApi`, HMAC-SHA256; источники, партнёрские заказы, full/delta-снимки |
| Desk | `/api/v1/desk/*` | `VerifyDeskApiBearer`; auth/SSO, города, оргдерево, кэш заказов, статусы и документы |
| Guild Master | `/api/v1/gm/*` | `VerifyGmApiBearer`; профиль, метрики, отзывы, пользователи, оргструктура, заказы и документы |
| Health | `/api/v1/health`, `/api/v1/ops/health` | технические проверки |

Контракт и правила API описаны в `docs/API.md`, интеграции с GM — в `docs/GM-*.md`, с SuperPart — в `docs/SUPERPART_SOURCES.md` и `docs/tz/TZ-sync-LC-SP-2026-09-10.md`.

## Данные и связи

Основное ядро: `Order` связан с `Person` через `order_persons`, с `Address`, `Source`, мастером `User`, комментариями, документами, претензиями, кассовыми операциями и журналом действий. `Person` имеет телефоны, адреса, звонки и заказы.

Оргструктура строится вокруг `User`, `Role`, `City` и pivot-таблиц `user_roles`/`user_cities`; `City` также владеет районами, источниками, адресами, CFM-операциями и расписанием открытия.

Кассовый контур использует `CfmCategory` → `CfmOperation`; операции могут ссылаться на заказ, город, пользователя и `SalaryCalculation`. Промо-контур связывает `Promoter`, `Route`, `RouteAction`, встречи, назначения, банки и выплаты.

Интеграционные модели включают кэш и журналы Desk, связи Hub, `IntegrationApiLog`, outbox SuperPart, версии синхронизации заказа и журналы безопасности.

Особенности схемы, которые нужно проверять перед изменением:

- первичные ключи ядра часто называются `order_id`, `person_id`, `user_id`, а не `id`;
- `Order` отключает Laravel timestamps и хранит `order_created_at`, `order_closed_at`, `status_changed_at` и связанные поля;
- видимость заказов зависит от роли и доступных городов; developer, call center и general director имеют специальные исключения;
- миграции являются историей изменений, а фактическое состояние развёрнутой БД нужно проверять отдельно.

## Фоновые процессы

Расписание в `routes/console.php`:

- каждую минуту: доставка SuperPart outbox, queue worker, синхронизация Desk;
- каждые 5 минут: catch-up заказов в SuperPart и health-check;
- ежечасно: stale/городские срезы отчётов и связь мастеров Hub;
- ежедневно: пересборка отчётов, очистка экспортов, шифрованный бэкап и его проверка.

Jobs в `app/Jobs/` отправляют изменения пользователей и заказов в GM, уведомления и snapshots в SuperPart, удаляют документы GM и выполняют CSV-экспорт.

## Frontend

Основной UI — Blade с layout-файлами `resources/views/layouts/`, повторно используемые элементы — `resources/views/components/ui/`. JS небольшой и прикладной: уведомления, звук, маска телефона, раскрытие телефона, поиск и фильтры дат. Основные CSS-файлы — `resources/css/app.css`, `resources/css/layout.css` и собранный `public/css/app.css`.

Для изменений Blade/CSS читать `docs/DESIGN_SYSTEM.md` и `.cursor/rules/levelion-design-system.mdc`; новые экраны проверять в обеих темах.

## Проверка и эксплуатация

- Feature-тесты покрывают API GM, Desk/Hub, авторизацию, заказы, CFM, телефоны и синхронизации; unit-тесты — сервисы, middleware, статусы, подписи и TOTP.
- Деплойные заготовки находятся в `deploy/ftp/` и `deploy/vps/`; внешние VPS/серверные скрипты лежат в `/root` и не являются частью приложения.
- Текущая копия не содержит `.git` и не имеет локального remote. В `README.md` указан предполагаемый upstream: `git@github.com:masterriadom-jpg/Levelion_dev.git`. Перед Git-операциями нужно проверить фактическое состояние.
- Не читать и не включать в карту значения `.env`; в документации достаточно имён переменных из `config/`.

## Документы по назначению

- API и контракты: `docs/API.md`, `docs/gm-api-mapping.md`, `docs/gm-api-openapi.yaml`.
- Desk/Hub: `docs/DESK.md`, `docs/GM-UNIFIED-HUB-API.md`, `docs/GM-ORG-INTEGRATION.md`.
- UI: `docs/DESIGN_SYSTEM.md`.
- ТЗ и решения: `docs/TZ-*.md`, `docs/tz/`, `docs/ADR-*.md`.
- Эксплуатация: `docs/CUTOVER-*.md`, `docs/BACKUP-*.md`, `docs/OPS_ALERTS.md`, `docs/SECURITY_STAGE_E.md`.
- История изменений: `docs/CHANGELOG.md`.

## С чего начинать задачу

1. Определить контур: web, API, фоновые процессы, данные или деплой.
2. Прочитать соответствующий маршрут/команду и основной контроллер.
3. Найти сервис и модели, где живут проверяемые бизнес-правила.
4. Проверить middleware, роли, city scope и затронутые миграции.
5. Перед изменением внешнего контракта свериться с `docs/API.md` и профильным ТЗ.
