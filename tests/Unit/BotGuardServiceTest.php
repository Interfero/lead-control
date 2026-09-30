<?php

namespace Tests\Unit;

use App\Models\User;
use App\Services\BotGuardService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class BotGuardServiceTest extends TestCase
{
    public function test_disabled_guard_does_not_kick(): void
    {
        config(['bot_guard.enabled' => false]);

        $user = $this->user(7);
        $guard = app(BotGuardService::class);
        $request = Request::create('/crm/orders/1', 'GET');

        $this->assertNull($guard->inspect($request, $user));
    }

    public function test_disabled_guard_ignores_existing_login_lock(): void
    {
        config(['bot_guard.enabled' => false]);
        Cache::put('bot_guard:lock:7', 'burst', now()->addMinutes(30));

        $user = $this->user(7);
        $guard = app(BotGuardService::class);

        $this->assertTrue($guard->isLocked(7));
        $this->assertFalse($guard->isLoginBlocked($user));
    }

    public function test_enabled_guard_blocks_login_when_locked(): void
    {
        config(['bot_guard.enabled' => true]);
        Cache::put('bot_guard:lock:8', 'burst', now()->addMinutes(30));

        $user = $this->user(8);
        $guard = app(BotGuardService::class);

        $this->assertTrue($guard->isLoginBlocked($user));
    }

    private function user(int $id): User
    {
        $user = \Mockery::mock(User::class)->makePartial();
        $user->user_id = $id;
        $user->shouldReceive('hasAnyRole')->andReturn(false);

        return $user;
    }
}
