<?php

namespace Tests\Unit;

use App\Models\Address;
use App\Models\City;
use App\Models\Order;
use App\Models\Person;
use App\Models\Source;
use App\Support\OrderCopyFormatter;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Tests\TestCase;

class OrderCopyFormatterTest extends TestCase
{
    public function test_noncore_party_order_matches_dispatcher_one_liner(): void
    {
        $order = $this->order([
            'order_id' => 699,
            'order_type' => 'new',
            'order_core' => 'non_core',
            'order_adds' => 'Симочный роутер. Телефоны работают. Пк выдает восклицательный знак. Работы от 3000',
            'datetime_order' => Carbon::parse('2026-09-25 12:00:00'),
        ], 'Рязань', 'ул. Коняева', '32А', 'М', 'БЫСТРОСЕРВИС', 'offline');

        $this->assertSame(
            'Заказ 699, Впервые, г. Рязань, Коняева, д. 32А, Симочный роутер. Телефоны работают. Пк выдает восклицательный знак. Работы от 3000, М, 25-09-2026 12:00, РК: БЫСТРОСЕРВИС, непрофильная',
            OrderCopyFormatter::format($order)
        );
    }

    public function test_profile_order_omits_noncore_label_and_age(): void
    {
        $order = $this->order([
            'order_id' => 12,
            'order_type' => 'repeat',
            'order_core' => 'core',
            'order_adds' => 'Не включается',
            'datetime_order' => Carbon::parse('2026-09-01 09:30:00'),
        ], 'Тула', 'Ленина', '10', 'Иван', 'Листовка', null);

        $text = OrderCopyFormatter::format($order);

        $this->assertStringContainsString('Заказ 12, Повтор, г. Тула, Ленина, д. 10', $text);
        $this->assertStringContainsString('Иван', $text);
        $this->assertStringNotContainsString('непрофильная', $text);
        $this->assertStringNotContainsString('№', $text);
        $this->assertStringNotContainsString('ул.', $text);
    }

    public function test_includes_address_comment_in_copy_text(): void
    {
        $order = $this->order([
            'order_id' => 843,
            'order_type' => 'new',
            'order_core' => 'non_core',
            'order_adds' => 'Принтер плохо печатал глянцевую бумагу',
            'datetime_order' => Carbon::parse('2026-10-03 12:18:00'),
        ], 'Йошкар-Ола', 'Коммунистическая', '14', 'М', 'РК', null);
        $order->address->address_adds = 'Пос Медведево';

        $this->assertStringContainsString(
            'г. Йошкар-Ола, Коммунистическая, д. 14, Пос Медведево, Принтер плохо печатал глянцевую бумагу',
            OrderCopyFormatter::format($order)
        );
    }

    public function test_strips_rush_word_from_description(): void
    {
        $order = $this->order([
            'order_id' => 3,
            'order_type' => 'new',
            'order_core' => 'non_core',
            'order_adds' => 'Поспешить. Не работает wifi',
            'datetime_order' => Carbon::parse('2026-09-25 12:00:00'),
        ], 'Рязань', 'Коняева', '1', 'М', 'РК', null);

        $this->assertStringNotContainsString('Поспешить', OrderCopyFormatter::format($order));
        $this->assertStringContainsString('Не работает wifi', OrderCopyFormatter::format($order));
    }

    public function test_party_source_link_is_appended_once(): void
    {
        $url = 'https://www.avito.ru/brands/i1';
        $order = $this->order([
            'order_id' => 5, 'order_type' => 'new', 'order_core' => 'core',
            'order_adds' => 'Не включается', 'datetime_order' => Carbon::parse('2026-09-01 09:30:00'),
        ], 'Тула', 'Ленина', '10', 'Иван', 'Партнёр', null);
        $order->source->source_kind = Source::KIND_PARTY;
        $order->source->use_source_url = true;
        $order->source->source_url = $url;

        $this->assertStringEndsWith('РК: Партнёр, '.$url, OrderCopyFormatter::format($order));

        $order->order_adds = 'Не включается '.$url;
        $this->assertSame(1, substr_count(OrderCopyFormatter::format($order), $url));
    }

    /**
     * @param  array<string, mixed>  $attrs
     */
    private function order(
        array $attrs,
        string $cityName,
        string $street,
        string $house,
        string $personName,
        string $sourceName,
        ?string $sourceFormat,
    ): Order {
        $city = new City(['city_name' => $cityName]);
        $address = new Address([
            'street' => $street,
            'house' => $house,
        ]);
        $address->setRelation('city', $city);

        $source = new Source([
            'source_name' => $sourceName,
            'source_format' => $sourceFormat,
        ]);

        $person = new Person([
            'person_name' => $personName,
            'person_age' => 30,
        ]);

        $order = new Order($attrs);
        $order->order_id = (int) $attrs['order_id'];
        $order->setRelation('address', $address);
        $order->setRelation('source', $source);
        $order->setRelation('persons', new Collection([$person]));

        return $order;
    }
}
