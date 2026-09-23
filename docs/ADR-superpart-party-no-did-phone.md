# Решение: телефоны DID не обязательны для SuperPart-партов

**Дата:** 2026-07-20  
**Вариант:** 2 (из обсуждения ТЗ §10.2 шаг 4)

## Решение

Для закрытия **§10.2 шаг 4** («РК на все боевые линии») телефон обязателен только у линий, где идёт **DID → Source** (листовки / Mango / ручные РК с номером).

**Парт-источники каталога SuperPart** (`source_kind=party` и `available_for_superpart` / `superpart_local_source_id`) **не требуют** `source_phone`: заказ приходит в CRM уже с `source_id`.

## Код

- `Source::isExemptFromDidPhoneRequirement()` / scopes `requiresDidPhone` / `exemptFromDidPhone`
- `SourceAttributionService::gapStats()` — `without_phone` = только блокеры DID
- UI «Без телефона» — только блокеры
- `php artisan sources:attribution-gaps` — раздельный отчёт

## Следствие для приёмки

§12.A п.2 «100% линий» трактуем как **100% боевых DID-линий** с телефоном; каналы SuperPart без DID не блокируют шаг 4.
