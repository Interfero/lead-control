<?php

namespace Tests\Unit;

use App\Models\City;
use App\Models\Role;
use App\Models\User;
use App\Services\GmApiService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GmApiProfileAndBranchTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_includes_all_roles_and_cities(): void
    {
        $moscow = $this->city('Москва');
        $kazan = $this->city('Казань');

        $user = $this->user('Рег');
        $user->roles()->attach([
            $this->role('regional_director')->role_id,
            $this->role('branch_head')->role_id,
        ]);
        $user->cities()->attach([$moscow->city_id, $kazan->city_id]);

        $profile = app(GmApiService::class)->profile($user->fresh(['roles', 'cities']));

        $this->assertEqualsCanonicalizing(['regional_director', 'branch_head'], $profile['roles']);
        $this->assertContains((int) $moscow->city_id, $profile['city_ids']);
        $this->assertContains((int) $kazan->city_id, $profile['city_ids']);
        $this->assertFalse($profile['access_all_cities']);
        $this->assertTrue($profile['isBranchDirector']);

        $cityIds = array_column($profile['cities'], 'id');
        $this->assertContains((int) $moscow->city_id, $cityIds);
        $this->assertContains((int) $kazan->city_id, $cityIds);
        $this->assertArrayHasKey('parent_id', $profile['cities'][0]);
        $this->assertArrayHasKey('is_satellite', $profile['cities'][0]);
        $this->assertArrayHasKey('name', $profile['cities'][0]);
        $this->assertNull($profile['inn']);
        $this->assertIsInt($profile['rating']);
        $this->assertGreaterThanOrEqual(0, $profile['rating']);
        $this->assertLessThanOrEqual(100, $profile['rating']);
    }

    public function test_profile_returns_normalized_inn(): void
    {
        $user = $this->user('С ИНН');
        $user->user_inn = '7707083893';
        $user->save();
        $user->roles()->attach($this->role('master')->role_id);

        $profile = app(GmApiService::class)->profile($user->fresh(['roles', 'cities', 'documents']));

        $this->assertSame('7707083893', $profile['inn']);
    }

    public function test_profile_inn_invalid_becomes_null(): void
    {
        $user = $this->user('Плохой ИНН');
        $user->user_inn = '123';
        $user->save();

        $profile = app(GmApiService::class)->profile($user->fresh(['roles', 'cities', 'documents']));

        $this->assertNull($profile['inn']);
    }

    public function test_update_inn_persists_valid_value(): void
    {
        $user = $this->user('Запись ИНН');

        $result = app(GmApiService::class)->updateInn($user, '7707083893');

        $this->assertSame(200, $result['status']);
        $this->assertSame([
            'ok' => true,
            'userId' => (int) $user->user_id,
            'inn' => '7707083893',
        ], $result['body']);

        $user->refresh();
        $this->assertSame('7707083893', $user->user_inn);
    }

    public function test_update_inn_rejects_non_digit_input(): void
    {
        $user = $this->user('Плохой ввод');

        $result = app(GmApiService::class)->updateInn($user, '12 34567890');

        $this->assertSame(422, $result['status']);
        $this->assertSame('invalid_inn', $result['body']['error']);
        $this->assertNull($user->fresh()->user_inn);
    }

    public function test_metrics_counters_are_integers(): void
    {
        $user = $this->user('Метрики');
        $user->roles()->attach($this->role('master')->role_id);
        $user->cities()->attach($this->city('Самара')->city_id);

        $metrics = app(GmApiService::class)->metrics($user->fresh(['roles', 'cities']));

        foreach ([
            'masterEarnedRub',
            'primaryAvgCheckRub',
            'primaryClosedOrdersMonth',
            'masterMonthRefusals',
            'masterMonthCancelledApplications',
            'primarySalaryRub',
            'branchCashRub',
            'unreadMessages',
            'deputyUnreadMessages',
        ] as $key) {
            $this->assertArrayHasKey($key, $metrics);
            $this->assertIsInt($metrics[$key], $key);
        }
    }

    public function test_profile_call_center_sees_all_cities(): void
    {
        $this->city('Москва');
        $this->city('Казань');

        $user = $this->user('КЦ');
        $user->roles()->attach($this->role('call_center')->role_id);

        $profile = app(GmApiService::class)->profile($user->fresh(['roles', 'cities']));

        $this->assertSame(['call_center'], $profile['roles']);
        $this->assertNull($profile['city_ids']);
        $this->assertTrue($profile['access_all_cities']);
        $this->assertCount(2, $profile['cities']);
        $this->assertNull($profile['branchId']);
    }

    public function test_branch_roster_city_id_selects_that_city(): void
    {
        $moscow = $this->city('Москва');
        $kazan = $this->city('Казань');
        $masterRole = $this->role('master');

        $mskMaster = $this->user('Мастер Мск');
        $mskMaster->roles()->attach($masterRole->role_id);
        $mskMaster->cities()->attach($moscow->city_id);

        $kznMaster = $this->user('Мастер Кзн');
        $kznMaster->roles()->attach($masterRole->role_id);
        $kznMaster->cities()->attach($kazan->city_id);

        $reg = $this->user('Рег');
        $reg->roles()->attach($this->role('regional_director')->role_id);
        $reg->cities()->attach([$moscow->city_id, $kazan->city_id]);
        $reg->load(['roles', 'cities']);

        $service = app(GmApiService::class);

        $default = $service->branchRoster($reg);
        $this->assertSame((int) $reg->cities->first()->city_id, $default['branchId']);

        $kazanRoster = $service->branchRoster($reg, (int) $kazan->city_id);
        $this->assertSame((int) $kazan->city_id, $kazanRoster['branchId']);
        $this->assertSame('Казань', $kazanRoster['branchCity']);
        $ids = array_column($kazanRoster['items'], 'id');
        $this->assertContains((int) $kznMaster->user_id, $ids);
        $this->assertNotContains((int) $mskMaster->user_id, $ids);
    }

    public function test_branch_stats_forbidden_city_throws(): void
    {
        $moscow = $this->city('Москва');
        $kazan = $this->city('Казань');

        $dir = $this->user('Дир Москвы');
        $dir->roles()->attach($this->role('branch_head')->role_id);
        $dir->cities()->attach($moscow->city_id);
        $dir->load(['roles', 'cities']);

        $this->expectException(AuthorizationException::class);
        app(GmApiService::class)->branchStats($dir, (int) $kazan->city_id);
    }

    private function city(string $name): City
    {
        return City::query()->create([
            'city_name' => $name,
            'city_type' => 'city',
            'city_timezone' => 'Europe/Moscow',
            'is_active' => true,
        ]);
    }

    private function role(string $code): Role
    {
        return Role::query()->firstOrCreate(
            ['role_code' => $code],
            ['role_name' => $code, 'is_active' => true],
        );
    }

    private function user(string $name): User
    {
        return User::query()->create([
            'user_name' => $name,
            'email' => 'gm.'.uniqid('', true).'@example.test',
            'password' => 'secret',
            'is_active' => true,
            'access_all_cities' => false,
        ]);
    }
}
