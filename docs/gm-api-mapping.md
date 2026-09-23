# GM API Mapping

## Идентификация пользователя

- Bearer auth: заголовок `Authorization: Bearer <token>`.
- Идентичность мастера передаётся через query-параметры или заголовки:
  - `userId` или `X-GM-User-Id`
  - `login` или `X-GM-Login`
  - `email` или `X-GM-Email`
- Приоритет поиска:
  1. `userId` -> `users.user_id`
  2. `login` -> локальная часть `users.email` до `@`
  3. `email` -> `users.email`

## External user -> GM master profile

- `userId` -> `users.user_id`
- `login` -> локальная часть `users.email`
- `email` -> `users.email`
- `displayName` -> `users.user_name`
- `fullName` -> `users.user_name`
- `inn` -> `users.user_inn` (нормализация при чтении: только 10/12 цифр, иначе `null`)
- запись: `PATCH /api/v1/gm/users/me/inn` с телом `{ "inn": "…" }` — строго 10 или 12 цифр без пробелов/букв; пишет в `users.user_inn`
- `birthDate` -> `users.user_birth_date`
- `guildJoinedAt` -> `users.user_hired_at`
- `role` -> первый `roles.role_code`
- `rating` -> `min(100, число completed-заказов мастера)` (clamp 0–100 для UI GM)
- `status` -> `active | inactive | blacklisted` по `is_active`, `user_fired_at`, `is_blacklisted`
- `isVerifiedByPassport` -> наличие `documents.is_verified = true` у сотрудника, иначе fallback на непустой `user_passport`
- `isBranchDirector` -> наличие роли `branch_head`
- `branchId` -> первый `cities.city_id`, связанный через `user_cities`
- `branchCity` -> первый `cities.city_name`
- `photoUrl` -> `null` (в текущей схеме нет аватара)
- `svyazGuild*` -> `null` (в текущей схеме нет полей учёток)

## External order -> GM order

- `id` -> `orders.order_id`
- `orderNumber` -> строковое представление `orders.order_id`
- `status` -> `orders.order_status`
- `description` -> `orders.order_adds`
- `address` -> `addresses.full_address`
- `clientName` -> первая связанная `persons.person_name`
- `clientPhone` -> первый `person_phones.phone_number`, нормализованный в `+7XXXXXXXXXX`
- `equipmentType` -> `orders.equipment_type`
- `previousContactsSummary` -> `orders.shift_adds`
- `clientContactType` -> `phone`, если найден телефон, иначе `null`
- `techDirectorComment` -> `orders.city_adds`
- `amountRub` -> `orders.amount_paid - orders.amount_comp`
- `billingCategory` -> `orders.order_type`
- `distanceKm` -> `null` (в схеме нет расстояния)
- `createdAt` -> `orders.order_created_at`
- `updatedAt` -> fallback `order_closed_at ?? order_created_at`
- `closedAt` -> `orders.order_closed_at`
- `contactUri` -> `tel:+7...`
- `documents` -> полиморфные `documents` заказа
- `financialSnapshot.amountPaidRub` -> `orders.amount_paid`
- `financialSnapshot.amountCompRub` -> `orders.amount_comp`
- `financialSnapshot.netAmountRub` -> `amount_paid - amount_comp`
- `financialSnapshot.masterSalaryRub` -> расчёт по текущей логике CRM для мастеров
- `timeline` -> `order_created_at`, `datetime_order`, `order_closed_at`, `order_activity_logs`
- `assignedMaster` -> `users` по `orders.master_id`
- `branch` -> `addresses.city_id / cities.city_name`
- `canCallClient`, `canMessageClient` -> `true`, если найден телефон клиента

## External review -> GM review

- Текущая схема `reviews` хранит ограниченный набор полей: `id`, `link`, `partner_id`, `order_number`, `created_by`, timestamps.
- Поэтому маппинг сейчас такой:
  - `id` -> `reviews.id`
  - `author` -> `reviews.partner_id`, fallback `users.user_name` по `created_by`
  - `text` -> `null`
  - `score` -> `null`
  - `emoji` -> `null`
  - `date` -> `reviews.created_at`
  - `source` -> `reviews.link`
  - `sentiment` -> `null`
  - `correctedAt` -> `null`
  - `correctedBy` -> `null`

## Ограничения текущей версии

- Это read-only фасад поверх текущей CRM-схемы.
- `rating-history`, `negative-open`, `guild-applications` пока возвращают пустые коллекции, потому что в текущей БД нет полноценного источника этих данных.
- Часть полей профиля и отзывов возвращается `null`, чтобы не подменять отсутствующие данные выдуманными значениями.
