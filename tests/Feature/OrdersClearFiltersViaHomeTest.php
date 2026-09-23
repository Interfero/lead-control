<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrdersClearFiltersViaHomeTest extends TestCase
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
            'email' => 'dev-clear-filters@example.test',
            'password' => 'secret',
            'is_active' => true,
        ]);
        $user->roles()->attach($role->role_id);

        return $user;
    }

    public function test_clear_filters_forgets_session_and_lands_on_clean_index(): void
    {
        $user = $this->developer();
        $sessionKey = 'orders_list_filters_'.$user->user_id;

        $this->actingAs($user)
            ->withSession([$sessionKey => ['search_address' => 'Энергетиков']])
            ->get(route('orders.index', ['clear_filters' => 1]))
            ->assertRedirect(route('orders.index'));

        $this->actingAs($user)
            ->get(route('orders.index'))
            ->assertOk()
            ->assertSessionMissing($sessionKey);
    }

    public function test_logo_target_uses_clear_filters(): void
    {
        $user = $this->developer();

        $this->actingAs($user)
            ->get(route('orders.index', ['clear_filters' => 1]))
            ->assertRedirect(route('orders.index'));

        $html = $this->actingAs($user)
            ->get(route('orders.index'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString(
            'clear_filters=1',
            $html,
            'Логотип / меню заказов должны вести на сброс фильтров'
        );
    }
}
