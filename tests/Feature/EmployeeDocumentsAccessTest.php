<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Документы сотрудников (паспорт, договор): кто может открыть и скачать. */
class EmployeeDocumentsAccessTest extends TestCase
{
    use RefreshDatabase;

    private function userWithRole(string $roleCode): User
    {
        $role = Role::query()->firstOrCreate(['role_code' => $roleCode], ['role_name' => $roleCode, 'is_active' => true]);
        $user = User::query()->create([
            'user_name' => 'Doc '.$roleCode,
            'email' => $roleCode.'-doc-'.uniqid('', true).'@example.test',
            'password' => 'secret',
            'is_active' => true,
        ]);
        $user->roles()->attach($role->role_id);

        return $user->fresh(['roles']);
    }

    private function employeeDocument(): Document
    {
        Storage::fake('local');
        $employee = $this->userWithRole('master');   // без городов: у руководителей филиала общих городов с ним нет
        Storage::disk('local')->put('documents/hr/passport.pdf', 'pdf-bytes');

        $doc = new Document([
            'documentable_type' => User::class,
            'documentable_id' => $employee->user_id,
            'document_category' => 'passport',
            'file_name' => 'passport.pdf',
            'file_path' => 'documents/hr/passport.pdf',
            'file_mime' => 'application/pdf',
            'file_size' => 9,
        ]);
        $doc->uploaded_by = $employee->user_id;
        $doc->save();

        return $doc;
    }

    public function test_general_director_can_view_and_download_any_employee_document(): void
    {
        $doc = $this->employeeDocument();
        $gd = $this->userWithRole('general_director');

        $this->actingAs($gd)->get('/crm/documents/'.$doc->document_id)->assertOk();
        $this->actingAs($gd)->get('/crm/documents/'.$doc->document_id.'/download')->assertOk();
    }

    public function test_branch_head_without_shared_city_is_still_denied(): void
    {
        $doc = $this->employeeDocument();
        $head = $this->userWithRole('branch_head');

        $this->actingAs($head)->get('/crm/documents/'.$doc->document_id)->assertForbidden();
        $this->actingAs($head)->get('/crm/documents/'.$doc->document_id.'/download')->assertForbidden();
    }

    public function test_other_roles_are_denied(): void
    {
        $doc = $this->employeeDocument();
        $dispatcher = $this->userWithRole('call_center');

        $this->actingAs($dispatcher)->get('/crm/documents/'.$doc->document_id)->assertForbidden();
        $this->actingAs($dispatcher)->get('/crm/documents/'.$doc->document_id.'/download')->assertForbidden();
    }
}
