# Журнал разработки Lead Control

## 18.09.2026 — GM: мастер сохраняет свой ИНН

- [routes/api.php](../routes/api.php): `PATCH /api/v1/gm/users/me/inn` (+ throttle `gm-update-inn`).
- [app/Http/Controllers/Api/GmController.php](../app/Http/Controllers/Api/GmController.php), [app/Services/GmApiService.php](../app/Services/GmApiService.php): запись в `users.user_inn` по identity `X-GM-*` (без тихой очистки ввода, без требования паспортной верификации).
- [app/Providers/AppServiceProvider.php](../app/Providers/AppServiceProvider.php): rate limit 10 / 15 мин → `429 too_many_requests`.
- Тесты: [tests/Feature/GmUpdateInnApiTest.php](../tests/Feature/GmUpdateInnApiTest.php); доки: [docs/API.md](API.md).

## 17.09.2026 — Карта проекта и правила для Codex

- Добавлены [AGENTS.md](../AGENTS.md) с проектными указаниями для Codex и [PROJECT-MAP.md](PROJECT-MAP.md) с навигацией по слоям, доменам, API, данным, фоновой обработке и эксплуатации.
- Добавлен краткий обязательный журнал задач [PROJECT-LOG.md](PROJECT-LOG.md); правило записи закреплено в [AGENTS.md](../AGENTS.md).
- В `README.md` добавлена ссылка на карту проекта.

## 26.06.2026 - GM integration merge into Desktop LC release copy

- Created prepared release copy at `C:\CRM_MAIN\LC_GM_RELEASE_20260626\lead-control.space.app`; original `C:\Users\666\Desktop\lead-control.space.app` was not edited.
- [routes/api.php](routes/api.php): restored `GET /api/v1/health` and protected `/api/v1/gm/*` routes while preserving existing Mango and SuperPart routes.
- [config/services.php](config/services.php): restored `services.gm_api` env-name config block without secrets.
- [app/Http/Controllers/HrController.php](app/Http/Controllers/HrController.php), [resources/views/hr/create.blade.php](resources/views/hr/create.blade.php), [resources/views/hr/edit.blade.php](resources/views/hr/edit.blade.php): restored GM login/password display and employee sync to GM.
- [app/Http/Controllers/OrderController.php](app/Http/Controllers/OrderController.php), [app/Http/Controllers/DocumentController.php](app/Http/Controllers/DocumentController.php): restored LC -> GM order sync and order document delete sync.
- [app/Models/Order.php](app/Models/Order.php), [app/Services/OrderVisibilityService.php](app/Services/OrderVisibilityService.php): restored status `review` / `Проверка` without migrations.
- [docs/GM_MERGE_2026-06-26.md](docs/GM_MERGE_2026-06-26.md): added detailed merge report; `.env`, DB, migrations and secrets were not touched.

## 14.06.2026 - GM integration merge into ?????????? LC

- [routes/api.php](routes/api.php): added `/api/v1/health` and protected `/api/v1/gm/*` routes without removing existing Mango and SuperPart API routes.
- [app/Http/Controllers/Api/GmController.php](app/Http/Controllers/Api/GmController.php), [app/Http/Middleware/VerifyGmApiBearer.php](app/Http/Middleware/VerifyGmApiBearer.php), [app/Services/GmApiService.php](app/Services/GmApiService.php): added GM API for profile, metrics, reviews, orders, accept, review, SD and order document upload.
- [app/Services/GmUserSyncService.php](app/Services/GmUserSyncService.php), [app/Services/GmOrderSyncService.php](app/Services/GmOrderSyncService.php): added LC -> GM user/order sync and GM document delete sync.
- [app/Http/Controllers/HrController.php](app/Http/Controllers/HrController.php), [resources/views/hr/create.blade.php](resources/views/hr/create.blade.php), [resources/views/hr/edit.blade.php](resources/views/hr/edit.blade.php): added GM login/password display and employee sync to GM.
- [app/Http/Controllers/OrderController.php](app/Http/Controllers/OrderController.php), [app/Http/Controllers/DocumentController.php](app/Http/Controllers/DocumentController.php): added order update sync and order document delete sync to GM.
- [app/Models/Order.php](app/Models/Order.php), [app/Services/OrderVisibilityService.php](app/Services/OrderVisibilityService.php): added LC status `review` / "????????" without migrations.
- [docs/GM_MERGE_2026-06-14.md](docs/GM_MERGE_2026-06-14.md): added detailed transfer report; `.env`, secrets, DB and migrations were not touched.

## 13.04.2026 — SuperPart: каталог источников в API, назначение партнёра, source_id в заказе

- [database/migrations/2026_04_13_120000_add_available_for_superpart_to_sources_table.php](database/migrations/2026_04_13_120000_add_available_for_superpart_to_sources_table.php): в таблицу `sources` добавлено поле `available_for_superpart` (boolean, по умолчанию `false`) — участие источника в справочнике для приложения SuperPart.
- [app/Models/Source.php](app/Models/Source.php): в `fillable` и `casts` добавлено `available_for_superpart`.
- [app/Http/Controllers/Management/SourceManagementController.php](app/Http/Controllers/Management/SourceManagementController.php): в списке источников фильтр «Все» / «Для SuperPart» (`?superpart=1`); при создании/обновлении сохраняется `available_for_superpart`; из валидации формы убрано поле `superpart_partner_id` (в CRM больше не задаётся вручную).
- [resources/views/management/sources/index.blade.php](resources/views/management/sources/index.blade.php): переключатель вкладками-ссылками, колонка «В каталоге SuperPart», колонка «ID партнёра SP».
- [resources/views/management/sources/create.blade.php](resources/views/management/sources/create.blade.php), [resources/views/management/sources/edit.blade.php](resources/views/management/sources/edit.blade.php): чекбокс «Показывать в каталоге SuperPart»; на редактировании `superpart_partner_id` только для просмотра.
- [app/Http/Controllers/Api/PartnerReferenceController.php](app/Http/Controllers/Api/PartnerReferenceController.php): `GET /api/v1/reference/sources` — активные источники с `available_for_superpart` (поля `source_id`, `source_name`, `city_id`, `city_name`, `superpart_partner_id`); `PATCH /api/v1/reference/sources/{source_id}` — обновление `superpart_partner_id` (ключ в теле обязателен, значение `null` или integer ≥ 1), только для источников из каталога SuperPart.
- [routes/api.php](routes/api.php): маршруты `api.v1.reference.sources` и `api.v1.reference.sources.update-partner`.
- [app/Http/Controllers/Api/PartnerOrderController.php](app/Http/Controllers/Api/PartnerOrderController.php): в теле `POST /partner-orders` допускается необязательное поле `source_id` (exists по `sources.source_id`).
- [app/Services/PartnerOrderIngestService.php](app/Services/PartnerOrderIngestService.php): при переданном положительном `source_id` заказ создаётся с этим источником при проверках активности, `available_for_superpart` и совпадения `superpart_partner_id` с `partner_user_id` (если закрепление задано); если `source_id` не передан — по-прежнему используется `SUPERPART_DEFAULT_SOURCE_ID` из конфига.
- [docs/SUPERPART_SOURCES.md](docs/SUPERPART_SOURCES.md): инструкция для разработчиков SuperPart (аутентификация, три сценария, порядок внедрения).

## 13.04.2026 — Управление городами: часовой пояс в формате «МСК±N»

- [app/Helpers/TimezoneHelper.php](app/Helpers/TimezoneHelper.php): метод `mskOffsetLabel(string $ianaTimezone)` — смещение относительно `Europe/Moscow` на текущую дату; формат `МСК+0`, `МСК+2`, `МСК-1`, при нецелом часе — `МСК+5:30` и т.п.; добавлены `diffSecondsFromMsk()` и `uniqueIdentifiersForMskSelect(?string $ensureInclude)` — по одному IANA на каждое уникальное смещение относительно Москвы «сейчас» (в группе с тем же смещением: если есть `Europe/Moscow` — выбирается она, иначе лексикографически первый идентификатор), сортировка с запада на восток; для формы редактирования при необходимости добавляется сохранённый в городе IANA, если его нет среди канонических.
- [app/Http/Controllers/Management/CityManagementController.php](app/Http/Controllers/Management/CityManagementController.php): вместо полного `DateTimeZone::listIdentifiers()` в формы создания/редактирования передаётся сокращенный список из `TimezoneHelper::uniqueIdentifiersForMskSelect()`.
- [resources/views/management/cities/index.blade.php](resources/views/management/cities/index.blade.php): в таблице городов в колонке «Часовой пояс» выводится метка «МСК±N» вместо строки IANA.
- [resources/views/management/cities/create.blade.php](resources/views/management/cities/create.blade.php), [resources/views/management/cities/edit.blade.php](resources/views/management/cities/edit.blade.php): в списке выбора подпись опции — только «МСК±N»; в `value` по-прежнему IANA для сохранения в БД.

## 13.04.2026 — Управление: справочники макетов листовок и источников заказов

- [routes/web.php](routes/web.php): в группе `management` (middleware `role:developer,general_director`) добавлены префиксы `flyer-makets` и `sources` — маршруты `management.flyer-makets.*` и `management.sources.*` (index, create, store, edit, update).
- [app/Http/Controllers/Management/FlyerMaketController.php](app/Http/Controllers/Management/FlyerMaketController.php): CRUD для модели `FlyerMaket` (название, активность); без удаления записей.
- [app/Http/Controllers/Management/SourceManagementController.php](app/Http/Controllers/Management/SourceManagementController.php): CRUD для модели `Source` — поля `source_name`, `source_phone` (уникальный, nullable), `source_format`, `city_id`, `flyer_maket_id`, `superpart_partner_id`, `is_active`; списки городов и макетов; для выбора макетов при редактировании неактивного макета у текущего источника макет остаётся в списке; без удаления источников.
- [resources/views/management/flyer-makets/](resources/views/management/flyer-makets/) и [resources/views/management/sources/](resources/views/management/sources/): страницы списка и форм создания/редактирования в стиле раздела «Города» (breadcrumbs, `x-ui.card`, таблица со строкой-кликом).
- [app/Services/NavigationService.php](app/Services/NavigationService.php): в подменю «Управление» добавлены пункты «Макеты листовок» (`management.flyer-makets.index`) и «Источники заказов» (`management.sources.index`) между «Города» и «Кассовые категории»; видимость по-прежнему через `canAccessManagement` (роли `developer`, `general_director`).

## 13.04.2026 — SuperPart: webhook order-completed с профильностью и видом техники

- [app/Services/PartnerApiService.php](app/Services/PartnerApiService.php): в JSON для `POST …/api/v1/webhooks/order-completed` добавлены поля `order_core` и `equipment_type` (как в заявке CRM), чтобы SuperPart получал те же признаки при проведении заказа, что и при webhook `partner-order-created`.

## 12.04.2026 — Уведомления филиалу: интервал опроса 3 с, бейдж только при ненулевом счёте

- Для отчёта о проделанной работе (функционал «новые заявки без просмотра филиалом»): роли **старший менеджер** и **руководитель филиала** — опрос JSON `GET /notifications/unseen-city-orders-count` каждые **3 секунды**; ответ содержит `count` и `unseen_order_ids`; на клиенте сравнение снимка ID в `sessionStorage`, при появлении новых ID — **системное уведомление** браузера «Новый заказ ID N» (после разрешения уведомлений), клик открывает карточку; полоса «Разрешить уведомления» под шапкой; счётчик у пункта «Заказы» **скрыт при нуле** (атрибут `hidden` + обновление из JS). Ограничения: HTTPS; без разрешения уведомления окна не показываются; фоновая вкладка может реже выполнять таймер; iOS Safari — ограничения платформы. Спам по смыслу не от интервала, а от **diff новых ID** (не от каждого тика).
- [resources/js/app.js](resources/js/app.js): `CITY_UNSEEN_POLL_MS = 3000`; `updateBadge` переключает атрибут `hidden` у бейджа.
- [resources/views/layouts/app.blade.php](resources/views/layouts/app.blade.php): начальное скрытие бейджа при нуле через `hidden` / `aria-hidden`.

## 12.04.2026 — Оповещения: системные уведомления «Новый заказ ID …» вместо MP3

- [app/Services/OrderCityViewService.php](app/Services/OrderCityViewService.php): метод `getUnseenCityOrderIds()` — список ID «непросмотренных филиалом» заявок (до 500) для сравнения на клиенте.
- [app/Http/Controllers/OrderNotificationController.php](app/Http/Controllers/OrderNotificationController.php): JSON `unseen_order_ids` рядом с `count`.
- [resources/views/layouts/app.blade.php](resources/views/layouts/app.blade.php): `data-city-orders-base-url`, `data-city-notify-icon`; полоса «Разрешить уведомления»; убраны data-атрибуты для MP3.
- [resources/js/app.js](resources/js/app.js): Web Notifications API — при появлении новых ID в опросе показывается системное уведомление с текстом «Новый заказ ID N», клик по уведомлению открывает карточку; запасной текст при росте счётчика без списка ID; вибрация на поддерживаемых устройствах; запрос разрешения при загрузке и кнопка; снимок ID в `sessionStorage` для diff.
- [deploy/ftp/deploy_260412_2.sh](deploy/ftp/deploy_260412_2.sh): обновлены имена Vite-чанков, убрана выгрузка `public/sounds/order-notify.mp3` из списка.

## 12.04.2026 — Просмотры заявок филиалом (П), счётчик и звук в шапке

- [database/migrations/2026_04_12_140000_create_order_city_views_table.php](database/migrations/2026_04_12_140000_create_order_city_views_table.php): таблица `order_city_views` (`order_id`, `user_id`, `viewed_at`, уникальная пара заказ–пользователь, внешние ключи на `orders` и `users`).
- [app/Models/OrderCityView.php](app/Models/OrderCityView.php), [app/Models/Order.php](app/Models/Order.php): модель и связь `cityViews()`.
- [app/Services/OrderCityViewService.php](app/Services/OrderCityViewService.php): запись первого просмотра карточки для ролей `senior_manager` и `branch_head` при доступе к городу заказа; подсчёт заказов текущего месяца без просмотра филиалом (как у списка заказов по умолчанию: видимые статусы, без закрытых, без фильтров формы); строки для блока на карточке заказа.
- [app/Http/Controllers/OrderController.php](app/Http/Controllers/OrderController.php): вызов сервиса в `show`; в `index` — `withExists('cityViews')`; в представление передаётся `orderCityViewLines`.
- [app/Http/Controllers/OrderNotificationController.php](app/Http/Controllers/OrderNotificationController.php), [routes/web.php](routes/web.php): `GET /notifications/unseen-city-orders-count` (`notifications.unseen-city-orders-count`), JSON `{ "count": N }`.
- [app/Providers/AppServiceProvider.php](app/Providers/AppServiceProvider.php): для пользователей с ролями `senior_manager` / `branch_head` в макет передаётся начальное значение `unseenCityOrdersCount`.
- [resources/views/layouts/app.blade.php](resources/views/layouts/app.blade.php): ссылка на список заказов с бейджем-счётчиком, `data-*` для опроса и URL звука `asset('sounds/order-notify.mp3')`.
- [resources/js/app.js](resources/js/app.js): `initCityUnseenPoll()` — опрос раз в 45 с, обновление бейджа, звук при росте счётчика после жеста пользователя (разблокировка аудио).
- [public/sounds/order-notify.mp3](public/sounds/order-notify.mp3): файл уведомления (MP3).
- [resources/views/orders/index.blade.php](resources/views/orders/index.blade.php): колонка «П» — галочка при `city_views_exists` (просмотр старшим менеджером или руководителем филиала).
- [resources/views/orders/show.blade.php](resources/views/orders/show.blade.php): блок «Просмотр филиалом» (ФИО, роль, дата/время).
- [app/Services/OrderVisibilityService.php](app/Services/OrderVisibilityService.php): пункт легенды про колонку «П» для КЦ, ген./разработчика и городских ролей.

## 12.04.2026 — order:create-demo: только Тестоград (city 49), автор user 131

- [app/Console/Commands/CreateDemoOrderCommand.php](app/Console/Commands/CreateDemoOrderCommand.php): по умолчанию `--city=49` (Тестоград), `--creator=131` (заявка «с лица» тестового пользователя); подписи клиента/телефона/описания с пометкой Тестоград; при отсутствии пользователя — подсказка запустить сидер.
- [database/seeders/TestogradCliOrderAuthorSeeder.php](database/seeders/TestogradCliOrderAuthorSeeder.php): идемпотентное создание пользователя `user_id=131` (email `testograd_cli_orders@lc.ru`, пароль `password`, роль `order_manager`, город 49) или только привязка города 49, если 131 уже есть.
- [deploy/ftp/deploy_260412_2.sh](deploy/ftp/deploy_260412_2.sh): выгрузка правок команды, сидера и CHANGELOG; в хвосте — команды на сервере (`db:seed --class=TestogradCliOrderAuthorSeeder`, `order:create-demo`).

## 12.04.2026 — Консоль: создание полной тестовой заявки по SSH

- [app/Console/Commands/CreateDemoOrderCommand.php](app/Console/Commands/CreateDemoOrderCommand.php): команда Artisan `order:create-demo` — при каждом запуске создаётся новая персона с уникальным ФИО и 10-значным телефоном, адрес (улица/дом/квартира по опциям) и заказ с полями по правилам создания из интерфейса (тип, профильность по виду техники, источник, статус, `order_created_by`, связь `order_persons`, суммы 0). Опции: `--city`, `--street` (по умолчанию «Тестовая улица»), `--house` (по умолчанию `123`), `--flat`, `--creator`, `--source`, `--status`, `--type`, `--equipment`. На продакшене запуск из корня Laravel: `php83 artisan order:create-demo` (при необходимости параметры).

## 12.04.2026 — Тестоград: отдельный сидер для смены роли менеджеров (без правок TestogradSeeder)

- [database/seeders/TestogradManagersSeniorManagerSeeder.php](database/seeders/TestogradManagersSeniorManagerSeeder.php): для пользователей `testana@lc.ru` и `testmarina@lc.ru` снимается роль `order_manager`, назначается `senior_manager` (менеджер филиала, доступ к заказам); повторный запуск безопасен. Запуск: `php artisan db:seed --class=TestogradManagersSeniorManagerSeeder`. Сидер [TestogradSeeder.php](database/seeders/TestogradSeeder.php) по-прежнему создаёт этих пользователей с `order_manager` — исправление роли выполняется отдельной командой на уже залитой БД.
- Удалён файл [docs/ИСТОРИЯ_РАЗРАБОТКИ.md](docs/ИСТОРИЯ_РАЗРАБОТКИ.md) — дублировал [CHANGELOG.md](docs/CHANGELOG.md). Удалена миграция `2026_04_12_180000_testograd_managers_order_manager_to_senior_manager` (логику заменил сидер).

## 12.04.2026 — Ошибка 403: выход из учётки без ловушки «главная → заказы»

- [resources/views/errors/403.blade.php](resources/views/errors/403.blade.php): для авторизованных пользователей ссылка «← Настройки» на маршрут `settings` вместо `orders.index`; краткий текст о том, что список заказов недоступен и выход — через меню по имени в шапке на странице настроек.
- [resources/views/layouts/error.blade.php](resources/views/layouts/error.blade.php): в шапке макета ошибок для `@auth` — ссылка «Настройки» и форма «Выход» (POST `logout` с CSRF) вместо единственной ссылки «На главную» на `orders.index`, из-за которой пользователи без доступа к заказам (например `order_manager`) попадали в цикл 403.

## 12.04.2026 — Удалены уведомления городов о новых заявках (опрос, звук, бейдж)

- [routes/web.php](routes/web.php): удалён маршрут `GET /api/orders/new-count` (`orders.new-count`) с ограничением ролей городских пользователей.
- [app/Http/Controllers/OrderController.php](app/Http/Controllers/OrderController.php): удалён метод `newCount`, отдававший JSON для клиентского опроса новых заказов по `since`.
- [resources/views/layouts/app.blade.php](resources/views/layouts/app.blade.php): удалены опрос раз в 30 с, звук через Web Audio API, бейдж «+N» у ссылки на заявки и использование `localStorage` (`crm_notif_alerted_ids`, `crm_notif_last_sound`).

## 12.04.2026 — HR: чёрный список только просмотр, снятие с ЧС с карточки, без бейджа «ЧС» в списке, сброс пароля не для мастера

- [resources/views/hr/blacklist.blade.php](resources/views/hr/blacklist.blade.php): страница «Чёрный список» — только таблица и поиск по паспорту/ФИО; удалены колонка с кнопкой удаления из ЧС и клиентский скрипт; `colspan` пустого состояния — 7.
- [resources/views/hr/edit.blade.php](resources/views/hr/edit.blade.php): для пользователей с правами как у прежней кнопки на странице ЧС (генеральный директор, разработчик, автор записи в ЧС) добавлена кнопка «Убрать из чёрного списка» и JS `removeFromBlacklistCard()` (DELETE `hr/{id}/blacklist`); сброс пароля скрыт, если у карточки есть роль `master`.
- [resources/views/hr/index.blade.php](resources/views/hr/index.blade.php): убран бейдж «ЧС» рядом с ФИО в таблице списка сотрудников.
- [app/Http/Controllers/HrController.php](app/Http/Controllers/HrController.php): метод `resetPassword` — единая загрузка сотрудника с `cities` и `roles`; при целевой роли `master` возвращается 403 с текстом о недоступности сброса для мастеров.

## 12.04.2026 — Роли: ОКК, касса, HR, КЦ и «все города»

- [app/Services/NavigationService.php](app/Services/NavigationService.php): для роли `branch_head` (управляющий городом) в разделе «ОКК» снова отображаются «Претензии» и «Отчёт» (раньше у директора филиала в меню был только отчёт).
- [app/Http/Controllers/ComplaintController.php](app/Http/Controllers/ComplaintController.php): убраны редиректы `branch_head` со списка и карточки претензии на отчёт; добавлена проверка `ensureComplaintAccessibleToUser` для `show`, `update`, `storeComment` — претензия должна быть в городе пользователя (кроме `developer`).
- [resources/views/orders/show.blade.php](resources/views/orders/show.blade.php): кнопка «Создать претензию» только для ролей `developer` и `call_center` (согласовано с маршрутом `complaints.create`).
- [resources/views/cfm/index.blade.php](resources/views/cfm/index.blade.php): скрыты четыре кнопки создания кассовых операций (инкас / расход / приход / перемещение) для `general_director` (для `developer` доступ сохранён); «Отчёт по Кассе» без изменений.
- [routes/web.php](routes/web.php): маршруты отзывов (`complaints.reviews*`, в т.ч. изображение) вынесены в группу с `middleware('role:developer,call_center')`; остальные маршруты претензий — прежний набор ролей.
- [app/Services/NavigationService.php](app/Services/NavigationService.php): пункт «Отзывы» в ОКК только для `developer` и `call_center`; «Чёрный список» скрыт для `senior_manager` и `tech_director` (без `developer`); «График мастеров» скрыт для `tech_director` (без `developer`).
- [app/Http/Controllers/ReviewController.php](app/Http/Controllers/ReviewController.php): убраны редиректы по `branch_head` — доступ задаётся маршрутами.
- [database/migrations/2026_04_12_120000_add_access_all_cities_to_users_table.php](database/migrations/2026_04_12_120000_add_access_all_cities_to_users_table.php): колонка `users.access_all_cities` (bool); для роли КЦ — флаг и очистка `user_cities`; для пользователей с полным набором активных городов в pivot — выставлен флаг.
- [app/Models/User.php](app/Models/User.php): `hasAccessToCity` — для `call_center` и при `access_all_cities` доступ ко всем городам; метод `hasAllCitiesAccess()` для отображения в HR.
- [app/Http/Controllers/OrderController.php](app/Http/Controllers/OrderController.php): для `call_center` в фильтрах списка заказов подставляются все активные города (как у `developer` / `general_director`).
- [app/Http/Controllers/HrController.php](app/Http/Controllers/HrController.php): создание/обновление сотрудника — роль КЦ без выбора городов, `access_all_cities` и синхронизация `user_cities`; для `developer`/`general_director` — чекбокс «Все города»; JSON `show` содержит `access_all_cities`.
- [app/Services/HrService.php](app/Services/HrService.php): в выборке сотрудников учтены `access_all_cities` и роль `call_center` при фильтре по городам текущего пользователя и при фильтре по `city_id` в таблице.
- [resources/views/hr/index.blade.php](resources/views/hr/index.blade.php), [resources/views/hr/create.blade.php](resources/views/hr/create.blade.php), [resources/views/hr/edit.blade.php](resources/views/hr/edit.blade.php): подпись «все города» / «все города (диспетчер КЦ)», чекбокс и скрытие выбора городов для КЦ и режима «все города», JS переключения роли на карточке.

## 11.04.2026 — SuperPart POST /partner-orders: ответ JSON при ошибке БД (500) вместо «пустого» исключения

- [app/Http/Controllers/Api/PartnerOrderController.php](app/Http/Controllers/Api/PartnerOrderController.php): при `QueryException` логируется сообщение в `storage/logs/laravel.log`, клиенту возвращается JSON с подсказкой по типичным причинам (нет колонки `partner_user_id`, нет таблиц `partner_order_idempotency` / `integration_api_logs` — выполнить `php83 artisan migrate --force`); при любом другом исключении — лог с классом/файлом/строкой и JSON с напоминанием смотреть `laravel.log`.
- [deploy/ftp/deploy_260411_6.sh](deploy/ftp/deploy_260411_6.sh): выгрузка только `PartnerOrderController.php`.

## 11.04.2026 — SuperPart: понятные 503 при неверном SUPERPART_DEFAULT_SOURCE_ID

- [app/Services/PartnerOrderIngestService.php](app/Services/PartnerOrderIngestService.php): проверка источника для партнёрских заказов разделена на отдельные случаи — не задан положительный ID в `.env`, в таблице `sources` нет строки с указанным `source_id`, источник есть, но `is_active=false`; в теле ответа 503 теперь явно указан проблемный `source_id` и подсказка (вместо общего «активный источник»).
- [deploy/ftp/deploy_260411_5.sh](deploy/ftp/deploy_260411_5.sh): выгрузка только `PartnerOrderIngestService.php` и команда `php83 artisan optimize:clear` на сервере.

## 11.04.2026 — Деплой SuperPart API на прод: полный FTP-скрипт и чеклист SSH

- Причина отсутствия `api/v1/*` на сервере: частичные выгрузки (например только `PartnerOrderController`) без [routes/api.php](routes/api.php) и [bootstrap/app.php](bootstrap/app.php); либо устаревший кэш маршрутов в `bootstrap/cache/`.
- [deploy/ftp/deploy_260411_4.sh](deploy/ftp/deploy_260411_4.sh): новый скрипт поштучной FTP-выгрузки для входящего API SuperPart — `routes/api.php`, `bootstrap/app.php`, `config/services.php`, [app/Http/Middleware/VerifySuperPartApi.php](app/Http/Middleware/VerifySuperPartApi.php), [app/Http/Controllers/Api/IntegrationPingController.php](app/Http/Controllers/Api/IntegrationPingController.php), [PartnerOrderController](app/Http/Controllers/Api/PartnerOrderController.php), [PartnerReferenceController](app/Http/Controllers/Api/PartnerReferenceController.php), [MangoWebhookController](app/Http/Controllers/Api/MangoWebhookController.php) (тот же файл маршрутов), [app/Services/PartnerOrderIngestService.php](app/Services/PartnerOrderIngestService.php), [app/Models/IntegrationApiLog.php](app/Models/IntegrationApiLog.php), [app/Models/Order.php](app/Models/Order.php) (`partner_user_id`), [database/migrations/2026_04_06_120000_partner_superpart_integration.php](database/migrations/2026_04_06_120000_partner_superpart_integration.php). В конце скрипта — блок команд для сервера: `php83 artisan route:clear`, `php83 artisan optimize:clear`, `php83 artisan config:clear`, опционально `migrate --force`, проверка `route:list | grep partner-orders`, напоминание про `.env` (`SUPERPART_API_KEY`, `SUPERPART_API_SECRET`, `SUPERPART_ORDER_AUTHOR_USER_ID`, `SUPERPART_DEFAULT_SOURCE_ID`).
- Локальная проверка репозитория: `php artisan route:list` содержит `POST api/v1/partner-orders`, `GET api/v1/ping`, справочники `api/v1/reference/*`.

## 11.04.2026 — Источники заказов: расширение модели Source и справочник макетов листовок

- [database/migrations/2026_04_11_120000_create_flyer_makets_table.php](database/migrations/2026_04_11_120000_create_flyer_makets_table.php): создана таблица `flyer_makets` (`flyer_maket_id`, `flyer_maket_name`, `is_active`, `timestamps`) — отдельный справочник макетов для источников (без связи с `route_makets` / промо).
- [database/migrations/2026_04_11_120001_extend_sources_table.php](database/migrations/2026_04_11_120001_extend_sources_table.php): у таблицы `sources` снят UNIQUE с `source_name`; добавлены поля `source_phone` (nullable, UNIQUE), `source_format` (enum `online` / `offline`, nullable), `city_id` (FK → `cities`, nullable), `flyer_maket_id` (FK → `flyer_makets`, nullable, `nullOnDelete`), `superpart_partner_id` (unsignedBigInteger, nullable).
- [app/Models/FlyerMaket.php](app/Models/FlyerMaket.php): модель справочника макетов; связь `sources()`.
- [app/Models/Source.php](app/Models/Source.php): константы `FORMAT_ONLINE` / `FORMAT_OFFLINE`; fillable для новых полей; связи `city()`, `flyerMaket()`; аксессор `display_label` (название · город · телефон · онлайн/офлайн) для селектов при неуникальных названиях.
- [app/Models/City.php](app/Models/City.php): связь `sources()`.
- [app/Http/Controllers/OrderController.php](app/Http/Controllers/OrderController.php): загрузка `source.city` в списке заказов, `getData`, `show`; выборки источников с `with('city')` и сортировкой по `source_name`; в JSON `source_name` отдаётся как `display_label`.
- [app/Http/Controllers/PersonController.php](app/Http/Controllers/PersonController.php): источники с `with('city')`; в карточке персоны для заказов и звонков — `orders.source.city`, `calls.source.city`.
- [app/Http/Controllers/ReportController.php](app/Http/Controllers/ReportController.php): отчёт по партнёрам — список источников с `with('city')`.
- [resources/views/orders/create.blade.php](resources/views/orders/create.blade.php), [resources/views/persons/create.blade.php](resources/views/persons/create.blade.php), [resources/views/reports/partners.blade.php](resources/views/reports/partners.blade.php): подписи опций — `display_label`.
- [resources/views/orders/show.blade.php](resources/views/orders/show.blade.php), [resources/views/persons/show.blade.php](resources/views/persons/show.blade.php), [resources/views/orders/index.blade.php](resources/views/orders/index.blade.php): отображение источника и копирование в буфер — через `display_label` там, где нужна различимость.
- [database/seeders/SourceSeeder.php](database/seeders/SourceSeeder.php): комментарий о допустимости одинаковых названий у разных источников вручную.

## 11.04.2026 — Настройки и навбар: ID пользователя, отступы карточек, блок разработчика

- [resources/views/settings/index.blade.php](resources/views/settings/index.blade.php): убран дублирующий заголовок страницы (остались крошки); между крошками и контентом `mb-6`, блоки — `flex flex-col gap-6` вместо `space-y-4`; в «Информация о пользователе» добавлено поле ID (`user_id`, `font-mono`); в конце для роли `developer` — карточка «Блок Разработчика» с кнопкой `x-ui.button` на маршрут `ui-kit`.
- [resources/views/partials/navbar-profile.blade.php](resources/views/partials/navbar-profile.blade.php): рядом с именем отображается идентификатор в виде `· ID {user_id}` в `navbar-profile-id`.
- [resources/css/layout.css](resources/css/layout.css): стили `navbar-profile-id`, увеличен `max-width` у `navbar-profile-name` для строки имя+ID.
- [deploy/ftp/deploy_260411_1.sh](deploy/ftp/deploy_260411_1.sh): в `FILES` добавлены `resources/views/settings/index.blade.php`, `resources/views/partials/navbar-profile.blade.php`, `resources/css/layout.css`; шапка комментария дополнена описанием настроек и навбара.

## 11.04.2026 — Настройки /settings: единый вид с карточками и дизайн-системой

- [resources/views/settings/index.blade.php](resources/views/settings/index.blade.php): контент в `mx-auto max-w-3xl` с `x-breadcrumbs` (Главная → Настройки); блоки «Тема», «Информация о пользователе», «Пароль» / сообщение о недоступности смены пароля — в `x-ui.card`; уведомления — `x-ui.alert` (success / error); тема — `x-ui.button` `variant="outline"`; ФИО и email — `bg-muted` и `border-border` вместо инлайн-цветов; поля пароля — `x-ui.form-group`, `x-ui.input`, кнопка показа пароля как на странице входа; отправка — `x-ui.button`; скрипт переключения темы и валидации формы сохранены.

## 11.04.2026 — База знаний /information: крошки вместо заголовка

- [resources/views/knowledge/index.blade.php](resources/views/knowledge/index.blade.php): вместо `page-header` с `h1` — панель `orders-index-toolbar` с `x-breadcrumbs` (Главная → База знаний) и справа `x-ui.button` «Добавить статью» при `$canEdit`, как на заказах; пустое состояние — подпись через `text-muted-foreground`, кнопка «Создать первую статью» через `x-ui.button`.
- [deploy/ftp/deploy_260411_1.sh](deploy/ftp/deploy_260411_1.sh): в `FILES` добавлен `resources/views/knowledge/index.blade.php`.

## 11.04.2026 — Касса /cfm/editor: крошки и кнопка «Новая статья» как у заказов

- [resources/views/cfm/editor.blade.php](resources/views/cfm/editor.blade.php): убран заголовок с иконкой «Редактор статей ДДС» и верхняя `card` с кнопками; добавлена панель `orders-index-toolbar` с `x-breadcrumbs` (Главная → Касса с ссылкой на `cfm.index` → «Редактор статей») и справа `x-ui.button` «Новая статья» (`type="button"`, `onclick="toggleNewForm()"`), в том же виде что кнопка «Создать заказ» на заказах; ссылка «К списку» убрана — возврат к списку кассы через крошку «Касса».
- [deploy/ftp/deploy_260411_1.sh](deploy/ftp/deploy_260411_1.sh): в массив `FILES` добавлены `resources/views/management/cities/index.blade.php`, `edit.blade.php`, `create.blade.php`, `resources/views/cfm/editor.blade.php`; шапка комментария дополнена описанием этих выгрузок.

## 11.04.2026 — Управление городами (/management/cities): крошки, таблица, открытие редактирования в новой вкладке

- [resources/views/management/cities/index.blade.php](resources/views/management/cities/index.blade.php): панель `orders-index-toolbar` с `x-breadcrumbs` (Главная → Города); кнопка «Добавить город» — `x-ui.button`; список в `x-ui.card` `padding="none"`, таблица `table table-sticky orders-sticky-table`; клик по строке (или Enter/пробел при фокусе) — переход на `management.cities.edit` в той же вкладке (`window.location`); URL в атрибуте через `e($cityEditUrl)`; столбец «Новая вкладка» удалён; статусы — токены `primary` / `muted`; пагинация с разделителем при наличии страниц.
- [resources/views/management/cities/edit.blade.php](resources/views/management/cities/edit.blade.php): крошки (Главная → Города → название и ID); `@section('navbar_context')` с именем города; форма в `x-ui.card`, заголовок и кнопки через `x-ui.button`; ошибки — `text-destructive`.
- [resources/views/management/cities/create.blade.php](resources/views/management/cities/create.blade.php): крошки (Главная → Города → Новый город); форма в `x-ui.card`, те же кнопки и стили ошибок.

## 11.04.2026 — Отзывы: документы как у кассы (КФМ), карточка отзыва

- [app/Services/ReviewDocumentService.php](app/Services/ReviewDocumentService.php): сохранение файлов в `documents/reviews/{id}` и запись в `documents` с `documentable_type` = `App\Models\Review` (те же MIME и лимит 10 МБ, что у КФМ).
- [app/Services/ReviewService.php](app/Services/ReviewService.php): создание отзыва в транзакции с вложениями `documents[]`; список с `documents`; поле `reviews.image` для новых записей не заполняется (старые записи с картинкой в списке и карточке показываются как «старый формат»).
- [app/Models/Review.php](app/Models/Review.php): связь `documents()` morphMany к `Document`.
- [app/Http/Controllers/ReviewController.php](app/Http/Controllers/ReviewController.php): `store` — валидация `documents.*` как у создания КФМ; редирект на `complaints.reviews.show`; метод `show(Review)`; редирект `branch_head` на отчёт в `create` (как в `index`).
- [routes/web.php](routes/web.php): `GET complaints/reviews/{review}` — `complaints.reviews.show`, `whereNumber('review')`, после маршрута `reviews/image`.
- [app/Http/Controllers/DocumentController.php](app/Http/Controllers/DocumentController.php): доступ к файлам отзывов для ролей ОКК в `show` и `download`; удаление — `developer`, `call_center`; для неизвестных `documentable_type` — `abort(403)` перед удалением.
- [resources/views/complaints/reviews/create.blade.php](resources/views/complaints/reviews/create.blade.php): вёрстка `order-show-page` / `cfm-show-layout` — форма слева, справа блок «Документы» (dropzone, очередь файлов до сохранения).
- [resources/views/complaints/reviews/show.blade.php](resources/views/complaints/reviews/show.blade.php): карточка отзыва — реквизиты слева, документы справа (список как на КФМ).
- [resources/views/complaints/reviews.blade.php](resources/views/complaints/reviews.blade.php): колонка «Документы», мини-превью и ссылка «Подробнее» на карточку.
- [deploy/ftp/deploy_260411_1.sh](deploy/ftp/deploy_260411_1.sh): в список выгрузки добавлены сервисы, модель, `DocumentController`, `show.blade.php`.

## 11.04.2026 — Отзывы (/complaints/reviews): крошки, форма на отдельной странице

- [routes/web.php](routes/web.php): `GET complaints/reviews/create` — имя `complaints.reviews.create`, middleware `role:developer,call_center` (как у `complaints.reviews.store`).
- [app/Http/Controllers/ReviewController.php](app/Http/Controllers/ReviewController.php): метод `create()` — шаблон `complaints.reviews.create`.
- [resources/views/complaints/reviews.blade.php](resources/views/complaints/reviews.blade.php): панель `orders-index-toolbar` с `x-breadcrumbs` (Главная → Претензии → Отзывы); справа — `x-ui.button` «Добавить отзыв» только для `developer` и `call_center`; форма добавления удалена; у таблицы убран заголовок «Список отзывов»; блок успешного сохранения остаётся после редиректа со страницы создания.
- [resources/views/complaints/reviews/create.blade.php](resources/views/complaints/reviews/create.blade.php): форма добавления отзыва (поля и превью картинки), крошки до «Добавить отзыв», ссылка «К списку отзывов», скрипт превью изображения.
- [deploy/ftp/deploy_260411_1.sh](deploy/ftp/deploy_260411_1.sh): в список выгрузки добавлены `routes/web.php`, `ReviewController.php`, `complaints/reviews.blade.php`, `complaints/reviews/create.blade.php`.

## 11.04.2026 — Претензии /complaints/report: сетка колонок и полные подписи с переносом в шапке

- [resources/views/complaints/report.blade.php](resources/views/complaints/report.blade.php): `colgroup` с долями ширины; в заголовках типов — полный текст из `Complaint::TYPES`, после `/` вставлен `<wbr>` (`str_replace` после `e()`); сводные колонки — полные подписи; таблица `lang="ru"`; обёртка `overflow-x-auto`; у ячейки города — `title` при обрезке длинного названия.
- [resources/css/app.css](resources/css/app.css), [public/css/app.css](public/css/app.css): `.complaints-report-table` — `table-layout: fixed`; у `thead th` — `text-transform: none` (снятие uppercase с `.table th`), перенос строк в шапке (`white-space: normal`, `overflow-wrap: anywhere`), подписи типов влево, `line-height: 1.35`.
- [deploy/ftp/deploy_260411_1.sh](deploy/ftp/deploy_260411_1.sh): в список выгрузки добавлены `complaints/report.blade.php`, `resources/css/app.css`, `public/css/app.css`, `public/build/manifest.json`, `public/build/assets/app-DAu5N9n3.css`; в шапке — напоминание про `npm run build` и описание отчёта по претензиям.

## 11.04.2026 — Претензии /complaints/report: крошки и таблица как у заказов

- [resources/views/complaints/report.blade.php](resources/views/complaints/report.blade.php): над отчётом — `orders-index-toolbar` и `x-breadcrumbs` (Главная → Претензии с ссылкой на `complaints.index` → «Отчёт»); фильтр периода — `x-ui.card` `padding="none"`, полоса `orders-filters-sticky` с `x-ui.filter-dates` без «Закрыто», кнопки сброса и применения (`x-ui.button` icon, `id="filtersForm"` / `filtersFormSubmit` для `app.js`); таблица — `table table-sticky orders-sticky-table w-full min-w-0 text-sm`; пустое состояние — `px-4 py-8 text-center text-muted-foreground`; у числовых ячеек — `tabular-nums`. Добавлен `@push('scripts')` с автоотправкой формы при изменении дат (blur/Enter), как на `complaints.index`.

## 11.04.2026 — Претензии: фильтры во второй строке таблицы; крошки на создании

- [resources/views/complaints/index.blade.php](resources/views/complaints/index.blade.php): в верхней полосе остались только даты (`x-ui.filter-dates` без «Закрыто») и кнопки сброса/применения; статус, тип, город, текстовый поиск, ID претензии и номер заказа перенесены во вторую строку `thead` (`border-b bg-muted`), как на `orders.index` — `x-ui.filter-input` и `multiselect multiselect-compact` с `status[]`, `type[]`, `city_id[]`; добавлен `@push('scripts')` с логикой мультиселекта и фиксированного выпадающего списка (как у кассы/заказов).
- [app/Http/Controllers/ComplaintController.php](app/Http/Controllers/ComplaintController.php): сбор фильтров в `index` — массивы из `status`, `type`, `city_id`, плюс `search`, `search_id`, `search_order`, даты.
- [app/Services/ComplaintService.php](app/Services/ComplaintService.php): фильтр по типу — `whereIn` по массиву; добавлены точные фильтры `complaint_id` (`search_id`) и `order_id` (`search_order`).
- [resources/views/complaints/create.blade.php](resources/views/complaints/create.blade.php): над формой — `orders-index-toolbar` и `x-breadcrumbs` (Главная → Претензии → Новая претензия).

## 11.04.2026 — Претензии /complaints: крошки, кнопка создания, фильтры в карточке как у заказов

- [resources/views/complaints/index.blade.php](resources/views/complaints/index.blade.php): над списком — `orders-index-toolbar` с `x-breadcrumbs` (Главная → Претензии, главная ведёт на `orders.index`); справа — `x-ui.button` «Создать претензию» для ролей `developer` и `call_center` (как «Создать заказ» на заказах). Вместо отдельных `card` с инлайн-стилями — одна `x-ui.card` `padding="none"`: полоса `orders-filters-sticky` с `x-ui.filter-dates` без полей «Закрыто» (`showClosedDates` false), селекты статуса/типа/города и поле поиска на `x-ui.select` / `x-ui.input` с подписями `text-xs text-muted-foreground`; справа — кнопки сброса и применения (`x-ui.button` primary icon, как на `orders.index`). Таблица — `table table-sticky orders-sticky-table` (липкая шапка через `app.js`); ссылка на заказ — `text-primary`; пагинация — блок «Показано … из …» и `vendor.pagination.leadcontrol`, как у заказов. Убрана обёртка `max-width: 1600px`; автоотправка формы при смене дат/селектов заменена на явное применение фильтра.

## 10.04.2026 — Отчёты: фильтры как у заказов, тёмная тема таблиц

- [resources/views/reports/orders.blade.php](resources/views/reports/orders.blade.php), [resources/views/reports/cancellations.blade.php](resources/views/reports/cancellations.blade.php), [resources/views/reports/partners.blade.php](resources/views/reports/partners.blade.php): вместо устаревших `card`/`form-input`/`btn` — одна `x-ui.card` с полосой `orders-filters-sticky` (`border-b border-border`), `x-ui.filter-dates` без полей «Закрыто от/по» (`showClosedDates` false), справа кнопки сброса и применения (`x-ui.button` primary icon, как на `orders.index`); таблица — `table orders-sticky-table` в `overflow-x-auto`; строка «ИТОГО» — `bg-muted` вместо инлайн `#f3f4f6`.
- [resources/views/reports/partners.blade.php](resources/views/reports/partners.blade.php): выбор партнёра — `x-ui.select` с подписью «Партнёр».
- [resources/views/reports/orders.blade.php](resources/views/reports/orders.blade.php), [resources/views/reports/cancellations.blade.php](resources/views/reports/cancellations.blade.php), [resources/views/reports/partners.blade.php](resources/views/reports/partners.blade.php): над карточкой — `orders-index-toolbar` и `x-breadcrumbs` (Главная → Отчёты с ссылкой на `reports.orders` → название отчёта без ссылки, как в меню «Отчёты»).
- [deploy/ftp/deploy_260410_1.sh](deploy/ftp/deploy_260410_1.sh): в тот же деплой за 10.04.2026 добавлены три шаблона `resources/views/reports/*.blade.php`; в шапке скрипта — описание блока отчётов и примечание про необязательность `npm run build` при выгрузке только Blade отчётов.

## 10.04.2026 — FTP: deploy_260410_1.sh (HR + layout + Vite)

- [deploy/ftp/deploy_260410_1.sh](deploy/ftp/deploy_260410_1.sh): выгрузка изменений сессии — `HrController`, `resources/views/layouts/app.blade.php`, `hr/create`, `hr/edit`, три шаблона `resources/views/reports/*.blade.php`, `resources/css/app.css`, `resources/css/layout.css`, `public/css/app.css`, `database/seeders/TestogradSeeder.php`, `public/build/manifest.json`, `public/build/assets/app-CnnGuIQA.css`, `public/build/assets/app-BA6TlPUe.js`, `docs/CHANGELOG.md`, сам скрипт; проверка даты в имени; на сервере после заливки — `php83 artisan view:clear`, `php83 artisan cache:clear`.

## 10.04.2026 — HR create/edit: сетка полей, комментарии в сайдбаре, ФИО (ID) в крошках и навбаре

- [resources/views/hr/create.blade.php](resources/views/hr/create.blade.php): поля в сетке `hr-employee-form` — строка ФИО; Email / телефон; паспорт / роль / город; дата рождения / работает с.
- [resources/views/hr/edit.blade.php](resources/views/hr/edit.blade.php): та же сетка полей; комментарии перенесены в правую карточку под блоком документов (разделитель `border-t`); переменная `$hrCardContextLabel` — «ФИО (ID user_id)» для крошек, `@section('title')`, `@section('navbar_context')`.
- [resources/views/layouts/app.blade.php](resources/views/layouts/app.blade.php): рядом с логотипом вывод `@yield('navbar_context')` при наличии секции.
- [resources/css/layout.css](resources/css/layout.css): блок `.navbar-brand-group`, подпись `.navbar-page-context`; в медиазапросе до 1024px порядок и отступы для группы.
- [public/css/app.css](public/css/app.css): классы `.hr-employee-form`, `.hr-form-row`, модификаторы `--1` / `--2` / `--3` и адаптив.

## 10.04.2026 — HR: создание как редактирование; email везде сводится к @lc.ru

- [app/Http/Controllers/HrController.php](app/Http/Controllers/HrController.php): `LEGACY_EMPLOYEE_EMAIL_SUFFIX`; `employeeEmailLocalPart()`; `mergeNormalizedEmployeeEmailFromInput()` — вызывается в `store` и `update`, в БД сохраняется `локаль` + `@lc.ru` (в т.ч. при вводе со старым `@level.ion`); в JSON `show` добавлены `email_local` и `email_suffix`.
- [resources/views/hr/create.blade.php](resources/views/hr/create.blade.php): та же вёрстка, что у карточки редактирования (крошки, `cfm-show-layout`, документы справа); у `input files[]` атрибут `form="employeeForm"`, чтобы вложения отправлялись с формой из левой колонки; предупреждение по паспорту — `hr-create-passport-warn`; в `saveEmployee` обрезка локали до `@`, как на edit.
- [resources/views/hr/edit.blade.php](resources/views/hr/edit.blade.php): поле email — `HrController::employeeEmailLocalPart()`.
- [public/css/app.css](public/css/app.css): `hr-create-passport-warn`.

## 10.04.2026 — HR /hr/{id}/edit: крошки, двухколоночная вёрстка, тёмная тема документов, домен @lc.ru

- [resources/views/hr/edit.blade.php](resources/views/hr/edit.blade.php): добавлен `x-breadcrumbs` (Главная → Сотрудники → ФИО); разметка как у кассовой операции — `order-show-page`, `cfm-show-layout`, слева карточка с формой/комментариями/кнопками, справа `cfm-show-sidebar` с блоком «Документы сотрудника»; у дропзона убраны инлайн светлые цвета, используется класс `dropzone` и компактный `hr-dropzone-inner-compact`; комментарии и ошибка загрузки документов переведены на классы `hr-comments-box`, `hr-comment-item`, `hr-edit-doc-error`; суффикс email из `HrController::EMPLOYEE_EMAIL_SUFFIX`; в JS — `employeeEmailSuffix`, `showDocumentError`/`hideDocumentError`, безопасное добавление комментария (`textContent` для текста, `escapeHtml` для имён), кнопка удаления комментария для разработчика при AJAX-добавлении.
- [resources/views/hr/create.blade.php](resources/views/hr/create.blade.php): суффикс `@lc.ru` через константу контроллера, поле email с `form-input-email-prefix` и `email-suffix`; дропзона и блок ошибки документов без инлайн светлой темы; нижняя полоса кнопок — класс `hr-edit-actions`.
- [app/Http/Controllers/HrController.php](app/Http/Controllers/HrController.php): константа `EMPLOYEE_EMAIL_SUFFIX = '@lc.ru'`; в `update` нормализация email с этим суффиксом вместо `@level.ion`.
- [public/css/app.css](public/css/app.css): стили HR — `form-input-email-prefix`, `email-suffix`, `hr-edit-info-banner`, `hr-edit-fired-banner`, `hr-edit-doc-error`, `hr-comments-box`, комментарии (`hr-comment-*`), `hr-edit-actions`, `hr-dropzone-inner-compact`.
- [database/seeders/TestogradSeeder.php](database/seeders/TestogradSeeder.php): тестовые email заменены на домен `@lc.ru`.

## 08.04.2026 — HR /hr: строка таблицы открывает карточку сотрудника

- [resources/views/hr/index.blade.php](resources/views/hr/index.blade.php): удалена колонка с кнопкой перехода к карточке; строка `tbody` с `cursor-pointer` и `onclick` на `hr.edit`; пустое состояние — `colspan="8"`.

## 08.04.2026 — HR /hr: фильтры в строке заголовка таблицы (как заказы)

- [resources/views/hr/index.blade.php](resources/views/hr/index.blade.php): верхняя полоса только чекбоксы «Показать уволенных» / «Только чёрный список» и кнопки сброса + применения (`filtersFormSubmit`); фильтры перенесены во вторую строку `thead` (`border-b bg-muted`) — ID (`search_id`, `x-ui.filter-input`), город и роль (`multiselect-compact`), ФИО+паспорт (`search`), телефон (`search_phone`), комментарий (`search_note`); таблица `table table-sticky orders-sticky-table` и скрипты мультиселекта с фиксированным выпадающим списком и `repositionOpenOrdersTableMultiselect`, как на `orders.index`; `window.toggleMultiselect` / `window.updateMultiselect` для клона липкой шапки в `app.js`.
- [app/Http/Controllers/HrController.php](app/Http/Controllers/HrController.php): сбор фильтров из query — `search`, `search_phone`, `search_note`, `search_id`, `city_id` (массив из `city_id[]`), `role[]` в массив кодов ролей.
- [app/Services/HrService.php](app/Services/HrService.php): фильтр по `user_id` (`search_id`); роли — `whereIn` по нескольким `role[]`; поиск по ФИО/паспорту отдельно от телефона (`search_phone`, только цифры в LIKE); `search_note` по `user_note`.

## 08.04.2026 — HR /hr: тулбар и фильтры как на странице заказов

- [resources/views/hr/index.blade.php](resources/views/hr/index.blade.php): верхняя полоса `orders-index-toolbar` — крошки и `x-legend` в одной строке; кнопка «Создать сотрудника» справа через `x-ui.button` (как «Создать заказ» на заказах); фильтры в `x-ui.card` с полосой `orders-filters-sticky` — мультиселект города `multiselect-compact` (140px, подпись `text-xs text-muted-foreground`), поле поиска с подписью и `x-ui.filter-input` (`w-[11rem]`), роль — `x-ui.select` `sm` и `h-9 w-[11rem]`; чекбоксы в стиле темы; сброс — иконка `x-ui.button` `size="icon"` со ссылкой на `hr.index` (как сброс на заказах); таблица внутри той же карточки; скрипты мультиселекта и отправки формы (кнопка лупы в `filter-input`, blur/Enter для текстовых полей, change для чекбоксов) унифицированы с заказами; дата увольнения — класс `text-destructive` вместо инлайн-цвета; убрана нижняя отдельная `x-legend` и обёртка `max-width: 1600px`.

## 08.04.2026 — HR /hr и /hr/blacklist: хлебные крошки, без заголовка на ЧС

- [resources/views/hr/index.blade.php](resources/views/hr/index.blade.php): добавлен компонент `x-breadcrumbs` (Главная → Сотрудники).
- [resources/views/hr/blacklist.blade.php](resources/views/hr/blacklist.blade.php): добавлены `x-breadcrumbs` (Главная → Сотрудники → Чёрный список); удалён блок заголовка «Чёрный список сотрудников» (`page-header` / `h1`).
- [deploy/ftp/deploy_260408_1.sh](deploy/ftp/deploy_260408_1.sh): в список выгрузки добавлены `resources/views/hr/index.blade.php` и `resources/views/hr/blacklist.blade.php`; обновлена шапка комментария.

## 08.04.2026 — FTP: дополнен deploy_260408_1.sh (график мастеров)

- [deploy/ftp/deploy_260408_1.sh](deploy/ftp/deploy_260408_1.sh): в список выгрузки добавлен `resources/views/hr/roster.blade.php`; обновлён хеш Vite CSS на `app-DnhThRka.css` по актуальному `public/build/manifest.json`; шапка комментария — masters + roster.

## 08.04.2026 — HR /hr/roster: без выделения текущего дня

- [resources/views/hr/roster.blade.php](resources/views/hr/roster.blade.php): убран класс `today` у шапки дня, ячеек графика и строки «Итого».
- [resources/css/app.css](resources/css/app.css): удалены стили обводки `.today` для таблицы графика.

## 08.04.2026 — HR /hr/roster: открытие календаря у невидимого date

- [resources/views/hr/roster.blade.php](resources/views/hr/roster.blade.php): у поля недели задан `id="rosterWeekPicker"`; по `click` вызывается `showPicker()` (если доступен), чтобы нативный календарь показывался при невидимом `input`.
- [resources/css/app.css](resources/css/app.css): у `.hr-roster-week-input` заданы `appearance: auto` и полный размер области (`z-[5]` в разметке).

## 08.04.2026 — HR /hr/roster: диапазон дат как кликабельный текст

- [resources/views/hr/roster.blade.php](resources/views/hr/roster.blade.php): отдельное видимое поле `input type="date"` убрано; диапазон недели остаётся обычным текстом, поверх него — прозрачный `input` на всю капсулу (`absolute inset-0`, `opacity-0`), по клику открывается системный выбор даты; подсказка при наведении — пунктирное подчёркивание и рамка капсулы.
- [resources/css/app.css](resources/css/app.css): сброс отступов у `.hr-roster-week-input` внутри капсулы.

## 08.04.2026 — HR /hr/roster: тулбар относительно таблицы, неделя по центру, дата-пикер, цвета как у заказов

- [resources/views/hr/roster.blade.php](resources/views/hr/roster.blade.php): форма и карточка таблицы обёрнуты в `.hr-roster-table-block`; тулбар — сетка `1fr auto 1fr` (от `sm`): город слева, блок навигации по неделе по центру, кнопка редактирования справа; порядок: «Пред. неделя» → капсула с диапазоном дат и полем `input type="date" name="week"` (отправка формы, на сервере по-прежнему `startOfWeek()`) → «След. неделя» → «Текущая неделя»; ячейки графика получают классы `status-completed` / `status-cancelled_cc` (как у заказов); после AJAX обновления классы и HTML иконок синхронизируются через `rosterIconCheck` / `rosterIconClose`.
- [resources/css/app.css](resources/css/app.css): убраны локальные фоны/обводки для `.schedule-cell.working` / `.off`; иконки в ячейках наследуют `color` от ячейки со статусом.

## 08.04.2026 — HR /hr/roster: тулбар без лишних обёрток, капсулы только у ссылок недели

- [resources/views/hr/roster.blade.php](resources/views/hr/roster.blade.php): мультиселект города и кнопка редактирования без внешних капсул; диапазон дат — отдельный текст между блоками; ссылки «Пред./След./Текущая неделя» — каждая в своём виде капсулы через класс `hr-roster-week-nav`; легенда `x-legend` синхронизирована с усиленными цветами ячеек.
- [resources/css/app.css](resources/css/app.css): стили `.hr-roster-week-nav` для капсул навигации; фоны ячеек «работает»/«выходной» усилены (~52% / ~44% color-mix); тонкая внутренняя обводка для рабочих/выходных дней без класса `today`, чтобы не конфликтовать с обводкой «сегодня».

## 08.04.2026 — HR /hr/roster: капсулы тулбара, кнопка-иконка как на заказах, контраст ячеек

- [resources/views/hr/roster.blade.php](resources/views/hr/roster.blade.php): убрана общая карточка над графиком; фильтры в отдельных капсулах (`rounded-xl border border-border bg-card shadow-sm`) — город, неделя (`btn btn-sm btn-filter h-9`), редактирование; кнопка режима — `x-ui.button` с `size="icon"` (36×36, как на странице заказов), карандаш крупнее только внутри слота; цвета легенды `x-legend` приведены к усиленным фонам ячеек.
- [resources/css/app.css](resources/css/app.css): повышен контраст фонов `.schedule-cell.working` / `.off` (большая доля `color-mix` с `--success` и `--muted-foreground`); для `.hr-roster-edit-btn` задано увеличение только иконки до 1.2rem внутри кнопки.

## 08.04.2026 — HR /hr/roster: селектор города, кнопка редактирования, легенда

- [resources/views/hr/roster.blade.php](resources/views/hr/roster.blade.php): мультиселект города приведён к виду страницы рейтинга мастеров (`multiselect-compact`, ширина 140px, подпись `text-xs text-muted-foreground`, стрелка 0.7rem); легенда перенесена в строку с хлебными крошками через компонент `x-legend` (как на кассе/заказах), пункт «Есть примечание» убран; кнопка режима редактирования — только иконка карандаша, подсказки `title`/`aria-label` переключаются между «Редактировать график» и «Завершить редактирование».
- [resources/css/app.css](resources/css/app.css): удалены стили нижней полосы `.hr-roster-legend`; добавлены стили `.hr-roster-edit-btn` для иконки увеличенного размера.

## 08.04.2026 — HR /hr/roster: стили графика и хлебные крошки

- [resources/css/app.css](resources/css/app.css): добавлены стили страницы графика мастеров — обёртка `.hr-roster-page`, таблица `.schedule-table` (шапка дней, ячейки мастеров, рабочий/выходной, сегодня, итого), легенда `.hr-roster-legend`; для модальных окон с разметкой «backdrop + content» заданы `.modal:has(.modal-backdrop)` (прозрачный фон у контейнера), `.modal-backdrop` (полноэкранное затемнение), позиционирование `.modal-content` поверх подложки.
- [resources/views/hr/roster.blade.php](resources/views/hr/roster.blade.php): добавлены `x-breadcrumbs` (Главная → Сотрудники → График мастеров); контент обёрнут в `.hr-roster-page`; плейсхолдер мультиселекта и пустое состояние таблицы переведены на классы темы (`text-muted-foreground`, утилиты Tailwind); легенда использует класс `.hr-roster-legend`; у блока содержимого модалки примечания заданы `position`/`z-index` для корректного наложения; в скрипте мультиселекта для пустого выбора используется класс `text-muted-foreground` вместо инлайн-цвета.

## 08.04.2026 — HR /hr/masters: хлебные крошки

- [resources/views/hr/masters.blade.php](resources/views/hr/masters.blade.php): добавлены `x-breadcrumbs` — Главная → Сотрудники → Рейтинг мастеров (как на странице заказов).

## 08.04.2026 — FTP: скрипт выгрузки HR /hr/masters

- [deploy/ftp/deploy_260408_1.sh](deploy/ftp/deploy_260408_1.sh): поштучная выгрузка по FTP для изменений рейтинга мастеров (Blade, `resources/css/app.css`, `public/build/*`, `docs/CHANGELOG.md`); проверка даты в имени файла; блок команд на сервере `php83 artisan view:clear` и `cache:clear`.

## 08.04.2026 — HR /hr/masters: сброс, подписи, даты и периоды

- [resources/views/hr/masters.blade.php](resources/views/hr/masters.blade.php): кнопка сброса фильтров заменена на `x-ui.button` с `variant="primary"`, `size="icon"`, подсказкой «Сбросить фильтры» (как на странице заказов), без текстовой подписи; подписи чекбоксов «Показать имена мастеров» / «Показать сводки городов» заменены на «Скрыть имена» и «Города», для `show_names` задана логика через скрытое поле и чекбокс «Скрыть имена» (включено = `show_names=0`); блок быстрых периодов даты перенесён в одну строку справа от полей «с / по».

## 08.04.2026 — HR /hr/masters: раздельные карточки фильтров

- [resources/views/hr/masters.blade.php](resources/views/hr/masters.blade.php): форма оборачивает верхний блок фильтров (город — сброс) в отдельную карточку `masters-filters-main-card` и пять отдельных карточек `hr-masters-metric-group` для групп показателей таблицы; убран общий внешний контейнер-«обёртка» вокруг всего блока фильтров; у скрытых полей сортировки (`sort_by`, `sort_dir`) добавлен атрибут `form="filtersForm"`, чтобы они отправлялись вместе с формой фильтров; уменьшены вертикальные отступы между блоком фильтров и сеткой карточек показателей.
- [resources/css/app.css](resources/css/app.css): класс сетки переименован с `.hr-masters-settings-grid` на `.hr-masters-column-groups`, вложенность стилей привязана к `.hr-masters-metric-group`; для сетки показателей уменьшен `gap`, задано `align-items: stretch`, карточки групп с `height/min-height: 100%` и `box-sizing: border-box` для одинаковой высоты в ряду и равной ширины колонок (`minmax(0, 1fr)`).

## 08.04.2026 — HR: рейтинг мастеров (/hr/masters), фильтры и подписи колонок

- [resources/views/hr/masters.blade.php](resources/views/hr/masters.blade.php): мультиселект города приведён к `multiselect-compact` и ширине 140px (как у полей даты), подпись плейсхолдера — классы `text-xs text-muted-foreground`; кнопки периода «Сегодня / Вчера / Неделя / Месяц» — `btn btn-sm rounded-full`, активный период — `btn-primary`, иначе — `btn-secondary`; удалён лишний закрывающий тег `</button>`; блок выбора колонок таблицы — класс `hr-masters-settings-grid`; группа показателей бывших L/H: заголовок «Микра / чеки», пункты «Микра» и «Чеки»; в шапке таблицы колонки L/H заменены на «Микра» и «Чеки»; исправлена опечатка в атрибуте `class` у `setting-category-compact`.
- [resources/css/app.css](resources/css/app.css): стили сетки `.hr-masters-settings-grid` (адаптивные 1→2→3→5 колонок от `sm` до `xl`) и выравнивание дочерних чекбоксов через `flex-wrap` внутри категории.

## 08.04.2026 — отчёт ДДС по городу: тёмная тема

- [resources/views/cfm/city-report.blade.php](resources/views/cfm/city-report.blade.php): строки-заголовки видов деятельности переведены с инлайн-фона `#f9fafb` на класс `bg-muted` (токен `--muted`); итоговая строка — с инлайн `#111827` / белого текста на `bg-sidebar text-sidebar-foreground`; суммы — на `text-[color:var(--success)]` / `text-destructive` вместо фиксированных hex; разделитель дат в фильтре — `text-muted-foreground`; отступ категорий — `pl-8` вместо инлайн padding.

## 06.04.2026 — интеграция API с SuperPart (партнёрский портал)

### База данных
- [database/migrations/2026_04_06_120000_partner_superpart_integration.php](database/migrations/2026_04_06_120000_partner_superpart_integration.php): колонка `orders.partner_user_id` (ID пользователя SuperPart); таблицы `integration_api_logs`, `partner_order_idempotency` (ключ + `order_id` для идемпотентности `POST` создания заказа).

### Конфигурация
- [config/services.php](config/services.php): блок `superpart` — `SUPERPART_API_KEY`, `SUPERPART_API_SECRET`, `SUPERPART_BASE_URL`, `SUPERPART_ORDER_AUTHOR_USER_ID` (пользователь CRM для `order_created_by` у заказов из API), `SUPERPART_DEFAULT_SOURCE_ID` (активный источник для входящих партнёрских заказов).

### Входящий API (SuperPart → CRM)
- [routes/api.php](routes/api.php): префикс `api/v1` — `GET /ping`, `GET /reference/cities`, `GET /reference/work-types`, `POST /partner-orders` (заголовки `X-API-Key`, `X-Signature` = `hash_hmac('sha256', rawBody, secret)`, `Idempotency-Key`), `GET /partner-orders/{order_id}`; middleware `throttle:60,1` и [app/Http/Middleware/VerifySuperPartApi.php](app/Http/Middleware/VerifySuperPartApi.php) (в production без ключей в `.env` — 503).
- [app/Http/Controllers/Api/PartnerOrderController.php](app/Http/Controllers/Api/PartnerOrderController.php), [app/Services/PartnerOrderIngestService.php](app/Services/PartnerOrderIngestService.php): создание персоны/телефона/адреса/заказа; блокировка `GET_LOCK` по idempotency-key; идемпотентный повтор возвращает тот же заказ.
- [app/Http/Controllers/Api/PartnerReferenceController.php](app/Http/Controllers/Api/PartnerReferenceController.php), [app/Http/Controllers/Api/IntegrationPingController.php](app/Http/Controllers/Api/IntegrationPingController.php): справочники и ping.
- [app/Models/Order.php](app/Models/Order.php): в `fillable` добавлено `partner_user_id`.
- [app/Models/IntegrationApiLog.php](app/Models/IntegrationApiLog.php): лог неуспешной авторизации входящих запросов.

### Исходящие webhook’и (CRM → SuperPart)
- [app/Services/PartnerApiService.php](app/Services/PartnerApiService.php): `POST` на `SUPERPART_BASE_URL` — `/api/v1/webhooks/order-completed` (после проведения заказа со статусом `completed`, начисление `charge_amount` = 30% от `amount_paid - amount_comp`) и `/api/v1/webhooks/partner-order-created` (после создания заказа с заполненным `partner_user_id`).
- [app/Services/OrderService.php](app/Services/OrderService.php): после успешного `complete` и статуса `completed` вызывается уведомление SuperPart при наличии `partner_user_id`.
- [app/Http/Controllers/OrderController.php](app/Http/Controllers/OrderController.php): опциональное поле `partner_user_id` при создании заказа; после сохранения — `notifyPartnerOrderCreated`.
- [resources/views/orders/create.blade.php](resources/views/orders/create.blade.php): поле «ID партнёра (SuperPart)».
- [resources/views/orders/show.blade.php](resources/views/orders/show.blade.php): отображение `partner_user_id` в боковой карточке.

### Прочее
- [bootstrap/app.php](bootstrap/app.php): алиас middleware `superpart.api` → `VerifySuperPartApi`.

---

## 06.04.2026 — сидер тестового города Тестоград

- [database/seeders/PruneCitiesExceptTestogradSeeder.php](database/seeders/PruneCitiesExceptTestogradSeeder.php): удаление всех городов кроме «Тестоград» (очистка связанных строк: промо, маршруты/действия, районы, касса, жалобы, адреса, `user_cities` для чужих городов; у `routes` с удаляемыми районами сброс `district_id`); после очистки у пользователя с ролью `developer` города синхронизируются только с «Тестоград»; в конце [database/seeders/TestogradSeeder.php](database/seeders/TestogradSeeder.php) вызывается автоматически. Отдельный запуск: `php artisan db:seed --class=PruneCitiesExceptTestogradSeeder --force`.
- [database/seeders/LocalDevPurgeAndTestogradSeeder.php](database/seeders/LocalDevPurgeAndTestogradSeeder.php): сидер полной локальной очистки транзакционных данных (заказы, персоны, адреса, касса, промо, маршрутные действия, документы, звонки, комментарии, жалобы, отзывы, собеседования, график мастеров, статьи БЗ и т.д.), удаление всех пользователей кроме разработчика (роль `developer` или email `developer@leadcontrol.ru`), очистка `sessions` и `password_reset_tokens`, затем вызов `TestogradSeeder`. Запуск: `php artisan db:seed --class=LocalDevPurgeAndTestogradSeeder --force`. Справочники (роли, города, источники, банки, категории ДДС, маршруты без действий и пр.) не трогаются.
- [database/seeders/TestogradSeeder.php](database/seeders/TestogradSeeder.php): добавлен сидер для локальной/стендовой проверки — город «Тестоград» (`city_type` city, часовой пояс Europe/Moscow), девять сотрудников с указанными ФИО и email (три мастера `master`, два менеджера по заказам `order_manager`, региональный директор `regional_director`, руководитель филиала `branch_head`, технический директор `tech_director`, диспетчер КЦ `call_center`), привязка к городу через `user_cities`, роли через `user_roles`; единый тестовый пароль (константа `TEST_PASSWORD`, по умолчанию `password`, вывод в консоль при выполнении). Создаются 20 заказов с персонами и телефонами, адреса с улицей «ТЕСТОВАЯ» (верхний регистр), дома 1–20; разнообразие статусов (`pending`, `callback`, `in_progress`, `in_progress_sd`, `completed`, `cancelled_cc`, `cancelled_city`), типов техники из `Order::EQUIPMENT_TYPES`, назначение мастеров на активные/завершённые заказы, `order_created_by` — менеджеры, у закрытых — `order_closed_by` руководитель филиала. Запуск: `php artisan db:seed --class=TestogradSeeder` (предварительно должны быть засеяны роли и источники, например через основной `DatabaseSeeder`).

## 03.04.2026 — безопасность: заказы, API Mango, логин, документы, заголовки, аудит зависимостей

### Доступ к данным
- [app/Http/Controllers/OrderController.php](app/Http/Controllers/OrderController.php): добавлен приватный метод `authorizeOrderAccessForMutation` (та же логика города, что у `show` / `getData`: `developer` и `general_director` без ограничения, иначе `hasAccessToCity`); вызов в `update` и `complete` после `findOrFail` — устранение IDOR (изменение и проведение чужого заказа по угаданному `order_id`).

### API и Mango
- [bootstrap/app.php](bootstrap/app.php): в `withRouting` подключён [routes/api.php](routes/api.php) — маршрут `POST api/mango/webhook` (`mango.webhook`) регистрируется; после деплоя при использовании `route:cache` выполнить `php83 artisan route:clear` и при необходимости снова закэшировать маршруты.
- [app/Http/Controllers/Api/MangoWebhookController.php](app/Http/Controllers/Api/MangoWebhookController.php): в логах входа — только IP и флаги `has_json` / `has_sign`, без полного тела; при `APP_ENV=production` и пустом `MANGO_WEBHOOK_SECRET` подпись считается невалидной (вебхук не обрабатывается до настройки секрета); в логе ошибки обработки — только `error`.
- [app/Services/MangoCallService.php](app/Services/MangoCallService.php): убрано логирование полного payload и телефонов/URL записи; в логах — `event`, `mango_call_id`, внутренние `call_id` где уместно.
- [config/services.php](config/services.php): комментарий к `MANGO_WEBHOOK_SECRET` про поведение в production.

### Аутентификация и файлы
- [routes/web.php](routes/web.php): `POST /login` — middleware `throttle:login`.
- [app/Providers/AppServiceProvider.php](app/Providers/AppServiceProvider.php): лимитер `login` — 8 попыток в минуту на связку email (нижний регистр) + IP.
- [app/Http/Controllers/ReviewController.php](app/Http/Controllers/ReviewController.php): `showImage` — нормализация пути, запрет `..`, проверка `realpath` внутри каталога `storage/app/reviews`.
- [app/Http/Controllers/DocumentController.php](app/Http/Controllers/DocumentController.php): метод `safeOriginalFileName`; имя на диске и в БД без путей и нулевых байт, обрезка длины; снижена детализация логов (debug при `APP_DEBUG`); убраны блоки `debug` из JSON-ответов об ошибках; ответ 500 без утечки исключения при `APP_DEBUG=false`; предупреждение при неизвестном типе загрузки без полного URL.
- [app/Services/CfmOperationDocumentService.php](app/Services/CfmOperationDocumentService.php): то же безопасное имя файла, что и для документов заказов/HR.

### Заголовки и зависимости
- [app/Http/Middleware/SecurityHeaders.php](app/Http/Middleware/SecurityHeaders.php): глобально добавлены `X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy`, `Permissions-Policy`; при HTTPS — `Strict-Transport-Security` (дублирование/усиление к настройкам Apache/nginx на reg.ru допустимо).
- Выполнен `composer audit`: зафиксированы advisories для `league/commonmark`, `phpunit/phpunit` (dev), `psy/psysh` (dev), `symfony/process` — рекомендуется по мере обновления фреймворка/зависимостей поднять версии до закрывающих CVE.

### Обзор массового присвоения (HR)
- [app/Http/Controllers/HrController.php](app/Http/Controllers/HrController.php): метод `update` заполняет модель только из валидированного массива; роли и города — `sync` по отдельным полям; отдельные `update` для чёрного списка и увольнения — явные поля; критичных признаков присвоения из сырого `$request` без валидации не выявлено.

## 03.04.2026 — уведомления о новых заявках: город, звук, server_time

- [app/Http/Controllers/OrderController.php](app/Http/Controllers/OrderController.php): метод `newCount` — для городских ролей (маршрут `orders.new-count`) убран фильтр `whereIn('order_status', getVisibleStatuses())`. Раньше заказы в статусах `not_processed` и `callback` (типичный ввод от КЦ) не попадали в выборку, при пустом ответе клиент сдвигал `lastCheck` на `now()`, и после перевода заказа в `pending` условие `order_created_at > since` больше не выполнялось — уведомление не приходило. Для роли `call_center` (на случай расширения маршрута) фильтр по видимым статусам сохранён. Сравнение по времени и поле `server_time` переведены на формат `Y-m-d H:i:s.u`; в ранних ответах JSON добавлено `order_ids: []` где отсутствовало.
- [resources/views/layouts/app.blade.php](resources/views/layouts/app.blade.php): опрос новых заказов — `lastCheck` инициализируется с микросекундами (`Y-m-d H:i:s.u`); ключ `crm_notif_last_sound` в `localStorage` для общего cooldown звука между вкладками (снижение повторного звука на одну заявку при нескольких открытых вкладках); при недоступности `localStorage` — прежний fallback на `lastSoundTime` в памяти вкладки.
- [deploy-upload-2026-04-03-43.sh](deploy-upload-2026-04-03-43.sh): FTP-выгрузка перечисленных файлов; на сервере `php83 artisan view:clear`.
- **Диагностика, если уведомлений нет у конкретного города (Сыктывкар, Оренбург и т.д.):** проверить в MySQL привязку пользователя к городу (`user_city`) и что у заказа есть адрес с `city_id`. Пример запроса:

```sql
SELECT u.user_id, u.name, r.role_code, c.city_name
FROM users u
JOIN role_user ru ON ru.user_id = u.user_id
JOIN roles r ON r.role_id = ru.role_id
LEFT JOIN user_city uc ON uc.user_id = u.user_id
LEFT JOIN cities c ON c.city_id = uc.city_id
WHERE u.user_id = /* id пользователя */;
```

## 03.04.2026 — статусы заказа: карточка клиента совпадает со списком заказов и селектами

- Причина расхождения: в [resources/views/persons/show.blade.php](resources/views/persons/show.blade.php) для `pending` выводилось «Требуется обработка», тогда как в [app/Models/Order.php](app/Models/Order.php) `getStatusLabels()` и формы заказа — «Ожидает».
- [resources/views/persons/show.blade.php](resources/views/persons/show.blade.php): в таблице заказов на карточке персоны статус выводится через `{{ $order->status_label }}` (как на [resources/views/dashboard/index.blade.php](resources/views/dashboard/index.blade.php)), без дублирующего `@switch`.
- [app/Models/Order.php](app/Models/Order.php): аксессор `getStatusLabelAttribute` сначала берёт подпись из `getStatusLabels()`, для устаревших кодов БД (`unassigned`, `on_way`, `waiting_parts`, `waiting_payment`) — прежние тексты в `match`; устранено расхождение «Готов» / «Завершён» и «Отмена Город» / «Отмена город» между дашбордом и списком заказов.
- [resources/views/orders/show.blade.php](resources/views/orders/show.blade.php): комментарий у логики подсветки полей КЦ — статус «Ожидает» (`pending`).
- [deploy-upload-2026-04-03-42.sh](deploy-upload-2026-04-03-42.sh): FTP-выгрузка перечисленных файлов; на сервере `php83 artisan view:clear`.

## 03.04.2026 — HR: права на снятие с чёрного списка (автор записи, ген. дир, разработчик)

- [routes/web.php](routes/web.php): маршрут `hr.blacklist.remove` (DELETE) — middleware `role:branch_head,regional_director,general_director,developer` (как у `blacklist.add`); итоговая проверка в контроллере.
- [app/Http/Controllers/HrController.php](app/Http/Controllers/HrController.php): в `removeFromBlacklist` — снятие разрешено, если пользователь `developer` или `general_director` (любая запись в ЧС), либо `blacklisted_by` совпадает с текущим `user_id`; при отсутствии прав ответ JSON 403 с `message`; если сотрудник не в ЧС — JSON 422; убрано ограничение для ген. директора «только сотрудники КЦ» при снятии с ЧС.
- [resources/views/hr/blacklist.blade.php](resources/views/hr/blacklist.blade.php): кнопка «Удалить» — у ген. директора, разработчика или у автора записи (`blacklisted_by`); в запросе DELETE заголовок `Accept: application/json`; при ошибке показ `message` из ответа через `Toast`.
- [deploy-upload-2026-04-03-41.sh](deploy-upload-2026-04-03-41.sh): FTP-выгрузка перечисленных файлов; на сервере `php83 artisan view:clear`, `php83 artisan route:clear` (при `route:cache` — заново закэшировать маршруты).

## 03.04.2026 — HR: регионал создаёт мастера; из чёрного списка убирают только ген. директор и разработчик

- [app/Services/HrService.php](app/Services/HrService.php): в `getCreatableRolesForUser` для `regional_director` в список добавлен `master` (создание мастера в своих городах).
- [routes/web.php](routes/web.php): маршрут `hr.blacklist.remove` (DELETE) — middleware только `general_director`, `developer` (убран `regional_director`).
- [app/Http/Controllers/HrController.php](app/Http/Controllers/HrController.php): в `removeFromBlacklist` в начале проверка `hasAnyRole(['developer', 'general_director'])`, иначе `403`; правило ген. директора только по сотрудникам КЦ без изменений.
- [resources/views/hr/blacklist.blade.php](resources/views/hr/blacklist.blade.php): кнопка «Удалить» из ЧС — только у `general_director` и `developer` (раньше у регионала и разработчика, без ген. директора).
- [deploy-upload-2026-04-03-40.sh](deploy-upload-2026-04-03-40.sh): FTP-выгрузка перечисленных файлов; на сервере `php83 artisan view:clear`, `php83 artisan route:clear` (при `route:cache` — заново закэшировать маршруты).

## 03.04.2026 — региональный директор: смена пароля в настройках, создание руководителя филиала

- [resources/views/settings/index.blade.php](resources/views/settings/index.blade.php): роль `regional_director` убрана из списка ролей без формы смены пароля; обновлён комментарий в шаблоне.
- [app/Http/Controllers/SettingsController.php](app/Http/Controllers/SettingsController.php): в `updatePassword` для `regional_director` снят запрет (`abort(403)`); валидация текущего и нового пароля без изменений.
- [app/Services/HrService.php](app/Services/HrService.php): в `getCreatableRolesForUser` для `regional_director` в список создаваемых ролей добавлен `branch_head` (руководитель филиала); маршруты `hr.create` / `hr.store` и проверка `canCreateWithRole` используют этот список.
- [deploy-upload-2026-04-03-39.sh](deploy-upload-2026-04-03-39.sh): FTP-выгрузка перечисленных файлов; на сервере `php83 artisan view:clear`.

## 03.04.2026 — касса: `/cfm/summary` — фильтры как у заказов, клик по строке

- [resources/views/components/ui/filter-dates.blade.php](resources/views/components/ui/filter-dates.blade.php): опция `showClosedDates` (по умолчанию `true`); при `false` скрыты поля «Закрыто от/по» — для экранов без фильтра по дате закрытия заказа.
- [resources/views/cfm/summary.blade.php](resources/views/cfm/summary.blade.php): блок периода в `x-ui.card` и строке `orders-filters-sticky` — `x-ui.filter-dates` только «Дата от/по», справа иконки «Сбросить период» и «Показать» (`x-ui.button` `size="icon"`, как на списке заказов); быстрые периоды «С начала месяца», «Вчера», «Сегодня» — `x-ui.button` `primary` для совпадающего с URL диапазона, иначе `outline`; в форму передаются скрытые `sort`/`dir` при наличии; даты пресетов считаются от `now()->copy()` без мутации одной цепочки Carbon.
- Строки таблицы по городам: класс `order-row cursor-pointer` и `onclick` перехода на `cfm.summary.city` (как клик по строке заказа), название города — текст без вложенной ссылки.
- [deploy-upload-2026-04-03-38.sh](deploy-upload-2026-04-03-38.sh): FTP-выгрузка шаблонов и журнала; на сервере `php83 artisan view:clear`.

## 03.04.2026 — favicon: пользовательский круглый «LC», прозрачный фон

- Исходник: PNG пользователя со светлым шахматным фоном; обработка локально: обход от краёв кадра, пиксели с яркостью (Rec. 709) ≥ 88 помечаются как внешний фон и получают альфа 0, остальное без изменений.
- [public/favicon.png](public/favicon.png): 1024×1024, RGBA; [public/apple-touch-icon.png](public/apple-touch-icon.png): 180×180; [public/favicon.ico](public/favicon.ico): 16×16 и 32×32 в одном ICO.
- Удалён векторный [public/favicon.svg](public/favicon.svg); в шаблонах первая иконка вкладки — PNG.
- [resources/views/layouts/app.blade.php](resources/views/layouts/app.blade.php), [resources/views/layouts/master.blade.php](resources/views/layouts/master.blade.php), [resources/views/auth/login.blade.php](resources/views/auth/login.blade.php): `link rel="icon"` для `favicon.png` (`sizes="128x128"`), затем `favicon.ico`, без ссылки на SVG.
- [deploy-upload-2026-04-03-37.sh](deploy-upload-2026-04-03-37.sh): выгрузка иконок и Blade; на сервере при необходимости удалить устаревший `public/favicon.svg`.

## 03.04.2026 — FTP: имена скриптов с «будущими» датами приведены к 03.04.2026

- Удалены имена `deploy-upload-2026-04-04.sh`, `2026-04-05.sh`, `2026-04-05-2.sh`, `2026-04-06.sh`, `2026-04-07.sh`, `2026-04-23.sh`, `2026-04-23-2.sh`; содержимое перенесено в [deploy-upload-2026-04-03-30.sh](deploy-upload-2026-04-03-30.sh) … [deploy-upload-2026-04-03-36.sh](deploy-upload-2026-04-03-36.sh) (номера #30–#36 в шапках, примеры `bash ./deploy-upload-…` обновлены).
- Номер `-23` зарезервирован под выгрузку favicon — см. [deploy-upload-2026-04-03-23.sh](deploy-upload-2026-04-03-23.sh) (иконки и Blade из записи про favicon ниже).

## 03.04.2026 — favicon вкладки: «LC» на фоне тёмной темы

- [public/favicon.svg](public/favicon.svg) (позднее удалён): векторная иконка 64×64, буквы «LC» как контуры из шрифта Plus Jakarta Sans (ось вариативного начертания `wght` 600, соответствует semibold навбара), цвет текста `#f8fafc`, фон `#050406` (токен `--background` тёмной темы в [resources/css/app.css](resources/css/app.css)); межбуквенный интервал −0.02 em в координатах глифов.
- [public/favicon.ico](public/favicon.ico), [public/apple-touch-icon.png](public/apple-touch-icon.png): растровые производные той же композиции (16×16 и 32×32 в ICO, 180×180 PNG) для клиентов без SVG и для «Добавить на экран» в iOS.
- [resources/views/layouts/app.blade.php](resources/views/layouts/app.blade.php), [resources/views/layouts/master.blade.php](resources/views/layouts/master.blade.php), [resources/views/auth/login.blade.php](resources/views/auth/login.blade.php): в `<head>` добавлены `link rel="icon"` (SVG и ICO с `?v=filemtime`), `link rel="apple-touch-icon"`.
- [deploy-upload-2026-04-03-23.sh](deploy-upload-2026-04-03-23.sh): FTP-скрипт выгрузки иконок и затронутых Blade-шаблонов.

## 03.04.2026 — касса: сводный отчёт `/cfm/summary` (крошки, УК, тема, липкая шапка)

- [resources/views/cfm/summary.blade.php](resources/views/cfm/summary.blade.php): вверху панель `orders-index-toolbar` с `x-breadcrumbs` (Главная → Касса → Отчёт по кассе); справа кнопка `x-ui.button` «УК» с иконкой банка, суммой баланса и `title="Баланс Управляющей Компании"` — только для ролей `developer` и `general_director` при наличии `$mcCity` и `$mcBalance !== null`; убрана карточка `.summary-card` и ссылка «К списку»; корневой контейнер без `max-width: 1200px`, классы `min-w-0 max-w-full`; заголовок страницы приведён к «Отчёт по кассе»; строка «ИТОГО», ячейки городов и пустое состояние на токенах темы (`bg-muted`, `text-[color:var(--success)]`, `text-destructive`, `text-muted-foreground`, ссылки `text-primary`); таблица в обёртке `cfm-summary-table-scroll`.
- [app/Http/Controllers/CfmController.php](app/Http/Controllers/CfmController.php): в `cityReport` для города с `city_type === 'mc'` доступ только у `developer` и `general_director`, иначе `403`; для остальных городов прежняя проверка `developer` / `hasAccessToCity`.
- [resources/css/app.css](resources/css/app.css): блок `.cfm-summary-table-scroll` — `max-height`, `overflow: auto`; `thead th` — `position: sticky`, фон `var(--muted)`, нижняя граница `var(--border)`.
- [public/css/app.css](public/css/app.css): удалены правила `.cfm-summary-back` в медиазапросе (кнопка «К списку» снята).
- Сборка фронта: `npm run build` (обновлён бандл Vite).
- [deploy-upload-2026-04-03-36.sh](deploy-upload-2026-04-03-36.sh): FTP-скрипт выгрузки изменений по `/cfm/summary` (контроллер, шаблон, `resources/css/app.css`, `public/css/app.css`, `public/build/*`, журнал).

## 03.04.2026 — касса: страница создания операции (макет как у карточки, документы до сохранения)

- [resources/views/cfm/create.blade.php](resources/views/cfm/create.blade.php): убран бейдж «Не проведено»; разметка как у [cfm/show](resources/views/cfm/show.blade.php) — крошки «Главная → Касса → Новая операция», `order-show-page` и `cfm-show-layout`; слева карточка «Реквизиты операции» с формой (`id="cfmForm"`, `enctype="multipart/form-data"`), справа сайдбар с кнопками «Сохранить» / «Назад» (`form="cfmForm"`, `order-show-buttons`) и блоком документов (`.dropzone`, `.order-documents-heading`, `.cfm-sidebar-documents-only`, подсказка `.order-docs-hint`); обязательные поля помечены `span.text-destructive` вместо инлайн-красного; скрытый `input` с `form="cfmForm"` `name="documents[]" multiple`; JS — буфер `File[]`, `DataTransfer` для синхронизации с input после сброса `value`, drag-and-drop, список вложений с удалением до отправки.
- [app/Http/Controllers/CfmController.php](app/Http/Controllers/CfmController.php): в `store` валидация `documents` / `documents.*` (до 10 МБ, те же MIME, что у КФМ в документах), нормализация массива файлов, вызов `CfmService::create(..., $documents)`, перехват `ValidationException` с `back()->withErrors`.
- [app/Services/CfmService.php](app/Services/CfmService.php): метод `create` принимает третий аргумент `array $documents = []`; после создания основной операции (и парных при перемещении/инкассе) вызывается [CfmOperationDocumentService](app/Services/CfmOperationDocumentService.php) для каждого валидного `UploadedFile` — вложения только к основной операции.
- [app/Services/CfmOperationDocumentService.php](app/Services/CfmOperationDocumentService.php): новый сервис — проверка MIME как раньше в контроллере, `storeAs` в `documents/cfm/{cfm_id}`, создание `Document` с `document_category` `general`, при ошибке записи — удаление файла с диска.
- [app/Http/Controllers/DocumentController.php](app/Http/Controllers/DocumentController.php): ветка загрузки для КФМ после базовой валидации файла делегирует сохранение в `CfmOperationDocumentService::attachToOperation`, ответ JSON при ошибке валидации MIME через `ValidationException`.

## 03.04.2026 — касса: карточка операции — блок «Создано» в левой карточке

- [resources/views/cfm/show.blade.php](resources/views/cfm/show.blade.php): под реквизитами снова выводится «Создано» — дата и время в часовом поясе города операции и имя автора.
- [app/Http/Controllers/CfmController.php](app/Http/Controllers/CfmController.php): во `show` снова передаётся `$cfmCreatedAtForCity`.
- [deploy-upload-2026-04-03-22.sh](deploy-upload-2026-04-03-22.sh): выгрузка.

## 03.04.2026 — касса: карточка операции — слева только реквизиты, кнопки над документами

- [resources/views/cfm/show.blade.php](resources/views/cfm/show.blade.php): из левой карточки убраны бейдж статуса, пояснение про часовой пояс города и блоки «Создано» / «Проведено»; кнопки (Переоткрыть, Назад, Сохранить, Провести и закрыть) и скрытые формы перенесены в правую колонку **над** заголовком «Документы» (разделитель `border-t` у заголовка документов).
- [app/Http/Controllers/CfmController.php](app/Http/Controllers/CfmController.php): во `show` во view передаются только `cfmClosedAtForCity` (для крошек проведённой операции), убраны неиспользуемые `cfmCreatedAtForCity` и `cfmDisplayTimezone` из `compact`.
- [deploy-upload-2026-04-03-21.sh](deploy-upload-2026-04-03-21.sh): выгрузка.

## 03.04.2026 — касса: карточка операции — слева реквизиты и действия, справа только документы; крошки

- [resources/views/cfm/show.blade.php](resources/views/cfm/show.blade.php): в начале страницы задаётся `$isOrderIncome` и подпись крошек `$cfmBreadcrumbLabel` — для проведённой операции последний пункт: «Операция №N (Проведено дд.мм.гггг чч:мм / ФИО закрывшего)» с `$cfmClosedAtForCity` и `closedBy`; для непроведённой — «Операция №N». Бейдж статуса, пояснение про часовой пояс, блоки «Создано»/«Проведено», кнопки и скрытые формы `reopen`/`close` перенесены в левую карточку под разделителем `border-t`; правая колонка содержит только заголовок «Документы», зону загрузки, список файлов и подсказки.
- [deploy-upload-2026-04-03-20.sh](deploy-upload-2026-04-03-20.sh): выгрузка.

## 03.04.2026 — касса: карточка операции — сайдбар ~600px, документы справа, превью файлов столбиком

- [public/css/app.css](public/css/app.css): сетка `.cfm-show-layout` (`1fr` + `minmax(280px, 600px)`), липкий `.cfm-show-sidebar`, стили `.cfm-files-list`, `.cfm-file-item` (вертикальная раскладка: миниатюра сверху, под ней имя/размер/удаление), `.cfm-file-thumb` / `.cfm-file-thumb--placeholder` для не-картинок; при ширине экрана до 1200px — одна колонка.
- [resources/views/cfm/show.blade.php](resources/views/cfm/show.blade.php): документы перенесены в правый блок между датами и кнопками; текст «Операция проведена…» под списком файлов; убран блок документов из левой карточки; `addFileToList` собирает ту же разметку с `cfmEscapeHtml` для имён.
- [deploy-upload-2026-04-03-19.sh](deploy-upload-2026-04-03-19.sh): выгрузка.

## 03.04.2026 — касса: карточка операции — документы слева, время по городу

- [app/Http/Controllers/CfmController.php](app/Http/Controllers/CfmController.php): в `show` вычисляются `$cfmDisplayTimezone` из `city_timezone` города операции (fallback `config('app.timezone')`) и моменты `$cfmCreatedAtForCity` / `$cfmClosedAtForCity` через `Carbon::timezone()` для отображения «Создано» / «Проведено» в локальном времени города.
- [resources/views/cfm/show.blade.php](resources/views/cfm/show.blade.php): из правого блока убран заголовок «Операция №…»; под бейджем статуса — пояснение про часовой пояс города; блок документов перенесён в левую карточку под реквизиты (разделитель `border-t`), полноширинная секция `order-show-documents` снизу удалена.
- [deploy-upload-2026-04-03-18.sh](deploy-upload-2026-04-03-18.sh): выгрузка.

## 03.04.2026 — касса: карточка операции (как заказ — сетка, крошки, документы снизу)

- [resources/views/cfm/show.blade.php](resources/views/cfm/show.blade.php): убрана узкая колонка `max-width: 800px`; сверху хлебные крошки «Главная → Касса → Операция №…» (`x-breadcrumbs`, ссылки на `orders.index` и `cfm.index`); основная разметка — `order-show-page` и двухколоночная `order-show-layout` / `order-show-sidebar` как на `orders/show`; слева карточка «Реквизиты операции», справа — заголовок «Операция №…» с иконкой `cfm`, бейдж проведения, блоки «Создано» / «Проведено» и кнопки (`order-show-buttons`, `form`/`form="cfmForm"`); убран прежний заголовок «Кассовая операция: N»; секция документов вынесена под сетку в `order-show-documents` с панелью `order-doc-panel`, зона загрузки через `dropzone-inner` в режиме редактирования; модальные окна и ссылки на заказ приведены к токенам темы где уместно; в JS `addFileToList` без кнопки удаления для роли `general_director`.
- [deploy-upload-2026-04-03-17.sh](deploy-upload-2026-04-03-17.sh): выгрузка.

## 03.04.2026 — касса: карточка операции, тёмная тема (подсказка по документам)

- [resources/views/cfm/show.blade.php](resources/views/cfm/show.blade.php): блок «Операция проведена. Добавление документов недоступно.» переведён с инлайн `background`/`color` (#f9fafb, #6b7280) на классы `bg-muted`, `text-muted-foreground`, `border-border` и т.д. — читаемость в `.dark`; подписи имён у «Создано» / «Проведено» в режиме проведённой операции — `text-muted-foreground` вместо инлайн-серого.
- [deploy-upload-2026-04-03-16.sh](deploy-upload-2026-04-03-16.sh): выгрузка.

## 03.04.2026 — касса: липкая шапка таблицы и тулбар кнопок

- [resources/views/cfm/index.blade.php](resources/views/cfm/index.blade.php): таблица переведена на классы `table-sticky orders-sticky-table` без обёртки `overflow-x-auto` — горизонтальный скролл и клон шапки (две строки заголовков и фильтров) через `initOrdersStickyTable` в [resources/js/app.js](resources/js/app.js), как на списке заказов; скрипты мультиселектов дополнены `positionOrdersMultiselectDropdownFixed`, `closeAllMultiselectDropdowns`, `repositionOpenOrdersTableMultiselect`, `window.updateMultiselect` для синхронизации с клоном; кнопки Инкас/Расход/Приход/Перемещение/Отчёт вынесены в правую часть полосы `orders-index-toolbar` напротив крошек и легенды.

## 03.04.2026 — касса (cfm.index): как список заявок — крошки, фильтры, пагинация, строки

- [resources/views/components/ui/filter-dates.blade.php](resources/views/components/ui/filter-dates.blade.php): компонент блока дат «Дата от/по», «Закрыто от/по» перенесён из `components/orders/`; подключение как `x-ui.filter-dates`.
- Удалён [resources/views/components/orders/filter-dates.blade.php](resources/views/components/orders/filter-dates.blade.php); [resources/views/orders/index.blade.php](resources/views/orders/index.blade.php) переведён на `x-ui.filter-dates`.
- [app/Http/Controllers/CfmController.php](app/Http/Controllers/CfmController.php): для списка операций при пустых `date_from`/`date_to` подставляется текущий месяц; фильтрация по `cfm_created_at` в этом диапазоне всегда; добавлены `closed_from`/`closed_to` по `cfm_closed_at`; исправлен фильтр `cfm_cat_group` — массив и `whereIn` по категории; во view передаются `$dateFrom`, `$dateTo`.
- [resources/views/cfm/index.blade.php](resources/views/cfm/index.blade.php): хлебные крошки (Главная → Касса) и легенда в верхней панели; быстрые действия через `x-ui.button` (primary); одна форма в `x-ui.card` с липкой полосой `.orders-filters-sticky`, `x-ui.filter-dates`, чекбоксы без `name` для скрытых параметров, справа иконки сброса и поиска как у заявок; вторая строка `thead` с `x-ui.filter-input` (ID) и `multiselect-compact` (тип, статья, город); скрипты — `updateMultiselect`/`selectAll`/`deselectAll` по образцу заказов, авто-submit для именованных чекбоксов и полей, мерж формы в URL при клике по сортировке в `th`; убрана колонка со стрелкой, переход на карточку операции по клику на строку; подсветка непроведённых — класс `table-row-warning`; футер пагинации при `total() > 0` и шаблон `vendor.pagination.leadcontrol`.
- [resources/views/components/legend.blade.php](resources/views/components/legend.blade.php): ветка `warning_swatch` — квадрат-образец через класс `.legend-warning-swatch` без инлайн-hex.
- [public/css/app.css](public/css/app.css): стили `.table-row-warning` / `.dark` для строк таблицы кассы; `.legend-warning-swatch` для легенды.

## 03.04.2026 — заказы: как superpart — одна таблица и клон шапки

- [resources/views/orders/index.blade.php](resources/views/orders/index.blade.php): устранена ошибочная разметка с двумя таблицами (`</thead></table>` + второй `<table>` для `tbody`); список заказов снова в **одной** таблице `table table-sticky orders-sticky-table` с `thead` (две строки) и `tbody`, по образцу проекта superpart.ru: одна таблица `w-full`, горизонтальный скролл и клон `thead` в `document.body` (`initOrdersStickyTable`).
- [public/css/app.css](public/css/app.css): удалены правила split-таблицы (`.orders-table-split-root`, `.orders-thead-sticky-host`, `.orders-head-hscroll`, `.orders-body-hscroll`, `.orders-hide-scrollbar`, `.orders-table-head` / `.orders-table-body`); добавлены стили `.orders-table-scroll`, `.orders-sticky-table` (фон строк шапки, ellipsis для колонок 1 и 9) и дублирование блока `.table-sticky-clone` с `top: var(--orders-clone-top, 7.5rem)`.
- [resources/js/app.js](resources/js/app.js): `syncOrdersFiltersBarHeightCss` выставляет `--orders-clone-top` для позиции клона под навбаром и липкой полосой фильтров; в `bindCloneMultiselects` добавлены `syncMultiselectLabel` и обновление подписи после change / select all в клоне.
- [resources/css/app.css](resources/css/app.css): у `.table-sticky-clone` вместо фиксированного `top: 60px` — `top: var(--orders-clone-top, 7.5rem)`.
- [deploy-upload-2026-04-03-14.sh](deploy-upload-2026-04-03-14.sh): выгрузка.

## 03.04.2026 — заказы: таблица не уже контейнера, колонки синхронно растягиваются

- [public/css/app.css](public/css/app.css): для `.orders-table-head` и `.orders-table-body` заданы `width: max(100%, max-content)`, `min-width: 100%`, `table-layout: fixed`, `box-sizing: border-box` вместо чистого `max-content`, чтобы таблица занимала как минимум ширину горизонтального скролла; дублирующее `table-layout: fixed` у `.orders-table-body` убрано (общее правило на обе таблицы).
- [resources/views/orders/index.blade.php](resources/views/orders/index.blade.php): в `syncOrdersHeadBodyColumns` после расчёта базовых ширин вычисляется `targetW = max(сумма колонок, clientWidth шапки/тела)`; недостающие пиксели до `targetW` добавляются к колонкам 3, 4, 6, 7, 9, 10 (время, тип, статус, город, адрес, мастер), при отсутствии индексов — поровну ко всем; коррекция округления на колонку «адрес»; на обе таблицы выставляется `style.width = targetW + 'px'`; в `clearWidths` сбрасывается ширина таблиц.
- [deploy-upload-2026-04-03-13.sh](deploy-upload-2026-04-03-13.sh): выгрузка.

## 03.04.2026 — заказы: мультиселект fixed, капы ширины ID/имя, table-layout fixed

- [resources/views/orders/index.blade.php](resources/views/orders/index.blade.php): выпадающие списки мультиселектов в `.orders-table-head` позиционируются через `position: fixed` (`positionOrdersMultiselectDropdownFixed`), с учётом края экрана и при нехватке места снизу — открытие вверх; `z-index: 6000`; при закрытии сбрасываются инлайн-стили (`clearOrdersMultiselectDropdownLayout`); глобальный `scroll` (capture) и `resize` вызывают `repositionOpenOrdersTableMultiselect`; после синхронизации колонок — повторное позиционирование открытого списка. В `syncOrdersHeadBodyColumns` добавлены верхние пределы ширины столбцов: индекс 0 (ID) — 48px, индекс 8 (имя) — 160px. Уменьшены `min-w` у ячеек фильтров (тип, статус, город, имя, адрес, мастер); у фильтра ID — `w-full max-w-[2.75rem] min-w-0`.
- [public/css/app.css](public/css/app.css): у `.orders-table-body` задано `table-layout: fixed`; для 1-й и 9-й колонок в шапке (первая строка `th`) и в теле (`td`) — `overflow: hidden`, `text-overflow: ellipsis`, у `td` — `white-space: nowrap`.
- Выполнена production-сборка Vite: `public/build/assets/app-CK7imqn8.css`.
- [deploy-upload-2026-04-03-12.sh](deploy-upload-2026-04-03-12.sh): выгрузка.

## 03.04.2026 — заказы: ширины колонок шапка/тело, компактнее ID и имя, видимый мультиселект

- [resources/views/orders/index.blade.php](resources/views/orders/index.blade.php): у фильтра по ID класс `min-w-[4.5rem]` заменён на `min-w-[2.5rem]`; у ячейки фильтра «Имя клиента» — `min-w-[8rem]` на `min-w-[6.5rem]`. В `syncOrdersHeadBodyColumns` те же вычисленные ширины применяются к ячейкам первой строки `tbody` (`width`/`minWidth`/`maxWidth`); в `clearWidths` добавлена очистка инлайн-ширин у этих `td`.
- [public/css/app.css](public/css/app.css): для `.orders-head-hscroll` задано `overflow-y: clip` (отдельным блоком перед общим правилом с `.orders-body-hscroll`), чтобы горизонтальный скролл шапки не создавал вертикальный scrollport и не обрезал выпадающие списки мультиселектов; у `.multiselect-compact .multiselect-dropdown` `z-index` поднят с 160 до 500.
- Выполнена production-сборка Vite: `public/build/manifest.json`, `public/build/assets/app-Ca-Hsmxr.css`, `public/build/assets/app-C13uuHfC.js`.
- [deploy-upload-2026-04-03-11.sh](deploy-upload-2026-04-03-11.sh): выгрузка.

## 03.04.2026 — заказы: горизонтальный скролл таблицы, без налезания заголовков

- [public/css/app.css](public/css/app.css): для `.table.orders-table-head` и `.table.orders-table-body` переопределена ширина с глобального `width: 100%` на `width`/`min-width: max-content`, чтобы колонки не сжимались по вьюпорту и скролл шёл внутри `.orders-head-hscroll` / `.orders-body-hscroll`; добавлены `.orders-table-split-root`, `min-width: 0` на цепочке скролла и липкого хоста, `white-space: nowrap` на первой строке заголовков, `touch-action: pan-x pan-y` для предсказуемого жеста на тач-экранах.
- [resources/views/orders/index.blade.php](resources/views/orders/index.blade.php): обёртка `.orders-table-split-root`; у формы фильтров и тулбара страницы — `min-w-0 max-w-full`; с таблиц убраны `w-full min-w-max` (роль ширины на себя берёт CSS). Скрипт `syncOrdersHeadBodyColumns`: после сброса ширин измерение в `requestAnimationFrame`, ширина столбца `max(тело, заголовок, строка фильтров, 36px)`, восстановление `scrollLeft` шапки/тела после применения.
- [public/css/app.css](public/css/app.css): у `.main-content` добавлено `min-width: 0`, чтобы контент не раздувал страницу по горизонтали во вложенных flex/grid.
- [deploy-upload-2026-04-03-10.sh](deploy-upload-2026-04-03-10.sh): выгрузка.

## 03.04.2026 — заказы: липкая шапка вне горизонтального скролла тела

- [resources/views/orders/index.blade.php](resources/views/orders/index.blade.php): `.orders-thead-sticky-host` вынесен из общего контейнера с `overflow-x: auto` — липкий блок больше не является потомком горизонтального scrollport тела таблицы (из‑за этого в ряде браузеров ломалось или «плавало» вертикальное sticky). Внутри хоста — `.orders-head-hscroll` (скролл шапки, скроллбар скрыт классом `.orders-hide-scrollbar`); тело — отдельный `.orders-body-hscroll`. Добавлена функция `initOrdersHorizontalScrollSync`: синхронизация `scrollLeft` между шапкой и телом без циклов (флаг `lock`). `ResizeObserver` для пересчёта колонок вешается на оба горизонтальных контейнера вместо устаревшего `.orders-table-x-scroll`.
- [public/css/app.css](public/css/app.css): класс `.orders-table-x-scroll` для списка заказов заменён парой `.orders-head-hscroll` / `.orders-body-hscroll` с общими правилами `overflow-x: auto`; у `.orders-thead-sticky-host` заданы `width/max-width: 100%`; добавлены стили `.orders-hide-scrollbar`.
- [deploy-upload-2026-04-03-9.sh](deploy-upload-2026-04-03-9.sh): выгрузка.

## 03.04.2026 — заказы: две таблицы + липкая обёртка шапки

- [resources/views/orders/index.blade.php](resources/views/orders/index.blade.php): шапка (две строки thead) вынесена в отдельную таблицу `.orders-table-head` внутри `.orders-thead-sticky-host`; строки данных — `.orders-table-body`; общий горизонтальный скролл `.orders-table-x-scroll`. Скрипт `syncOrdersHeadBodyColumns` выравнивает ширины колонок по первой строке tbody; вызов при load/resize/ResizeObserver (полоса фильтров и блок скролла).
- [public/css/app.css](public/css/app.css): `position: sticky` перенесён на `.orders-thead-sticky-host` (не на `thead` внутри overflow-x); стили фона шапки — `.orders-table-head thead ...`; у `.orders-table-body` отрицательный `margin-top` против двойной границы.
- [deploy-upload-2026-04-03-8.sh](deploy-upload-2026-04-03-8.sh): выгрузка.

## 03.04.2026 — заказы: таблица в границах карточки, горизонтальный скролл

- [resources/views/orders/index.blade.php](resources/views/orders/index.blade.php): обёртка `.orders-table-x-scroll` вокруг таблицы; у карточки списка заказов классы `min-w-0 max-w-full`.
- [public/css/app.css](public/css/app.css): `.orders-table-x-scroll` — `overflow-x: auto`, `max-width: 100%`; при `@supports (overflow: clip)` задаётся `overflow-y: clip`, чтобы не создавать вертикальный scrollport и не ломать sticky `thead`.
- [deploy-upload-2026-04-03-7.sh](deploy-upload-2026-04-03-7.sh): выгрузка.

## 03.04.2026 — заказ: хлебные крошки и подсветка без «Переносы»

- [resources/views/orders/show.blade.php](resources/views/orders/show.blade.php): над карточкой заказа добавлены `<x-breadcrumbs>` — Главная → Заказы → текущий заказ (как на списке заказов); из списка полей для жёлтой подсветки при статусе «Требуется обработка» убрано `shift_adds` (переносы необязательны); текст плашки предупреждения уточнён.

## 03.04.2026 — карточка заказа: разметка, тосты, тёмная тема

- [resources/views/orders/show.blade.php](resources/views/orders/show.blade.php): убрана внешняя обёртка `.card` вокруг верхней сетки; контейнер `.order-show-page`; секция документов — `<section class="order-show-documents">` с заголовком и верхней границей вместо вложенной карточки; блоки категорий документов — `.order-doc-panel` вместо `.card.document-block`; плашки КЦ и ошибок проведения — `.order-alert-warning`; сайдбар, расчёты, подсветка источника, закрытие заказа, dropzone и подсказка форматов переведены на классы из CSS; пустой список файлов — `.files-empty--hidden` до загрузки списка; исправлено получение `filesList` до `fileItem.remove()` при удалении документа.
- [public/css/app.css](public/css/app.css): классы `.order-show-page`, `.order-show-documents`, `.order-doc-panel`, `.dropzone-inner`, `.order-doc-disabled-placeholder`, `.order-docs-hint`, `.order-doc-note-warn`, `.order-alert-warning`, `.order-order-closed-box`, `.order-show-subtotal`, `.order-calc-*`, `.order-sidebar-*`, `.source-row-pending`, `.btn-order-compact`, `.files-empty--hidden`; для `.field-highlight` и `.btn-complaint-warn` — правила `.dark`; тосты: контейнер `column-reverse` и `align-items: flex-end`, `pointer-events` на контейнере/тостах; у `.toast` фон и рамка через переменные, отдельные правила `.dark .toast-*` для читаемости.

## 03.04.2026 — заказы: sticky thead без обёртки overflow-x

- [resources/views/orders/index.blade.php](resources/views/orders/index.blade.php): убрана обёртка `overflow-x-auto` вокруг таблицы — из‑за неё `position: sticky` у `thead` привязывался к внутреннему контексту, шапка визуально «съезжала» и не следовала прокрутке окна; таблица с `min-w-max` для горизонтального скролла на уровне страницы при узком окне.
- [public/css/app.css](public/css/app.css): дефолт `--orders-sticky-filters-height` снижен до `4.75rem`, чтобы до отработки JS не было лишнего зазора под полосой фильтров.
- Скрипт: `syncOrdersStickyFiltersHeight` через `offsetHeight`, повтор по событию `load`.
- [deploy-upload-2026-04-03-6.sh](deploy-upload-2026-04-03-6.sh): выгрузка.

## 03.04.2026 — заказы: липкие фильтры и шапка таблицы при прокрутке

- [public/css/app.css](public/css/app.css): класс `.orders-filters-sticky` — полоса дат/суммы/кнопок с `position: sticky` под навбаром; `.orders-table thead` — липкий блок из двух строк (заголовки колонок и фильтры), `top: calc(var(--navbar-height) + var(--orders-sticky-filters-height))`; переменная `--orders-sticky-filters-height` по умолчанию `6.5rem` в `:root`.
- [resources/views/orders/index.blade.php](resources/views/orders/index.blade.php): полоса фильтров с классом `orders-filters-sticky`; скрипт `syncOrdersStickyFiltersHeight` + `ResizeObserver` выставляет `--orders-sticky-filters-height` по фактической высоте полосы.
- [deploy-upload-2026-04-03-5.sh](deploy-upload-2026-04-03-5.sh): выгрузка.

## 03.04.2026 — заказы: шапка таблицы без position:sticky

- Удалены правила `.table-sticky` из [public/css/app.css](public/css/app.css); для таблицы заказов добавлен класс `orders-table` и явно задано `position: static !important` для `thead` / ячеек шапки, чтобы заголовок не закреплялся при прокрутке (в т.ч. при кэше старого HTML с `table-sticky`).
- [resources/views/orders/index.blade.php](resources/views/orders/index.blade.php): на `<table>` добавлен класс `orders-table`.
- [deploy-upload-2026-04-03-4.sh](deploy-upload-2026-04-03-4.sh): выгрузка.

## 03.04.2026 — заказы: без липкого thead на списке заказов

- [resources/views/orders/index.blade.php](resources/views/orders/index.blade.php): с таблицы убран класс `table-sticky`, заголовок и строка фильтров по колонкам прокручиваются вместе со страницей (не закреплены под навбаром).
- [deploy-upload-2026-04-03-3.sh](deploy-upload-2026-04-03-3.sh): выгрузка этого изменения.

## 03.04.2026 — заказы: фильтры, легенда, липкий thead, сброс дат без 500

### Доработка (липкий thead, закрытые/активные)
- [public/css/app.css](public/css/app.css): (исторически) для `.table-sticky` липким был сделан целиком `thead`; на списке заказов класс больше не используется — см. запись выше.
- [resources/views/orders/index.blade.php](resources/views/orders/index.blade.php): ссылки «Закрытые» / «Активные» — относительные URL `?` + `http_build_query` (как у сортировки), сохраняются остальные GET-параметры; иконки у кнопок убраны; полоса дат/суммы — `py-2`.

### Список заказов и фильтры
- [resources/views/orders/index.blade.php](resources/views/orders/index.blade.php): одна полоса над таблицей — даты, сумма от/до в стиле полей дат (`x-ui.input`, `w-[11rem]`), кнопки «Закрытые/Активные», «Сбросить», «Применить» в одной строке справа; все в `variant="primary"`, одна submit с лупой (`id="filtersFormSubmit"`), дублирующая круглая submit убрана; фон полосы и строки фильтров в `thead` — непрозрачный `bg-muted`; убрана вторая горизонтальная полоса и разделитель между бывшими строками.
- [resources/views/components/orders/filter-dates.blade.php](resources/views/components/orders/filter-dates.blade.php): убраны декоративные SVG календаря (остаётся нативная иконка `type="date"`); отступ справа `pr-8` только при видимой кнопке очистки.
- [resources/views/components/legend.blade.php](resources/views/components/legend.blade.php): панель легенды якорится `left-0` (не уезжает влево за экран); на кнопке символ «i» вместо иконки info.

### Бэкенд и скрипты
- [app/Http/Controllers/OrderController.php](app/Http/Controllers/OrderController.php): пустые или пробельные `date_from` / `date_to` в запросе подменяются на начало и конец текущего месяца (устранение 500 при очистке поля даты через GET).
- [resources/js/app.js](resources/js/app.js): по клику на крестик даты подставляются значения по умолчанию (`date_from` — 1-е число месяца, `date_to` — последний день месяца, `closed_*` — пусто), отправка формы через `requestSubmit` с кнопкой `#filtersFormSubmit`.

### Стили
- [public/css/app.css](public/css/app.css): см. подзаголовок «Доработка» выше (липкий `thead`); ранее в этой сессии — липкость только у первой строки `th`.

### Сборка и выгрузка
- Выполнена production-сборка Vite (`npm run build`), артефакты: `app-PlO6L2dJ.css`, `app-C13uuHfC.js`.
- Добавлен [deploy-upload-2026-04-03-2.sh](deploy-upload-2026-04-03-2.sh) для FTP-выгрузки затронутых файлов.

## 07.04.2026 — заказы: шапка (крошки+легенда), фильтр дат как SuperPart, sticky thead, пагинация, toast

### Список заказов
- [resources/views/orders/index.blade.php](resources/views/orders/index.blade.php): слева в одной группе крошки и легенда, справа только «Создать заказ»; первая полоса фильтров — компонент дат + переключатель закрытых/активных + круглая submit-кнопка «применить»; вторая полоса — сумма от/до и компактные кнопки «Найти»/«Сбросить» (`size="icon"`); таблица с классом `table-sticky`; пагинация через [resources/views/vendor/pagination/leadcontrol.blade.php](resources/views/vendor/pagination/leadcontrol.blade.php) с видимыми prev/next и «1» при одной странице.
- [resources/views/components/orders/filter-dates.blade.php](resources/views/components/orders/filter-dates.blade.php): блок «Дата от/по», «Закрыто от/по» в стиле SuperPart (`x-ui.input`, иконка календаря, кнопки сброса `date-clear-btn`).

### Стили и кнопки
- [resources/views/components/ui/button.blade.php](resources/views/components/ui/button.blade.php): класс `ui-button` для ссылок-кнопок.
- [public/css/app.css](public/css/app.css): правила `a.ui-button` против глобального `a { color: primary }` для primary/secondary/outline; тосты — контейнер внизу справа, анимация появления `slideUp`; `.multiselect-dropdown` шире (`min-width` / `max-width`); `.table-sticky thead` с `top: var(--navbar-height)` и фон `th` через `--card`.
- [resources/css/layout.css](resources/css/layout.css): переменная `--navbar-height: 60px` для sticky-заголовка таблицы.

### Скрипты
- [resources/js/app.js](resources/js/app.js): `initDateFilterClears()` — очистка даты и `requestSubmit` формы.

### Сборка
- Выполнена production-сборка Vite, артефакты: `app-BFMaF5hc.css`, `app-CUmp91D3.js`.

## 06.04.2026 — навбар: единый ghost-стиль контролов, профиль как в superpart (dropdown)

### Шапка и UX
- [resources/views/layouts/app.blade.php](resources/views/layouts/app.blade.php): в `.navbar-right` для авторизованных пользователей оставлены переключатель темы и блок профиля в едином визуальном стиле; отдельные кнопки «Настройки» и «Выход» в полоске убраны — пункты «Настройки» и «Выход» перенесены во всплывающую панель по клику на имя и стрелку (без вывода `user_id`); гостевая ссылка «Войти» оформлена классом `navbar-toolbar-btn`; переключатель темы использует класс `js-theme-toggle` вместо `#theme-toggle`; при закрытии мобильного меню и при сворачивании гамбургером закрываются открытые панели профиля; клик по ссылке «Настройки» из панели профиля закрывает выезжающее меню на узком экране.
- [resources/views/partials/navbar-profile.blade.php](resources/views/partials/navbar-profile.blade.php): общий фрагмент выпадающего профиля с вариантом `variant` (`desktop` | `mobile`).
- [resources/views/layouts/master.blade.php](resources/views/layouts/master.blade.php): кнопка темы переведена на `navbar-toolbar-btn js-theme-toggle` (без `id` и без `btn-settings`).
- [resources/css/layout.css](resources/css/layout.css): добавлены `.navbar-toolbar-btn`, `.navbar-profile-dropdown`, `.navbar-profile-panel`, `.navbar-profile-panel-link`, модификаторы мобильного профиля, кнопка «Тема» внизу мобильного меню `.navbar-menu-theme`; у `.navbar-mobile-user` задано `position: relative` для позиционирования панели.
- [resources/js/app.js](resources/js/app.js): `initProfileDropdowns()` — открытие/закрытие панели профиля по клику, закрытие при клике вне блока; `initTheme()` — поддержка нескольких кнопок `.js-theme-toggle` с обновлением иконок в каждой.
- Выполнена production-сборка Vite (`npm run build`), обновлены артефакты в [public/build/](public/build/).

## 05.04.2026 — страница «Заказы»: крошки, фильтры в таблице, легенда-поповер, отступы, пагинация, toast

### Макет и навигация
- [resources/views/layouts/app.blade.php](resources/views/layouts/app.blade.php): опциональная секция `@section('breadcrumbs')` перед контентом; добавлена глобальная функция `showCopyFeedback()` после определения `copyTextToClipboard`.
- [resources/views/components/breadcrumbs.blade.php](resources/views/components/breadcrumbs.blade.php): компонент `x-breadcrumbs` (разделитель «/», ссылки и текущий пункт).
- [public/css/app.css](public/css/app.css), [resources/css/layout.css](resources/css/layout.css): у `.main-content` убран `max-width: 1400px`, горизонтальные поля сужены (`1rem`), контент на полную ширину в пределах вьюпорта.

### Список заказов
- [resources/views/orders/index.blade.php](resources/views/orders/index.blade.php): одна строка шапки — слева `x-breadcrumbs` («Главная / Заказы»), справа `x-legend` и кнопка «Создать заказ» (`px-4 py-1.5`); форма фильтров объединена с таблицей в одной карточке; верхняя полоса — диапазоны дат заявки и закрытия, сумма, переключатель закрытых/активных, «Найти», «Сбросить»; вторая строка `thead` — `x-ui.filter-input` (ID, имя, адрес) и компактные мультиселекты (тип, статус, город, мастер); пагинация и текст «Показано … из …» при `total() > 0`, при одной странице — пометка «страница 1 из 1»; копирование строки через `showCopyFeedback` и `closest('td')`.
- [resources/views/components/ui/filter-input.blade.php](resources/views/components/ui/filter-input.blade.php): новый примитив для фильтров в таблице.
- [public/css/app.css](public/css/app.css): модификатор `.multiselect-compact` для строки фильтров; `z-index` у `.toast-container` повышен до `99999`.

### Легенда
- [resources/views/components/legend.blade.php](resources/views/components/legend.blade.php): вместо раскрывающегося блока в потоке — кнопка `x-ui.button` outline `size="icon"` и абсолютно позиционированная карточка с `x-ui.card shadow="lg"`; статусы через `status-badge`; скрипт открытия/закрытия (клик вне, Escape); `@once` + `@push('scripts')`.

### Прочее
- [resources/views/components/ui/card.blade.php](resources/views/components/ui/card.blade.php): проп `shadow` поддерживает строки `sm` и `lg`.
- [app/Http/Controllers/OrderController.php](app/Http/Controllers/OrderController.php): в белый список сортировки добавлено поле `order_closed_at` (ссылка в шапке таблицы уже была).
- [resources/views/orders/show.blade.php](resources/views/orders/show.blade.php): копирование в буфер — через `showCopyFeedback`.
- [docs/DESIGN_SYSTEM.md](docs/DESIGN_SYSTEM.md): описаны `x-ui.filter-input`, `x-breadcrumbs`, `x-legend`, уточнён `x-ui.card shadow`.
- [resources/views/ui-kit/index.blade.php](resources/views/ui-kit/index.blade.php): демо крошек, легенды, компактной primary-кнопки, `filter-input`.

## 04.04.2026 — hotfix: прод без стилей на логине и 419 за прокси

- На странице входа и лейаутах [resources/views/auth/login.blade.php](resources/views/auth/login.blade.php), [resources/views/layouts/master.blade.php](resources/views/layouts/master.blade.php), [resources/views/layouts/error.blade.php](resources/views/layouts/error.blade.php) добавлено подключение legacy [public/css/app.css](public/css/app.css) перед `@vite` (как в [resources/views/layouts/app.blade.php](resources/views/layouts/app.blade.php)), чтобы при несовпадении `manifest.json` и файлов в `public/build/assets/` интерфейс не оставался «голым» HTML.
- В [bootstrap/app.php](bootstrap/app.php) включён `trustProxies(at: '*')` для корректного определения HTTPS за reverse proxy (сессия и CSRF).
- В [deploy-upload-2026-04-03.sh](deploy-upload-2026-04-03.sh) исправлено имя CSS-артефакта Vite на актуальное из manifest; добавлен [deploy-upload-2026-04-03-30.sh](deploy-upload-2026-04-03-30.sh) для точечной выгрузки hotfix.

## 03.04.2026 — миграция на дизайн-систему: лейаут, заказы, глобальные стили, UI Kit на проде

### Лейаут и токены темы
- [resources/css/layout.css](resources/css/layout.css): навбар переведён на переменные `--sidebar*` и семантические токены из [resources/css/app.css](resources/css/app.css); пагинация и `.card` в shell — на `--foreground`, `--card`, `--muted`, `--primary` и др.; алерты `.alert-*` — на `--success` / `--warning` / `--danger`.
- В [resources/css/app.css](resources/css/app.css): в светлой теме `--sidebar` задан как тёмная полоса навигации (#1f2937), как у legacy-навбара; добавлены `--success`, `--warning`, `--danger` для согласования с `layout.css` и legacy; перенесён `@keyframes pulse` (бейдж новых заявок в шапке).
- [resources/views/layouts/app.blade.php](resources/views/layouts/app.blade.php): удалён дублирующий инлайн-блок стилей (остаются только подключения CSS/Vite); классы `bg-sidebar text-sidebar-foreground` на `<nav>`, `bg-background text-foreground` на `<main>`; флеш-сообщения через `<x-ui.alert>`.
- [resources/views/layouts/master.blade.php](resources/views/layouts/master.blade.php), [resources/views/layouts/error.blade.php](resources/views/layouts/error.blade.php): убраны инлайн-`<style>`, разметка на Tailwind + shell-классы; выход в master — `<x-ui.button>`.
- [resources/views/errors/404.blade.php](resources/views/errors/404.blade.php), [resources/views/errors/403.blade.php](resources/views/errors/403.blade.php): контент в `<x-ui.card>` и утилитах темы.

### Компоненты UI
- [resources/views/components/ui/button.blade.php](resources/views/components/ui/button.blade.php): при непустом `href` рендерится `<a>` (ссылка-кнопка без ручного `tag="a"`).

### Страница заказов
- [resources/views/orders/index.blade.php](resources/views/orders/index.blade.php): контейнеры и фильтры на Tailwind; кнопки — `<x-ui.button>`; карточки фильтров/таблицы — `<x-ui.card>`; удалён `@push('styles')`; JS мультиселекта переключает класс `text-muted-foreground` вместо инлайн-цвета.
- [resources/views/orders/create.blade.php](resources/views/orders/create.blade.php): форма на `x-ui.form-group`, `x-ui.input`, `x-ui.select`, `x-ui.textarea`, `x-ui.button`; удалён `@push('styles')`.
- [resources/views/orders/show.blade.php](resources/views/orders/show.blade.php): удалён большой `@push('styles')`; сетки и вспомогательные блоки вынесены в [public/css/app.css](public/css/app.css) (`.order-show-layout`, `.order-show-grid-row-*`, `.field-highlight`, `.dropzone`, `.file-*`, `.btn-complaint-warn`, `.btn-success` и медиазапросы); подсказка КЦ — `<x-ui.alert type="warning">`.

### Legacy `public/css/app.css`
- Добавлены общие блоки: мультиселект (заказы, HR, касса, промо), подсветка строки `.order-row` в списке заказов, `.status-cell` и анимации `.blink-*`, полный набор стилей карточки заказа, дашборд (`.stats-grid`, `.stat-card`, `.status-badge`, `.stats-period-grid`, `.stat-row`), вкладки промо-журнала (`.tabs-header`, `.nav-tabs`), легенда страницы (`.page-legend`, `@keyframes legend-blink`).

### Авторизация
- [resources/views/auth/login.blade.php](resources/views/auth/login.blade.php): убран инлайн-`<style>`, страница на Tailwind (градиент, адаптив), форма на `x-ui.card`, `x-ui.form-group`, `x-ui.input`, `x-ui.button`, `x-ui.alert`; переключение видимости пароля через `json_encode(icon(...))` в скрипте.

### Остальные представления
- Автоматически удалены блоки `@push('styles')` / `<style>` / `@endpush` во всех перечисленных шаблонах модулей CFM, HR, жалобы, отчёты, база знаний, настройки, промоутеры, дашборд, персоны — стили перенесены в глобальный legacy-CSS или считаются покрытыми существующими классами `.btn`, `.table`, `.form-*`, `.multiselect` и добавленными блоками выше. При необходимости точечные страницы можно доработать до полного использования `x-ui.*` по детальному плану миграции темы/дизайн-системы (этапы 1–14).

### Легенда и телефон
- [resources/views/components/legend.blade.php](resources/views/components/legend.blade.php): инлайн-стили перенесены в `public/css/app.css`; заголовок с `cursor-pointer`.
- [resources/views/components/input-phone-ru.blade.php](resources/views/components/input-phone-ru.blade.php): подпись через `<x-ui.label>`.

### Маршрут UI Kit
- [routes/web.php](routes/web.php): маршрут `GET /ui-kit` доступен не только локально: `middleware('role:developer')`, имя `ui-kit` сохранено.

### Сборка
- Выполнена production-сборка Vite (`npm run build`), обновлены артефакты в `public/build/`.

### Деплой
- Добавлен [deploy-upload-2026-04-03.sh](deploy-upload-2026-04-03.sh): FTP-скрипт со списком файлов миграции темы (маршруты, Vite-артефакты, CSS, Blade по модулям, docs); в шапке — команда запуска с `FTP_PASS`, блок команд на сервере (`php83 artisan view:clear`, `config:clear`, `cache:clear`).

**После деплоя:** выкладка изменённых PHP/Blade, `public/css/app.css`, `public/build/*`; на сервере при необходимости `php83 artisan view:clear`.

### Parity tweakcn Deep Purple (документация и UI Kit)
- [docs/DESIGN_SYSTEM.md](docs/DESIGN_SYSTEM.md): переработан §1 — полные таблицы семантических цветов (включая `*-foreground`, popover, chart, sidebar), legacy `--success` / `--warning` / `--danger`, нумерация подпунктов (типографика §1.5, радиусы/тени §1.6, темы §1.7); §6 — таблица секций витрины `/ui-kit`; добавлен §8 «Соответствие tweakcn и отклонения» с таблицей расхождений (`--sidebar*` в светлой теме против экспорта tweakcn, legacy-цвета, `font-sans antialiased` на `body`, тень dark); ссылка на тему [tweakcn Deep Purple](https://tweakcn.com/themes/cmlh0x713000104jrgmds6vcd).
- [resources/views/ui-kit/index.blade.php](resources/views/ui-kit/index.blade.php): расширена палитра (все пары фон/текст, `popover`, `chart-1`…`chart-5`, блок `sidebar*` с пояснением про навбар, демо legacy через `var(--success)` и др.); новые секции радиусы (`rounded-sm`…`rounded-xl`), тени (`shadow-2xs`…`shadow-2xl`), межбуквенный интервал (`tracking-*`); секция «Паттерны» — карточки метрик, список с аватарами-инициалами, radio-группа тарифов, progress, разделитель, статичная оболочка модалки на `x-ui.card`; вступление уточнено (доступ по роли developer, не только локально).
- Выполнена production-сборка Vite (`npm run build`), обновлены артефакты в `public/build/`.

## 02.04.2026 — дизайн-система Deep Purple, тема, UI Kit, ребрендинг шапки

### CSS и сборка
- Переписан [resources/css/app.css](resources/css/app.css): `@import "tailwindcss"`, `@custom-variant dark`, токены `:root` / `.dark` (Deep Purple), `@theme inline`, `@layer base`, вспомогательные стили (скроллбар, date input, sticky-таблица).
- В [resources/views/layouts/app.blade.php](resources/views/layouts/app.blade.php), [master.blade.php](resources/views/layouts/master.blade.php), [error.blade.php](resources/views/layouts/error.blade.php), [login.blade.php](resources/views/auth/login.blade.php) подключены Bunny Fonts и `@vite(['resources/css/app.css', 'resources/js/app.js'])` после/рядом с legacy `public/css/app.css` где применимо.

### Legacy-переменные
- Синхронизированы акцентные цвета в [public/css/app.css](public/css/app.css) и инлайн `:root` в `layouts/app.blade.php` с дизайн-системой (`--primary` #6b26d9 и др.).

### Навбар и бренд
- В основном layout: ссылка-бренд с `LC` / `Lead Control` при открытом мобильном меню (класс `menu-open` на `#mainNavbar`, правки `toggleMobileMenu` / `closeMobileMenu`).
- В [master.blade.php](resources/views/layouts/master.blade.php) и [error.blade.php](resources/views/layouts/error.blade.php) отображается «LC»; на странице входа остаётся полное «Lead Control».

### Тема light/dark
- Миграция [database/migrations/2026_04_02_120000_add_theme_to_users_table.php](database/migrations/2026_04_02_120000_add_theme_to_users_table.php): колонка `users.theme` (string, default `light`).
- [app/Models/User.php](app/Models/User.php): поле `theme` в `$fillable`.
- [app/Http/Controllers/SettingsController.php](app/Http/Controllers/SettingsController.php): метод `updateTheme()`, валидация `dark|light`.
- Маршрут `PATCH settings.theme` в [routes/web.php](routes/web.php).
- [resources/js/app.js](resources/js/app.js): `initTheme()`, `localStorage`, `PATCH /settings/theme`, `window.leadControlSetTheme`, событие `leadcontrol:set-theme`.
- [resources/js/bootstrap.js](resources/js/bootstrap.js): заголовки `Accept: application/json`, `X-CSRF-TOKEN` из meta.
- Класс `dark` на `<html>` в `layouts/app` и `layouts/error` по профилю; кнопка `#theme-toggle` в шапке.
- [resources/views/settings/index.blade.php](resources/views/settings/index.blade.php): блок «Тема оформления».

### Blade-компоненты
- Добавлены примитивы в [resources/views/components/ui/](resources/views/components/ui/): `button`, `input`, `select`, `textarea`, `label`, `form-group`, `card`, `alert`, `checkbox`.

### UI Kit и документация
- Локальный маршрут `GET /ui-kit` (`App::isLocal()`): [resources/views/ui-kit/index.blade.php](resources/views/ui-kit/index.blade.php) — палитра, типографика, кнопки, формы, алерты, карточки, бейджи, таблица, пагинация, демо-график и календарь, переключатель темы.
- [docs/DESIGN_SYSTEM.md](docs/DESIGN_SYSTEM.md), правило [.cursor/rules/levelion-design-system.mdc](.cursor/rules/levelion-design-system.mdc).

### Прочее
- Обновлены общие правила [.cursor/rules/levelion-general.mdc](.cursor/rules/levelion-general.mdc) (описание шрифтов и ссылка на дизайн-систему).

**После деплоя на сервер:** `php83 artisan migrate --force`, `npm run build` (или выкладка `public/build`), при необходимости очистка кэша представлений.
