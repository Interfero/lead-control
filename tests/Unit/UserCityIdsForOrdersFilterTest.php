<?php

namespace Tests\Unit;

use App\Models\City;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserCityIdsForOrdersFilterTest extends TestCase
{
    use RefreshDatabase;

    public function test_access_all_cities_returns_null_scope(): void
    {
        $user = User::factory()->create(['access_all_cities' => true]);
        $this->assertNull($user->cityIdsForOrdersFilter());
    }

    public function test_assigned_city_expands_to_operation_group(): void
    {
        $nn = City::query()->create([
            'city_name' => 'Нижний Новгород',
            'city_type' => 'city',
            'city_timezone' => 'Europe/Moscow',
            'is_active' => true,
        ]);
        $sat = City::query()->create([
            'city_name' => 'Дзержинск',
            'city_type' => 'city',
            'city_timezone' => 'Europe/Moscow',
            'parent_city_id' => $nn->city_id,
            'is_active' => true,
        ]);

        $user = User::factory()->create(['access_all_cities' => false]);
        $user->cities()->attach($nn->city_id);

        $ids = $user->cityIdsForOrdersFilter();
        $this->assertIsArray($ids);
        $this->assertContains((int) $nn->city_id, $ids);
        $this->assertContains((int) $sat->city_id, $ids);
    }
}
