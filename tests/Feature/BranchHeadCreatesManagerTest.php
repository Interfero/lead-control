<?php

namespace Tests\Feature;

use App\Models\City;
use App\Models\Role;
use App\Models\User;
use App\Services\HrService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Директор филиала может регистрировать менеджеров (и мастеров) только в своих городах. */
class BranchHeadCreatesManagerTest extends TestCase
{
    use RefreshDatabase;

    private function role(string $code): Role
    {
        return Role::query()->firstOrCreate(['role_code' => $code], ['role_name' => $code, 'is_active' => true]);
    }

    private function makeHead(City $city): User
    {
        $u = User::query()->create([
            'user_name' => 'Директор', 'email' => 'head-'.uniqid('', true).'@example.test',
            'password' => 'secret', 'is_active' => true,
        ]);
        $u->roles()->attach($this->role('branch_head')->role_id);
        $u->cities()->attach($city->city_id);

        return $u->fresh(['roles']);
    }

    private function city(string $name): City
    {
        return City::query()->create([
            'city_name' => $name.' '.uniqid(), 'city_timezone' => 'Europe/Moscow', 'city_type' => 'city',
        ]);
    }

    public function test_branch_head_may_create_manager_role_but_not_directors(): void
    {
        $head = $this->makeHead($this->city('Тула'));
        $svc = app(HrService::class);

        $this->assertTrue($svc->canCreateWithRole($head, 'senior_manager'));
        $this->assertTrue($svc->canCreateWithRole($head, 'master'));
        $this->assertFalse($svc->canCreateWithRole($head, 'branch_head'));
        $this->assertFalse($svc->canCreateWithRole($head, 'regional_director'));
    }

    public function test_branch_head_creates_manager_in_own_city_but_not_in_foreign_city(): void
    {
        Storage::fake('local');
        Http::fake();
        $own = $this->city('Тула');
        $foreign = $this->city('Омск');
        $head = $this->makeHead($own);
        $managerRole = $this->role('senior_manager');

        $payload = fn (int $cityId, string $email) => [
            'user_name' => 'Новый Менеджер', 'email' => $email, 'role_id' => $managerRole->role_id,
            'city_ids' => [$cityId], 'files' => [UploadedFile::fake()->create('doc.pdf', 10, 'application/pdf')],
        ];

        $this->actingAs($head)->postJson(route('hr.store'), $payload($foreign->city_id, 'm1@example.test'))
            ->assertStatus(403);
        $this->assertSame(0, User::query()->where('email', 'like', 'm1@%')->count());

        $this->actingAs($head)->postJson(route('hr.store'), $payload($own->city_id, 'm2@example.test'))
            ->assertOk()->assertJson(['success' => true]);
        $created = User::query()->where('email', 'like', 'm2@%')->firstOrFail();
        $this->assertTrue($created->hasRole('senior_manager'));
        $this->assertSame([(int) $own->city_id], $created->cities()->pluck('cities.city_id')->map(fn ($i) => (int) $i)->all());
    }
}
