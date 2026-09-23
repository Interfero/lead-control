# Атрибуция РК — дыры (prod snapshot)

**Дата:** 2026-07-20 (обновлено вечером — решение вариант 2)  
**Команда:** `php artisan sources:attribution-gaps`  
**ADR:** `docs/ADR-superpart-party-no-did-phone.md`

## Сводка (после решения)

| Метрика | Значение |
|---------|----------|
| **Блокеры DID** (без телефона, не SuperPart-парт) | **0** → шаг 4 по телефонам закрыт |
| SuperPart-парты без телефона | 15 (не блокер) |
| Неактивных с телефоном | 4 |
| Дублей `source_phone` | 0 |

## SuperPart-парты без телефона (информативно)

Заказ идёт с `source_id` из SuperPart; DID не используется. Список: CSV `docs/attribution-gaps-20260720.csv` (исторический полный снимок до разделения).

## Нормализация (§10.2 шаг 3)

2026-07-20: `sources:normalize-phones` — 3 записи; `persons:normalize-phones` — без правок.
