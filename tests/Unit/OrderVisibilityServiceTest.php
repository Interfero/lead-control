<?php

namespace Tests\Unit;

use App\Models\Role;
use App\Models\User;
use App\Services\OrderVisibilityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderVisibilityServiceTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @dataProvider cityDirectorRoles
     */
    public function test_branch_and_regional_cannot_set_review(string $roleCode): void
    {
        $statuses = app(OrderVisibilityService::class)->getSettableStatuses($this->userWithRole($roleCode));

        $this->assertNotContains('review', $statuses);
        $this->assertContains('pending', $statuses);
        $this->assertContains('in_progress', $statuses);
    }

    /** @return list<array{0: string}> */
    public static function cityDirectorRoles(): array
    {
        return [
            ['branch_head'],
            ['regional_director'],
            ['senior_manager'],
        ];
    }

    private function userWithRole(string $roleCode): User
    {
        $role = Role::query()->firstOrCreate(
            ['role_code' => $roleCode],
            ['role_name' => $roleCode, 'is_active' => true],
        );
        $user = User::query()->create([
            'user_name' => 'Vis '.$roleCode,
            'email' => $roleCode.'-vis-'.uniqid('', true).'@example.test',
            'password' => 'secret',
            'is_active' => true,
        ]);
        $user->roles()->attach($role->role_id);

        return $user->fresh(['roles']);
    }
}
