<?php

namespace Tests\Unit;

use App\Models\Address;
use App\Models\City;
use App\Models\Order;
use App\Support\MasterFlatCopyFormatter;
use Tests\TestCase;

class MasterFlatCopyFormatterTest extends TestCase
{
    public function test_formats_master_chat_block(): void
    {
        $text = MasterFlatCopyFormatter::format(
            '907',
            'г. Новокузнецк, Ермакова, д. 9а',
            'кв / офис: 268 офис'
        );

        $this->assertSame(
            "Заказ 907\nг. Новокузнецк, Ермакова, д. 9а\nкв / офис: 268 офис",
            $text
        );
    }

    public function test_from_order_matches_dispatcher_sample(): void
    {
        $city = new City(['city_name' => 'Новокузнецк']);
        $address = new Address([
            'street' => 'ул. Ермакова',
            'house' => '9а',
            'flat' => '268',
            'address_adds' => 'офис',
        ]);
        $address->setRelation('city', $city);
        $order = new Order;
        $order->forceFill(['order_id' => 907]);
        $order->setRelation('address', $address);

        $text = MasterFlatCopyFormatter::fromOrder($order);

        $this->assertSame(
            "Заказ 907\nг. Новокузнецк, Ермакова, д. 9а\nкв / офис: 268 офис",
            $text
        );
    }

    public function test_from_order_returns_null_without_flat(): void
    {
        $city = new City(['city_name' => 'Тула']);
        $address = new Address(['street' => 'Ленина', 'house' => '1', 'flat' => '']);
        $address->setRelation('city', $city);
        $order = new Order;
        $order->forceFill(['order_id' => 1]);
        $order->setRelation('address', $address);

        $this->assertNull(MasterFlatCopyFormatter::fromOrder($order));
    }

    public function test_normalizes_bare_office_number(): void
    {
        $this->assertSame('кв / офис: 12', MasterFlatCopyFormatter::normalizeOfficeLine('12'));
        $this->assertSame('кв / офис: 12', MasterFlatCopyFormatter::normalizeOfficeLine('кв / офис: 12'));
    }
}
