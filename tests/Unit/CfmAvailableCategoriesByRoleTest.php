<?php

namespace Tests\Unit;

use App\Models\CfmCategory;
use App\Models\Role;
use App\Models\User;
use App\Services\CfmService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CfmAvailableCategoriesByRoleTest extends TestCase
{
    use RefreshDatabase;

    private function role(string $code): Role
    {
        return Role::query()->firstOrCreate(
            ['role_code' => $code],
            ['role_name' => $code, 'is_active' => true],
        );
    }

    private function userWithRole(string $roleCode): User
    {
        $user = User::query()->create([
            'user_name' => 'CFM '.$roleCode,
            'email' => $roleCode.'-cfm@example.test',
            'password' => 'secret',
            'is_active' => true,
        ]);
        $user->roles()->attach($this->role($roleCode)->role_id);

        return $user->fresh(['roles']);
    }

    private function seedCats(): void
    {
        foreach ([
            'HeadHunter',
            'Аренда Квартиры',
            'Возвраты клиентам',
            'Зарплата Бухгалтера',
            'Зарплата Директора Филиала',
            'Зарплата Менеджера по заказам',
            'Расход на юриста',
        ] as $name) {
            CfmCategory::query()->create([
                'cfm_cat_name' => $name,
                'cfm_cat_group' => 'outflows',
                'cfm_cat_activities' => 'operating',
                'is_visible' => true,
                'is_auto' => false,
                'visible_for_roles' => $name === 'Возвраты клиентам'
                    ? 'developer,branch_head,regional_director,senior_manager,general_director'
                    : null,
            ]);
        }
    }

    public function test_branch_head_sees_same_extra_expenses_as_regional(): void
    {
        $this->seedCats();
        $svc = app(CfmService::class);

        $dir = $this->userWithRole('branch_head');
        $reg = $this->userWithRole('regional_director');
        $sm = $this->userWithRole('senior_manager');

        $dirNames = $svc->getAvailableCategories($dir)->pluck('cfm_cat_name')->all();
        $regNames = $svc->getAvailableCategories($reg)->pluck('cfm_cat_name')->all();
        $smNames = $svc->getAvailableCategories($sm)->pluck('cfm_cat_name')->all();

        foreach ([
            'Аренда Квартиры',
            'Возвраты клиентам',
            'Зарплата Бухгалтера',
            'Зарплата Директора Филиала',
            'Зарплата Менеджера по заказам',
        ] as $must) {
            $this->assertContains($must, $dirNames, "dir missing $must");
            $this->assertContains($must, $regNames, "reg missing $must");
            $this->assertContains($must, $smNames, "sm missing $must");
        }
    }
}
