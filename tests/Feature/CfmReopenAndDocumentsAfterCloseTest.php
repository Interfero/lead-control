<?php

namespace Tests\Feature;

use App\Models\CfmCategory;
use App\Models\CfmOperation;
use App\Models\City;
use App\Models\Document;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CfmReopenAndDocumentsAfterCloseTest extends TestCase
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
            'user_name' => 'CFM HTTP '.$roleCode,
            'email' => $roleCode.'-cfm-http-'.uniqid('', true).'@example.test',
            'password' => 'secret',
            'is_active' => true,
        ]);
        $user->roles()->attach($this->role($roleCode)->role_id);

        return $user->fresh(['roles']);
    }

    private function closedOperation(?\DateTimeInterface $closedAt = null): CfmOperation
    {
        $city = City::query()->create([
            'city_name' => 'НН '.uniqid(),
            'city_timezone' => 'Europe/Moscow',
        ]);
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
            'cfm_adds' => 'Возврат клиенту',
            'cfm_created_at' => $closedAt ?? now(),
            'cfm_closed_at' => $closedAt ?? now(),
        ]);
        $op->cfm_created_by = $author->user_id;
        $op->cfm_closed_by = $author->user_id;
        $op->save();

        return $op->fresh(['category', 'city', 'createdBy', 'closedBy', 'documents']);
    }

    public function test_gd_can_reopen_current_month_operation(): void
    {
        $op = $this->closedOperation(now());
        $gd = $this->userWithRole('general_director');

        $this->actingAs($gd)
            ->get(route('cfm.show', $op->cfm_id))
            ->assertOk()
            ->assertSee('Переоткрыть')
            ->assertDontSee('Добавление документов недоступно');

        $this->actingAs($gd)
            ->from(route('cfm.show', $op->cfm_id))
            ->post(route('cfm.reopen', $op->cfm_id))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertNull($op->fresh()->cfm_closed_at);
    }

    public function test_gd_cannot_reopen_previous_month_operation(): void
    {
        $op = $this->closedOperation(now()->subMonth());
        $gd = $this->userWithRole('general_director');

        $this->actingAs($gd)
            ->get(route('cfm.show', $op->cfm_id))
            ->assertOk()
            ->assertDontSee('Переоткрыть');

        $this->actingAs($gd)
            ->from(route('cfm.show', $op->cfm_id))
            ->post(route('cfm.reopen', $op->cfm_id))
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertNotNull($op->fresh()->cfm_closed_at);
    }

    public function test_branch_head_cannot_reopen(): void
    {
        $op = $this->closedOperation(now());
        $head = $this->userWithRole('branch_head');
        $head->cities()->attach($op->city_id);

        $this->actingAs($head)
            ->get(route('cfm.show', $op->cfm_id))
            ->assertOk()
            ->assertDontSee('Переоткрыть');

        $this->actingAs($head)
            ->post(route('cfm.reopen', $op->cfm_id))
            ->assertForbidden();
    }

    public function test_cash_roles_can_upload_document_after_close(): void
    {
        Storage::fake('local');
        $op = $this->closedOperation(now());

        foreach (['general_director', 'branch_head', 'call_center'] as $role) {
            $user = $this->userWithRole($role);
            if ($role === 'branch_head') {
                $user->cities()->attach($op->city_id);
            }

            $this->actingAs($user)
                ->post('/crm/cfm/'.$op->cfm_id.'/documents', [
                    'file' => UploadedFile::fake()->create('check-'.$role.'.pdf', 80, 'application/pdf'),
                ], ['Accept' => 'application/json'])
                ->assertOk()
                ->assertJson(['success' => true]);
        }

        $this->assertSame(3, Document::query()
            ->where('documentable_type', CfmOperation::class)
            ->where('documentable_id', $op->cfm_id)
            ->count());
    }

    public function test_cannot_delete_document_after_close(): void
    {
        Storage::fake('local');
        $op = $this->closedOperation(now());
        $dev = $this->userWithRole('developer');

        $this->actingAs($dev)
            ->post('/crm/cfm/'.$op->cfm_id.'/documents', [
                'file' => UploadedFile::fake()->create('keep.pdf', 80, 'application/pdf'),
            ], ['Accept' => 'application/json'])
            ->assertOk();

        $doc = Document::query()
            ->where('documentable_type', CfmOperation::class)
            ->where('documentable_id', $op->cfm_id)
            ->firstOrFail();

        $this->actingAs($dev)
            ->delete('/crm/documents/'.$doc->document_id, [], ['Accept' => 'application/json'])
            ->assertForbidden()
            ->assertJsonFragment(['message' => 'Нельзя удалять документы из проведённой операции']);

        $this->assertNotNull($doc->fresh());
    }

    public function test_gd_can_edit_refund_split_after_reopen(): void
    {
        $op = $this->closedOperation(now());
        $op->amount_from_master = 0;
        $op->cfm_adds = "Возврат клиенту\nЗаказ №1\nИтого возврат: 1 500 ₽\nС кассы: 1 500 ₽\nС мастера: 0 ₽";
        $op->save();

        $gd = $this->userWithRole('general_director');
        $this->actingAs($gd)->post(route('cfm.reopen', $op->cfm_id))->assertRedirect();

        $this->actingAs($gd)
            ->get(route('cfm.show', $op->cfm_id))
            ->assertOk()
            ->assertSee('С кассы')
            ->assertSee('С мастера')
            ->assertSee('Сохранить');

        $this->actingAs($gd)
            ->from(route('cfm.show', $op->cfm_id))
            ->put(route('cfm.update', $op->cfm_id), [
                'amount_from_cash' => 900,
                'amount_from_master' => 600,
                'cfm_adds' => $op->fresh()->cfm_adds,
                'city_id' => $op->city_id,
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $fresh = $op->fresh();
        $this->assertSame(900, (int) $fresh->amount_cfm);
        $this->assertSame(600, (int) $fresh->amount_from_master);
    }
}
