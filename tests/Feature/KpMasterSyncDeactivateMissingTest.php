<?php

namespace Tests\Feature;

use App\Models\City;
use App\Models\Role;
use App\Models\User;
use App\Services\KpMasterSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KpMasterSyncDeactivateMissingTest extends TestCase
{
    use RefreshDatabase;

    public function test_missing_kp_master_is_deactivated_when_no_other_city(): void
    {
        [$role, $cityA] = $this->roleAndCities();
        $gone = $this->kpMaster($role, 101, [$cityA->city_id]);
        $kept = $this->kpMaster($role, 102, [$cityA->city_id]);

        $result = app(KpMasterSyncService::class)->upsertMany(
            [[
                'kp_employee_id' => 102,
                'name' => $kept->user_name,
                'is_active' => true,
                'city_ids' => [$cityA->city_id],
            ]],
            (int) $cityA->city_id,
            true
        );

        $this->assertSame(1, $result['deactivated']);
        $this->assertFalse((bool) $gone->fresh()->is_active);
        $this->assertNotNull($gone->fresh()->user_fired_at);
        $this->assertFalse($gone->cities()->where('cities.city_id', $cityA->city_id)->exists());
        $this->assertTrue((bool) $kept->fresh()->is_active);
        $this->assertTrue($kept->cities()->where('cities.city_id', $cityA->city_id)->exists());
    }

    public function test_missing_in_one_city_stays_active_in_another(): void
    {
        [$role, $cityA, $cityB] = $this->roleAndCities();
        $multi = $this->kpMaster($role, 201, [$cityA->city_id, $cityB->city_id]);

        $result = app(KpMasterSyncService::class)->upsertMany(
            [[
                'kp_employee_id' => 999,
                'name' => 'Другой',
                'is_active' => true,
                'city_ids' => [$cityA->city_id],
            ]],
            (int) $cityA->city_id,
            true
        );

        $this->assertSame(0, $result['deactivated']);
        $this->assertTrue((bool) $multi->fresh()->is_active);
        $this->assertFalse($multi->cities()->where('cities.city_id', $cityA->city_id)->exists());
        $this->assertTrue($multi->cities()->where('cities.city_id', $cityB->city_id)->exists());
    }

    public function test_native_lc_master_without_kp_id_is_untouched(): void
    {
        [$role, $cityA] = $this->roleAndCities();
        $native = User::query()->create([
            'user_name' => 'Свой мастер',
            'email' => 'native-master@example.test',
            'password' => 'secret',
            'is_active' => true,
        ]);
        $native->roles()->attach($role->role_id);
        $native->cities()->attach($cityA->city_id);

        app(KpMasterSyncService::class)->upsertMany(
            [[
                'kp_employee_id' => 301,
                'name' => 'Из КП',
                'is_active' => true,
                'city_ids' => [$cityA->city_id],
            ]],
            (int) $cityA->city_id,
            true
        );

        $this->assertTrue((bool) $native->fresh()->is_active);
        $this->assertTrue($native->cities()->where('cities.city_id', $cityA->city_id)->exists());
    }

    public function test_second_city_sync_does_not_detach_first_city(): void
    {
        [$role, $cityA, $cityB] = $this->roleAndCities();
        $svc = app(KpMasterSyncService::class);

        $svc->upsertMany(
            [[
                'kp_employee_id' => 401,
                'name' => 'Два города',
                'is_active' => true,
                'city_ids' => [$cityA->city_id],
            ]],
            (int) $cityA->city_id,
            true
        );
        $svc->upsertMany(
            [[
                'kp_employee_id' => 401,
                'name' => 'Два города',
                'is_active' => true,
                'city_ids' => [$cityB->city_id],
            ]],
            (int) $cityB->city_id,
            true
        );

        $user = User::query()->where('kp_employee_id', 401)->first();
        $this->assertNotNull($user);
        $ids = $user->cities()->pluck('cities.city_id')->map(fn ($id) => (int) $id)->all();
        $this->assertContains((int) $cityA->city_id, $ids);
        $this->assertContains((int) $cityB->city_id, $ids);
    }

    public function test_failed_empty_payload_does_not_fire_anyone(): void
    {
        [$role, $cityA] = $this->roleAndCities();
        $kept = $this->kpMaster($role, 501, [$cityA->city_id]);

        $result = app(KpMasterSyncService::class)->upsertMany([], (int) $cityA->city_id, true);

        $this->assertSame(0, $result['deactivated']);
        $this->assertTrue((bool) $kept->fresh()->is_active);
    }

    /**
     * @return array{0: Role, 1: City, 2?: City}
     */
    private function roleAndCities(): array
    {
        $role = Role::query()->firstOrCreate(
            ['role_code' => 'master'],
            ['role_name' => 'Мастер', 'is_active' => true],
        );
        $cityA = City::query()->create(['city_name' => 'Город А '.uniqid(), 'is_active' => true]);
        $cityB = City::query()->create(['city_name' => 'Город Б '.uniqid(), 'is_active' => true]);

        return [$role, $cityA, $cityB];
    }

    /**
     * @param  list<int>  $cityIds
     */
    private function kpMaster(Role $role, int $kpId, array $cityIds): User
    {
        $user = User::query()->create([
            'kp_employee_id' => $kpId,
            'user_name' => 'КП '.$kpId,
            'email' => 'kp'.$kpId.'@example.test',
            'password' => 'secret',
            'is_active' => true,
            'user_note' => 'Импорт из КП (kp_employee_id='.$kpId.')',
        ]);
        $user->roles()->attach($role->role_id);
        $user->cities()->attach($cityIds);

        return $user;
    }
}
