<?php

namespace Tests\Feature;

use App\Models\City;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GmOrgTreeApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.gm_api.bearer_token' => 'test-gm-token']);
    }

    public function test_org_tree_returns_json_for_valid_bearer_without_identity(): void
    {
        $city = City::query()->create([
            'city_name' => 'Тестоград',
            'city_type' => 'city',
            'city_timezone' => 'Europe/Moscow',
            'is_active' => true,
        ]);

        $masterRole = Role::query()->firstOrCreate(
            ['role_code' => 'master'],
            ['role_name' => 'Мастер', 'is_active' => true],
        );

        $master = User::query()->create([
            'user_name' => 'Мастер API',
            'email' => 'master-api@example.test',
            'password' => 'secret',
            'is_active' => true,
        ]);
        $master->roles()->attach($masterRole->role_id);
        $master->cities()->attach($city->city_id);

        $response = $this->getJson('/api/v1/gm/org/tree', [
            'Authorization' => 'Bearer test-gm-token',
        ]);

        $response->assertOk()
            ->assertHeader('Content-Type', 'application/json')
            ->assertJsonStructure([
                'generated_at',
                'scope' => ['all_cities', 'city_ids', 'viewer_id'],
                'company' => [
                    'general_directors',
                    'developers',
                    'call_center',
                    'senior_dispatchers',
                    'investors',
                ],
                'cities' => [
                    [
                        'id',
                        'name',
                        'timezone',
                        'type',
                        'parent_id',
                        'is_satellite',
                        'regional_directors',
                        'branch_heads',
                        'tech_directors',
                        'senior_managers',
                        'ad_managers',
                        'order_managers',
                        'masters',
                    ],
                ],
            ]);

        $response->assertJsonPath('scope.all_cities', true);
        $response->assertJsonPath('scope.viewer_id', null);
        $response->assertJsonPath('cities.0.name', 'Тестоград');
        $response->assertJsonPath('cities.0.is_satellite', false);

        $masterIds = collect($response->json('cities.0.masters'))->pluck('id')->all();
        $this->assertContains($master->user_id, $masterIds);
    }

    public function test_org_tree_returns_401_without_bearer(): void
    {
        $this->getJson('/api/v1/gm/org/tree')
            ->assertUnauthorized()
            ->assertJson(['message' => 'Missing Bearer token.']);
    }

    public function test_org_tree_returns_503_when_token_not_configured(): void
    {
        config(['services.gm_api.bearer_token' => '']);

        $this->getJson('/api/v1/gm/org/tree', [
            'Authorization' => 'Bearer anything',
        ])->assertStatus(503);
    }
}
