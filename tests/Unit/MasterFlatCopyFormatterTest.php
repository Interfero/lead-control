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

    public function test_from_order_returns_null_without_flat_and_without_comment(): void
    {
        $city = new City(['city_name' => 'Тула']);
        $address = new Address(['street' => 'Ленина', 'house' => '1', 'flat' => '']);
        $address->setRelation('city', $city);
        $order = new Order;
        $order->forceFill(['order_id' => 1]);
        $order->setRelation('address', $address);

        $this->assertNull(MasterFlatCopyFormatter::fromOrder($order));
    }

    public function test_from_order_keeps_address_comment_without_flat(): void
    {
        // Заявка 962: квартиры/офиса нет (частный дом/улица), но есть комментарий
        // к адресу — раньше кнопка копирования пропадала целиком и комментарий
        // мастеру не попадал вообще.
        $city = new City(['city_name' => 'Адлер']);
        $address = new Address([
            'street' => 'Урожайная',
            'house' => '79',
            'flat' => '',
            'address_adds' => 'Код домофона 1234',
        ]);
        $address->setRelation('city', $city);
        $order = new Order;
        $order->forceFill(['order_id' => 962]);
        $order->setRelation('address', $address);

        $text = MasterFlatCopyFormatter::fromOrder($order);

        $this->assertSame(
            "Заказ 962\nг. Адлер, Урожайная, д. 79\nКод домофона 1234",
            $text
        );
    }

    public function test_normalizes_bare_office_number(): void
    {
        $this->assertSame('кв / офис: 12', MasterFlatCopyFormatter::normalizeOfficeLine('12'));
        $this->assertSame('кв / офис: 12', MasterFlatCopyFormatter::normalizeOfficeLine('кв / офис: 12'));
    }
}
