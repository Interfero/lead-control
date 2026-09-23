<?php

namespace Tests\Unit;

use App\Models\CfmCategory;
use App\Models\CfmOperation;
use App\Models\City;
use App\Models\Role;
use App\Models\User;
use App\Services\CfmService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CfmReopenAndDocumentsPolicyTest extends TestCase
{
    use RefreshDatabase;

    private CfmService $svc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->svc = app(CfmService::class);
    }

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
            'email' => $roleCode.'-cfm-policy-'.uniqid('', true).'@example.test',
            'password' => 'secret',
            'is_active' => true,
        ]);
        $user->roles()->attach($this->role($roleCode)->role_id);

        return $user->fresh(['roles']);
    }

    private function closedOperation(?\DateTimeInterface $closedAt = null): CfmOperation
    {
        $city = City::query()->create(['city_name' => 'Тестград '.uniqid()]);
        $cat = CfmCategory::query()->create([
            'cfm_cat_name' => 'Возвраты клиентам',
            'cfm_cat_group' => 'outflows',
            'cfm_cat_activities' => 'operating',
            'is_visible' => true,
            'is_auto' => false,
        ]);
        $author = $this->userWithRole('developer');

        $op = new CfmOperation([
            'city_id' => $city->city_id,
            'cfm_cat_id' => $cat->cfm_cat_id,
            'amount_cfm' => 1500,
            'cfm_adds' => 'тест',
            'cfm_created_at' => $closedAt ?? now(),
            'cfm_closed_at' => $closedAt ?? now(),
        ]);
        $op->cfm_created_by = $author->user_id;
        $op->cfm_closed_by = $author->user_id;
        $op->save();

        return $op->fresh();
    }

    public function test_current_month_closed_operation_can_be_reopened_by_gd_and_dev(): void
    {
        $op = $this->closedOperation(now());

        $this->assertTrue($this->svc->isClosedInCurrentMonth($op));
        $this->assertTrue($this->svc->userCanReopen($this->userWithRole('developer'), $op));
        $this->assertTrue($this->svc->userCanReopen($this->userWithRole('general_director'), $op));
        $this->assertFalse($this->svc->userCanReopen($this->userWithRole('branch_head'), $op));
        $this->assertFalse($this->svc->userCanReopen($this->userWithRole('call_center'), $op));
    }

    public function test_previous_month_closed_operation_cannot_be_reopened(): void
    {
        $op = $this->closedOperation(now()->subMonth());

        $this->assertFalse($this->svc->isClosedInCurrentMonth($op));
        $this->assertFalse($this->svc->userCanReopen($this->userWithRole('developer'), $op));
        $this->assertFalse($this->svc->userCanReopen($this->userWithRole('general_director'), $op));

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Переоткрыть можно только операции текущего месяца');
        $this->svc->reopen($op);
    }

    public function test_all_cash_roles_can_attach_documents(): void
    {
        foreach (CfmService::CASH_ACCESS_ROLES as $role) {
            $this->assertTrue(
                $this->svc->userCanAttachDocuments($this->userWithRole($role)),
                $role.' should attach CFM documents'
            );
        }

        $this->assertFalse($this->svc->userCanAttachDocuments($this->userWithRole('master')));
    }

    public function test_documents_cannot_be_deleted_after_close(): void
    {
        $op = $this->closedOperation(now());
        $this->assertFalse($this->svc->userCanDeleteDocuments($this->userWithRole('developer'), $op));
        $this->assertFalse($this->svc->userCanDeleteDocuments($this->userWithRole('branch_head'), $op));
    }

    public function test_open_refund_updates_cash_and_master_split(): void
    {
        $op = $this->closedOperation(now());
        $this->svc->reopen($op);
        $op = $op->fresh();

        $updated = $this->svc->update($op, [
            'amount_cfm' => 900,
            'amount_from_master' => 600,
            'cfm_adds' => $op->cfm_adds,
            'city_id' => $op->city_id,
        ]);

        $this->assertSame(900, (int) $updated->amount_cfm);
        $this->assertSame(600, (int) $updated->amount_from_master);
        $this->assertStringContainsString('С кассы: 900', str_replace("\u{00A0}", ' ', $updated->cfm_adds));
        $this->assertStringContainsString('С мастера: 600', str_replace("\u{00A0}", ' ', $updated->cfm_adds));
    }
}
