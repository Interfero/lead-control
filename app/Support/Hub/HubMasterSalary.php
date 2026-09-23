<?php

namespace App\Support\Hub;

/**
 * Расчётка мастера для заказов внешних источников хаба.
 * Лестница процентов — та же, что в OrderService::getMasterPercent (CRM1),
 * чтобы GM видел одинаковые суммы по LC- и KP-заявкам.
 */
final class HubMasterSalary
{
    public static function percent(
        int $netAmount,
        ?string $orderType,
        ?string $orderCore,
        bool $isLongTrip = false,
        bool $isSatellite = false,
        bool $isPartnerOrder = false,
    ): int {
        if ($orderType === 'warranty' && $netAmount <= 7500) {
            return 50;
        }

        if ($isLongTrip || $isSatellite) {
            return 50;
        }

        if ($orderCore === 'other') {
            return $isPartnerOrder ? 40 : 50;
        }

        if ($orderCore === 'non_core' && $netAmount <= 7500) {
            return 40;
        }

        return match (true) {
            $netAmount <= 2500 => 25,
            $netAmount <= 4500 => 30,
            $netAmount <= 7500 => 35,
            $netAmount <= 10500 => 40,
            $netAmount <= 17000 => 45,
            default => 50,
        };
    }

    public static function salary(
        int $netAmount,
        ?string $orderType,
        ?string $orderCore,
        bool $isLongTrip = false,
        bool $isSatellite = false,
        bool $isPartnerOrder = false,
    ): int {
        $percent = self::percent($netAmount, $orderType, $orderCore, $isLongTrip, $isSatellite, $isPartnerOrder);

        return (int) floor(max(0, $netAmount) * $percent / 100);
    }
}
