<?php

namespace Tests\Unit;

use App\Services\OpsHealthService;
use Tests\TestCase;

class OpsHealthDeferredMangoTest extends TestCase
{
    public function test_production_without_mango_secret_ok_when_not_required(): void
    {
        $this->app['env'] = 'production';
        config([
            'app.env' => 'production',
            'services.mango.webhook_secret' => '',
            'security.ops_health_require_mango' => false,
        ]);

        $snap = app(OpsHealthService::class)->snapshot();
        $this->assertTrue($snap['checks']['mango_webhook_secret']['ok']);
        $this->assertTrue($snap['checks']['mango_webhook_secret']['deferred']);
    }

    public function test_production_without_mango_secret_fails_when_required(): void
    {
        config([
            'app.env' => 'production',
            'services.mango.webhook_secret' => '',
            'security.ops_health_require_mango' => true,
        ]);
        $this->app['env'] = 'production';

        $snap = app(OpsHealthService::class)->snapshot();
        $this->assertFalse($snap['checks']['mango_webhook_secret']['ok']);
        $this->assertFalse($snap['ok']);
    }
}
