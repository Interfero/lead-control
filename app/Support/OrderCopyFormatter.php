<?php

namespace App\Support;

use App\Models\Order;
use App\Models\Source;

class OrderCopyFormatter
{
    /**
     * Одна строка для буфера: заказ, тип, адрес, описание, клиент, дата, РК, непрофиль.
     */
    public static function format(Order $order): string
    {
        $order->loadMissing(['address.city', 'source', 'persons']);

        $typeLabels = [
            'new' => 'Впервые',
            'first' => 'Впервые',
            'repeat' => 'Повтор',
            'warranty' => 'Гарантия',
        ];

        $parts = [];
        $parts[] = 'Заказ '.$order->order_id;
        $parts[] = $typeLabels[$order->order_type] ?? null;

        $address = self::formatAddress($order);
        if ($address !== '') {
            $parts[] = $address;
        }

        $adds = self::cleanDescription((string) ($order->order_adds ?? ''));
        if ($adds !== '') {
            $parts[] = $adds;
        }

        $personName = trim((string) ($order->persons->first()?->person_name ?? ''));
        if ($personName !== '') {
            $parts[] = $personName;
        }

        if ($order->datetime_order) {
            $parts[] = $order->datetime_order->format('d-m-Y H:i');
        }

        $rk = self::rkName($order->source);
        if ($rk !== '') {
            $parts[] = 'РК: '.$rk;
        }

        // Ссылку РК не дублируем, если она уже есть в описании (подставляется при создании заявки).
        $rkUrl = $order->source?->deskRkUrl();
        if ($rkUrl !== null && ! str_contains($adds, $rkUrl)) {
            $parts[] = $rkUrl;
        }

        if ($order->order_core === 'non_core') {
            $parts[] = 'непрофильная';
        }

        return implode(', ', array_values(array_filter($parts, fn ($p) => $p !== null && $p !== '')));
    }

    public static function formatAddress(Order $order): string
    {
        $addr = $order->address;
        if (! $addr) {
            return '';
        }

        $chunks = [];
        $city = trim((string) ($addr->city?->city_name ?? ''));
        if ($city !== '') {
            $chunks[] = self::withCityPrefix($city);
        }

        $street = self::streetWithoutTypePrefix(trim((string) ($addr->street ?? '')));
        if ($street !== '') {
            $chunks[] = $street;
        }

        $house = trim((string) ($addr->house ?? ''));
        if ($house !== '') {
            $chunks[] = self::withHousePrefix($house);
        }

        $addressAdds = self::cleanDescription((string) ($addr->address_adds ?? ''));
        if ($addressAdds !== '') {
            $chunks[] = $addressAdds;
        }

        return implode(', ', $chunks);
    }

    private static function cleanDescription(string $adds): string
    {
        $adds = preg_replace('/поспешить\.?\s*/iu', '', $adds) ?? $adds;
        $adds = str_replace(["\r\n", "\n", "\r", '"', "'"], ' ', $adds);
        $adds = preg_replace('/[ \t]+/u', ' ', $adds) ?? $adds;

        return trim($adds);
    }

    private static function rkName(?Source $source): string
    {
        if (! $source) {
            return '';
        }

        return $source->deskRkName();
    }

    private static function withCityPrefix(string $city): string
    {
        if (preg_match('/^г\.?\s/u', $city)) {
            return $city;
        }

        return 'г. '.$city;
    }

    private static function streetWithoutTypePrefix(string $street): string
    {
        $stripped = preg_replace('/^(ул\.?|улица)\s+/iu', '', $street);

        return trim((string) ($stripped ?? $street));
    }

    private static function withHousePrefix(string $house): string
    {
        if (preg_match('/^д\.?\s/u', $house)) {
            return $house;
        }

        return 'д. '.$house;
    }
}
