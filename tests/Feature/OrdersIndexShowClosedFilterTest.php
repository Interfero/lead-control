<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrdersIndexShowClosedFilterTest extends TestCase
{
    use RefreshDatabase;

    private function developer(): User
    {
        $role = Role::query()->firstOrCreate(
            ['role_code' => 'developer'],
            ['role_name' => 'Разработчик', 'is_active' => true],
        );

        $user = User::query()->create([
            'user_name' => 'Разработчик',
            'email' => 'dev-orders-filter@example.test',
            'password' => 'secret',
            'is_active' => true,
        ]);
        $user->roles()->attach($role->role_id);

        return $user;
    }

    public function test_show_closed_zero_opens_active_list(): void
    {
        $user = $this->developer();

        $this->actingAs($user)
            ->get(route('orders.index', ['show_closed' => '0']))
            ->assertOk()
            ->assertSee('Закрытые')
            ->assertDontSee('>Активные<', false);
    }

    public function test_active_link_does_not_restore_closed_mode_from_session(): void
    {
        $user = $this->developer();

        $this->actingAs($user)
            ->get(route('orders.index', ['show_closed' => '1']))
            ->assertOk()
            ->assertSee('Активные');

        $this->actingAs($user)
            ->get(route('orders.index'))
            ->assertOk()
            ->assertSee('Закрытые')
            ->assertDontSee('>Активные<', false);
    }
}
