<?php

namespace App\Support;

use App\Models\Order;

/**
 * Текст для мастера в рабочий чат: номер заявки + адрес с квартирой.
 *
 * Пример:
 * Заказ 907
 * г. Новокузнецк, Ермакова, д. 9а
 * кв / офис: 268 офис
 */
final class MasterFlatCopyFormatter
{
    public static function format(
        string $orderNumber,
        string $streetAddress,
        string $officeLine,
        string $commentLine = '',
    ): string {
        $num = trim($orderNumber);
        $num = ltrim($num, "№# \t");
        $num = preg_replace('/^заказ\s+/iu', '', $num) ?? $num;
        $num = trim((string) $num);
        $street = trim($streetAddress);
        $office = self::normalizeOfficeLine($officeLine);
        $comment = trim($commentLine);

        $lines = ['Заказ '.$num];
        if ($street !== '') {
            $lines[] = $street;
        }
        if ($office !== '') {
            $lines[] = $office;
        }
        if ($comment !== '') {
            $lines[] = $comment;
        }

        return implode("\n", $lines);
    }

    public static function fromOrder(Order $order): ?string
    {
        $order->loadMissing(['address.city']);
        $flat = trim((string) ($order->address?->flat ?? ''));
        $adds = trim((string) ($order->address?->address_adds ?? ''));
        if ($flat === '' && $adds === '') {
            return null;
        }

        $street = self::formatStreetLine($order);

        if ($flat !== '') {
            $office = 'кв / офис: '.$flat;
            if ($adds !== '') {
                $office .= ' '.$adds;
            }

            return self::format((string) $order->order_id, $street, $office);
        }

        // Квартиры/офиса нет, но комментарий к адресу есть (код домофона, этаж,
        // как найти дом) — мастеру он всё равно нужен, раньше кнопка копирования
        // вообще не показывалась без flat и комментарий пропадал молча.
        return self::format((string) $order->order_id, $street, '', $adds);
    }

    public static function normalizeOfficeLine(string $officeLine): string
    {
        $office = trim($officeLine);
        if ($office === '') {
            return '';
        }
        if (preg_match('/кв/iu', $office)) {
            return $office;
        }

        return 'кв / офис: '.$office;
    }

    /**
     * «г. Город, Ермакова, д. 9а» — без слова «улица/ул.».
     */
    public static function formatStreetLine(Order $order): string
    {
        $addr = $order->address;
        if (! $addr) {
            return '';
        }

        $chunks = [];
        $city = trim((string) ($addr->city?->city_name ?? ''));
        if ($city !== '') {
            $chunks[] = preg_match('/^г\.?\s/u', $city) ? $city : 'г. '.$city;
        }

        $street = trim((string) ($addr->street ?? ''));
        $street = preg_replace('/^(ул\.?|улица)\s+/iu', '', $street) ?? $street;
        $street = trim((string) $street);
        if ($street !== '') {
            $chunks[] = $street;
        }

        $house = trim((string) ($addr->house ?? ''));
        if ($house !== '') {
            $chunks[] = preg_match('/^д\.?\s/u', $house) ? $house : 'д. '.$house;
        }

        return implode(', ', $chunks);
    }
}
