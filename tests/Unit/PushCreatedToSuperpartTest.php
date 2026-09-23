<?php

namespace Tests\Unit;

use App\Models\Order;
use App\Services\PartnerApiService;
use Tests\TestCase;

class PushCreatedToSuperpartTest extends TestCase
{
    public function test_push_returns_false_when_superpart_not_configured(): void
    {
        config([
            'services.superpart.base_url' => '',
            'services.superpart.api_key' => '',
            'services.superpart.api_secret' => '',
        ]);

        $api = new PartnerApiService;
        $order = new Order;
        $order->order_id = 1;

        $this->assertFalse($api->pushCreatedToSuperpart($order));
    }

    public function test_resolve_partner_null_when_source_not_superpart(): void
    {
        $api = new PartnerApiService;
        $order = new Order(['partner_user_id' => 8, 'source_id' => 1]);
        $source = new \App\Models\Source([
            'available_for_superpart' => false,
            'superpart_partner_id' => 8,
        ]);
        $order->setRelation('source', $source);

        $this->assertNull($api->resolvePartnerUserId($order));
    }

    public function test_resolve_partner_from_sp_source(): void
    {
        $api = new PartnerApiService;
        $order = new Order(['partner_user_id' => null, 'source_id' => 51]);
        $source = new \App\Models\Source([
            'available_for_superpart' => true,
            'superpart_partner_id' => 11,
        ]);
        $order->setRelation('source', $source);

        $this->assertSame(11, $api->resolvePartnerUserId($order));
    }
}
