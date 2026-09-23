<?php

namespace App\Support;

/**
 * Происхождение заявки во внешнем API (не путать с marketing Source / source_id).
 *
 * LC — заказ из Lead Control (эта БД).
 * KP-LEAD — заказ из kp-lead-centre (CRM2); в ответах API LC не встречается,
 * код зарезервирован для единого контракта с Desk / агрегаторами.
 */
final class OrderLeadSource
{
    public const LC = 'LC';

    public const KP_LEAD = 'KP-LEAD';

    /** @return list<string> */
    public static function all(): array
    {
        return [self::LC, self::KP_LEAD];
    }

    public static function isValid(string $value): bool
    {
        return in_array($value, self::all(), true);
    }
}
