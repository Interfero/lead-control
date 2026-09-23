<?php

namespace Tests\Unit;

use App\Models\Order;
use App\Services\OrderService;
use Tests\TestCase;

class OrderServiceMasterPercentTest extends TestCase
{
    private function service(): OrderService
    {
        return app(OrderService::class);
    }

    public function test_non_core_up_to_7500_is_40_percent(): void
    {
        $order = new Order([
            'order_core' => 'non_core',
            'order_type' => 'first',
            'amount_paid' => 3600,
            'amount_comp' => 0,
            'is_long_trip' => false,
        ]);

        $this->assertSame(40, $this->service()->getMasterPercent($order));
        $this->assertSame(1440, $this->service()->calculateMasterSalary($order));
    }

    public function test_profile_3600_is_30_percent(): void
    {
        $order = new Order([
            'order_core' => 'core',
            'order_type' => 'first',
            'amount_paid' => 3600,
            'amount_comp' => 0,
            'is_long_trip' => false,
        ]);

        $this->assertSame(30, $this->service()->getMasterPercent($order));
    }

    public function test_long_trip_is_50_percent(): void
    {
        $order = new Order([
            'order_core' => 'non_core',
            'order_type' => 'first',
            'amount_paid' => 3600,
            'amount_comp' => 0,
            'is_long_trip' => true,
        ]);

        $this->assertSame(50, $this->service()->getMasterPercent($order));
    }

    public function test_other_partner_order_is_40_percent(): void
    {
        $order = new Order([
            'order_core' => 'other',
            'order_type' => 'first',
            'amount_paid' => 10000,
            'amount_comp' => 0,
            'partner_user_id' => 8,
        ]);

        $this->assertSame(40, $this->service()->getMasterPercent($order));
    }

    public function test_desk_extras_mark_non_core(): void
    {
        $order = new Order([
            'order_core' => 'non_core',
            'order_type' => 'first',
            'amount_paid' => 3600,
            'amount_comp' => 0,
            'is_long_trip' => false,
        ]);

        $extras = $this->service()->deskSerializationExtras($order);

        $this->assertTrue($extras['is_noncore']);
        $this->assertSame('non_core', $extras['order_core']);
        $this->assertSame(40, $extras['calculation']['master_percent']);
        $this->assertSame(1440, $extras['calculation']['master_salary']);
    }
}
