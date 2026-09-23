<?php

namespace Tests\Unit;

use App\Models\City;
use App\Models\Role;
use App\Models\User;
use App\Services\OrgTreeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrgTreeServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_tree_groups_people_by_city_and_company_roles(): void
    {
        $moscow = $this->city('Москва');
        $kazan = $this->city('Казань');

        $masterRole = $this->role('master');
        $regRole = $this->role('regional_director');
        $dirRole = $this->role('branch_head');
        $gdRole = $this->role('general_director');

        $master = $this->user('Мастер Москвы');
        $master->roles()->attach($masterRole->role_id);
        $master->cities()->attach($moscow->city_id);

        $reg = $this->user('Рег двух городов');
        $reg->roles()->attach($regRole->role_id);
        $reg->cities()->attach([$moscow->city_id, $kazan->city_id]);

        $dir = $this->user('Дир Казани');
        $dir->roles()->attach($dirRole->role_id);
        $dir->cities()->attach($kazan->city_id);

        $gd = $this->user('Гендир');
        $gd->roles()->attach($gdRole->role_id);

        $tree = app(OrgTreeService::class)->tree();

        $this->assertTrue($tree['scope']['all_cities']);
        $this->assertNull($tree['scope']['viewer_id']);

        $gdIds = array_column($tree['company']['general_directors'], 'id');
        $this->assertContains((int) $gd->user_id, $gdIds);

        $byName = collect($tree['cities'])->keyBy('name');
        $moscowNode = $byName['Москва'];
        $kazanNode = $byName['Казань'];

        $this->assertContains((int) $master->user_id, array_column($moscowNode['masters'], 'id'));
        $this->assertNotContains((int) $master->user_id, array_column($kazanNode['masters'], 'id'));

        $this->assertContains((int) $reg->user_id, array_column($moscowNode['regional_directors'], 'id'));
        $this->assertContains((int) $reg->user_id, array_column($kazanNode['regional_directors'], 'id'));

        $this->assertContains((int) $dir->user_id, array_column($kazanNode['branch_heads'], 'id'));
        $this->assertNotContains((int) $gd->user_id, array_column($moscowNode['branch_heads'], 'id'));
        $this->assertNotContains((int) $gd->user_id, array_column($moscowNode['masters'], 'id'));
    }

    public function test_viewer_scope_hides_other_cities(): void
    {
        $moscow = $this->city('Москва');
        $kazan = $this->city('Казань');
        $masterRole = $this->role('master');

        $mskMaster = $this->user('Мск');
        $mskMaster->roles()->attach($masterRole->role_id);
        $mskMaster->cities()->attach($moscow->city_id);

        $kznMaster = $this->user('Кзн');
        $kznMaster->roles()->attach($masterRole->role_id);
        $kznMaster->cities()->attach($kazan->city_id);

        $viewer = $this->user('Дир Москвы', ['access_all_cities' => false]);
        $viewer->roles()->attach($this->role('branch_head')->role_id);
        $viewer->cities()->attach($moscow->city_id);
        $viewer->load('cities');

        $tree = app(OrgTreeService::class)->tree($viewer);

        $this->assertFalse($tree['scope']['all_cities']);
        $this->assertEquals([(int) $moscow->city_id], $tree['scope']['city_ids']);
        $this->assertCount(1, $tree['cities']);
        $this->assertSame('Москва', $tree['cities'][0]['name']);
        $this->assertContains((int) $mskMaster->user_id, array_column($tree['cities'][0]['masters'], 'id'));
        $this->assertNotContains((int) $kznMaster->user_id, array_column($tree['cities'][0]['masters'], 'id'));
    }

    public function test_inactive_fired_and_blacklisted_users_are_excluded(): void
    {
        $moscow = $this->city('Москва');
        $masterRole = $this->role('master');

        $active = $this->user('Активный');
        $active->roles()->attach($masterRole->role_id);
        $active->cities()->attach($moscow->city_id);

        $inactive = $this->user('Неактивный', ['is_active' => false]);
        $inactive->roles()->attach($masterRole->role_id);
        $inactive->cities()->attach($moscow->city_id);

        $fired = $this->user('Уволенный', ['user_fired_at' => now()->toDateString()]);
        $fired->roles()->attach($masterRole->role_id);
        $fired->cities()->attach($moscow->city_id);

        $blocked = $this->user('ЧС', ['is_blacklisted' => true]);
        $blocked->roles()->attach($masterRole->role_id);
        $blocked->cities()->attach($moscow->city_id);

        $tree = app(OrgTreeService::class)->tree();
        $masterIds = array_column($tree['cities'][0]['masters'], 'id');

        $this->assertContains((int) $active->user_id, $masterIds);
        $this->assertNotContains((int) $inactive->user_id, $masterIds);
        $this->assertNotContains((int) $fired->user_id, $masterIds);
        $this->assertNotContains((int) $blocked->user_id, $masterIds);
    }

    public function test_call_center_in_company_has_access_all_cities_flag(): void
    {
        $this->city('Москва');
        $cc = $this->user('КЦ');
        $cc->roles()->attach($this->role('call_center')->role_id);

        $tree = app(OrgTreeService::class)->tree();
        $this->assertCount(1, $tree['company']['call_center']);
        $this->assertTrue($tree['company']['call_center'][0]['access_all_cities']);
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

    private function user(string $name, array $extra = []): User
    {
        return User::query()->create(array_merge([
            'user_name' => $name,
            'email' => 'orgtree.'.uniqid('', true).'@example.test',
            'password' => 'secret',
            'is_active' => true,
            'access_all_cities' => false,
        ], $extra));
    }
}
