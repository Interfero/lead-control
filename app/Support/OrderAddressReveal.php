<?php

namespace App\Support;

use App\Models\Address;
use Carbon\CarbonInterface;

/**
 * Показ квартиры/офиса в списках CRM: за N минут до времени заявки (как в Едином окне).
 */
class OrderAddressReveal
{
    public const REVEAL_MINUTES_BEFORE = 30;

    public static function canReveal(?CarbonInterface $callAt, ?CarbonInterface $now = null, ?string $timezone = null): bool
    {
        if ($callAt === null) {
            return false;
        }

        $now ??= $timezone ? now($timezone) : now();
        $call = $timezone ? $callAt->copy()->shiftTimezone($timezone) : $callAt;

        return $now->greaterThanOrEqualTo($call->copy()->subMinutes(self::REVEAL_MINUTES_BEFORE));
    }

    public static function revealAt(?CarbonInterface $callAt, ?string $timezone = null): ?CarbonInterface
    {
        if ($callAt === null) {
            return null;
        }

        $call = $timezone ? $callAt->copy()->shiftTimezone($timezone) : $callAt;

        return $call->copy()->subMinutes(self::REVEAL_MINUTES_BEFORE);
    }

    /**
     * Строка «кв / офис» для desk-синка и API.
     */
    public static function officeTextFromAddress(?Address $address): ?string
    {
        if ($address === null) {
            return null;
        }

        $parts = [];
        if ($address->flat) {
            $parts[] = 'кв / офис: '.$address->flat;
        }
        $adds = trim((string) ($address->address_adds ?? ''));
        if ($adds !== '') {
            $parts[] = $adds;
        }

        return $parts !== [] ? implode(', ', $parts) : null;
    }

    /**
     * Улица и дом без квартиры (для списков до раскрытия).
     */
    public static function streetLine(?Address $address, ?string $cityName = null): string
    {
        $parts = [];
        if ($cityName) {
            $parts[] = 'г. '.$cityName;
        }
        if ($address) {
            $streetParts = [];
            if ($address->street) {
                $streetParts[] = $address->street;
            }
            if ($address->house) {
                $streetParts[] = 'д. '.$address->house;
            }
            if ($streetParts !== []) {
                $parts[] = implode(', ', $streetParts);
            }
        }

        return implode(', ', $parts);
    }

    /**
     * Адрес для колонки списка заказов CRM.
     */
    public static function formatListAddress(?Address $address, ?CarbonInterface $datetimeOrder, ?string $cityTimezone = null): string
    {
        if ($address === null) {
            return '—';
        }

        $cityName = $address->relationLoaded('city') ? $address->city?->city_name : null;
        $base = self::streetLine($address, null);
        if ($base === '') {
            return '—';
        }

        if (! self::canReveal($datetimeOrder, null, $cityTimezone)) {
            return $base;
        }

        $office = self::officeTextFromAddress($address);
        if ($office) {
            return $base.', '.$office;
        }

        return $base;
    }
}
