<?php

namespace Tests\Unit;

use App\Models\Order;
use App\Services\CallbackNotificationService;
use Carbon\Carbon;
use Tests\TestCase;

class CallbackEventUrgencyTest extends TestCase
{
    public function test_pending_blinks_within_30_minutes(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-11 11:40:00', 'Europe/Moscow'));

        $order = new Order([
            'order_status' => 'pending',
            'datetime_order' => Carbon::parse('2026-09-11 12:00:00'),
        ]);
        $order->setRelation('address', (object) [
            'city' => (object) ['city_timezone' => 'Europe/Moscow'],
        ]);

        // address is Eloquent relation normally — set via anonymous for city access
        $addr = new class {
            public $city;
        };
        $city = new class {
            public $city_timezone = 'Europe/Moscow';
        };
        $addr->city = $city;
        $order->setRelation('address', $addr);

        $this->assertSame('blink-red', $order->eventUrgencyClass());
        Carbon::setTestNow();
    }

    public function test_on_way_never_blinks(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-11 11:40:00', 'Europe/Moscow'));
        $order = new Order([
            'order_status' => 'on_way',
            'datetime_order' => Carbon::parse('2026-09-11 12:00:00'),
        ]);
        $addr = new class {
            public $city;
        };
        $city = new class {
            public $city_timezone = 'Europe/Moscow';
        };
        $addr->city = $city;
        $order->setRelation('address', $addr);

        $this->assertSame('', $order->eventUrgencyClass());
        Carbon::setTestNow();
    }

    public function test_callback_overdue_is_solid_red(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-11 13:00:00', 'Europe/Moscow'));
        $order = new Order([
            'order_status' => 'callback',
            'datetime_order' => Carbon::parse('2026-09-11 12:00:00'),
        ]);
        $addr = new class {
            public $city;
        };
        $city = new class {
            public $city_timezone = 'Europe/Moscow';
        };
        $addr->city = $city;
        $order->setRelation('address', $addr);

        $this->assertSame('callback-overdue', $order->eventUrgencyClass());
        Carbon::setTestNow();
    }
}
