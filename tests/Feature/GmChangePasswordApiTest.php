<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class GmChangePasswordApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.gm_api.bearer_token' => 'test-gm-token']);
    }

    public function test_change_password_success_clears_must_change_flag(): void
    {
        $user = User::query()->create([
            'user_name' => 'Мастер GM',
            'email' => 'master-gm@example.test',
            'password' => 'old-secret1',
            'is_active' => true,
            'must_change_password' => true,
        ]);

        $response = $this->postJson('/api/v1/gm/users/change-password', [
            'userId' => $user->user_id,
            'currentPassword' => 'old-secret1',
            'newPassword' => 'new-secret9',
        ], [
            'Authorization' => 'Bearer test-gm-token',
        ]);

        $response->assertOk()
            ->assertJson([
                'ok' => true,
                'userId' => $user->user_id,
                'mustChangePassword' => false,
            ]);

        $user->refresh();
        $this->assertFalse($user->must_change_password);
        $this->assertTrue(Hash::check('new-secret9', $user->password));
        $this->assertNotNull($user->password_set_at);
    }

    public function test_change_password_with_wrong_current_returns_401(): void
    {
        $user = User::query()->create([
            'user_name' => 'Мастер GM',
            'email' => 'master-gm@example.test',
            'password' => 'old-secret1',
            'is_active' => true,
        ]);

        $this->postJson('/api/v1/gm/users/change-password', [
            'userId' => $user->user_id,
            'currentPassword' => 'wrong-password',
            'newPassword' => 'new-secret9',
        ], [
            'Authorization' => 'Bearer test-gm-token',
        ])
            ->assertUnauthorized()
            ->assertJson([
                'ok' => false,
                'error' => 'invalid_credentials',
            ]);

        $user->refresh();
        $this->assertTrue(Hash::check('old-secret1', $user->password));
    }

    public function test_change_password_with_weak_new_password_returns_422(): void
    {
        $user = User::query()->create([
            'user_name' => 'Мастер GM',
            'email' => 'master-gm@example.test',
            'password' => 'old-secret1',
            'is_active' => true,
        ]);

        $this->postJson('/api/v1/gm/users/change-password', [
            'userId' => $user->user_id,
            'currentPassword' => 'old-secret1',
            'newPassword' => 'short',
        ], [
            'Authorization' => 'Bearer test-gm-token',
        ])
            ->assertStatus(422)
            ->assertJson([
                'ok' => false,
                'error' => 'validation_error',
                'message' => 'Новый пароль должен содержать минимум 8 символов',
            ]);
    }

    public function test_change_password_rejects_same_as_current(): void
    {
        $user = User::query()->create([
            'user_name' => 'Мастер GM',
            'email' => 'master-gm@example.test',
            'password' => 'old-secret1',
            'is_active' => true,
        ]);

        $this->postJson('/api/v1/gm/users/change-password', [
            'userId' => $user->user_id,
            'currentPassword' => 'old-secret1',
            'newPassword' => 'old-secret1',
        ], [
            'Authorization' => 'Bearer test-gm-token',
        ])
            ->assertStatus(422)
            ->assertJson([
                'ok' => false,
                'error' => 'validation_error',
                'message' => 'Новый пароль не должен совпадать с текущим',
            ]);
    }

    public function test_verify_password_works_with_new_password_after_change(): void
    {
        $user = User::query()->create([
            'user_name' => 'Мастер GM',
            'email' => 'master-gm@example.test',
            'password' => 'old-secret1',
            'is_active' => true,
            'must_change_password' => true,
        ]);

        $this->postJson('/api/v1/gm/users/change-password', [
            'userId' => $user->user_id,
            'currentPassword' => 'old-secret1',
            'newPassword' => 'new-secret9',
        ], [
            'Authorization' => 'Bearer test-gm-token',
        ])->assertOk();

        $this->postJson('/api/v1/gm/users/verify-password', [
            'login' => 'master-gm',
            'password' => 'new-secret9',
        ], [
            'Authorization' => 'Bearer test-gm-token',
        ])
            ->assertOk()
            ->assertJsonPath('user.mustChangePassword', false);

        $this->postJson('/api/v1/gm/users/verify-password', [
            'login' => 'master-gm',
            'password' => 'old-secret1',
        ], [
            'Authorization' => 'Bearer test-gm-token',
        ])
            ->assertUnauthorized()
            ->assertJson(['ok' => false, 'error' => 'invalid_credentials']);
    }

    public function test_password_status_returns_flags(): void
    {
        $user = User::query()->create([
            'user_name' => 'Мастер GM',
            'email' => 'master-gm@example.test',
            'password' => 'old-secret1',
            'is_active' => true,
            'must_change_password' => true,
        ]);

        $this->getJson('/api/v1/gm/users/'.$user->user_id.'/password-status', [
            'Authorization' => 'Bearer test-gm-token',
        ])
            ->assertOk()
            ->assertJson([
                'userId' => $user->user_id,
                'hasPassword' => true,
                'mustChangePassword' => true,
            ]);
    }

    public function test_change_password_requires_bearer(): void
    {
        $this->postJson('/api/v1/gm/users/change-password', [
            'userId' => 1,
            'currentPassword' => 'x',
            'newPassword' => 'new-secret9',
        ])->assertUnauthorized();
    }
}
