<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class GmUpdateInnApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.gm_api.bearer_token' => 'test-gm-token']);
    }

    public function test_master_with_empty_inn_can_save_ten_digits(): void
    {
        $user = User::query()->create([
            'user_name' => 'Торин GM',
            'email' => 'torin-gm@example.test',
            'password' => 'secret1234',
            'is_active' => true,
            'user_inn' => null,
        ]);

        $this->getJson('/api/v1/gm/users/me', $this->gmHeaders($user))
            ->assertOk()
            ->assertJsonPath('inn', null);

        $this->patchJson('/api/v1/gm/users/me/inn', [
            'inn' => '1234567890',
        ], $this->gmHeaders($user))
            ->assertOk()
            ->assertJson([
                'ok' => true,
                'userId' => $user->user_id,
                'inn' => '1234567890',
            ]);

        $user->refresh();
        $this->assertSame('1234567890', $user->user_inn);

        $this->getJson('/api/v1/gm/users/me', $this->gmHeaders($user))
            ->assertOk()
            ->assertJsonPath('inn', '1234567890');
    }

    public function test_save_twelve_digit_inn(): void
    {
        $user = User::query()->create([
            'user_name' => 'Мастер 12',
            'email' => 'master12@example.test',
            'password' => 'secret1234',
            'is_active' => true,
        ]);

        $this->patchJson('/api/v1/gm/users/me/inn', [
            'inn' => '123456789012',
        ], $this->gmHeaders($user))
            ->assertOk()
            ->assertJsonPath('inn', '123456789012');
    }

    public function test_same_inn_is_idempotent(): void
    {
        $user = User::query()->create([
            'user_name' => 'Идемпотент',
            'email' => 'idem@example.test',
            'password' => 'secret1234',
            'is_active' => true,
            'user_inn' => '7707083893',
        ]);

        $this->patchJson('/api/v1/gm/users/me/inn', [
            'inn' => '7707083893',
        ], $this->gmHeaders($user))
            ->assertOk()
            ->assertJsonPath('inn', '7707083893');
    }

    #[DataProvider('invalidInnProvider')]
    public function test_invalid_inn_returns_422(string $inn): void
    {
        $user = User::query()->create([
            'user_name' => 'Валидация',
            'email' => 'validate@example.test',
            'password' => 'secret1234',
            'is_active' => true,
        ]);

        $this->patchJson('/api/v1/gm/users/me/inn', [
            'inn' => $inn,
        ], $this->gmHeaders($user))
            ->assertStatus(422)
            ->assertJson([
                'error' => 'invalid_inn',
                'message' => 'ИНН должен содержать 10 или 12 цифр',
            ]);

        $user->refresh();
        $this->assertNull($user->user_inn);
    }

    public static function invalidInnProvider(): array
    {
        return [
            'too short' => ['123'],
            'with spaces' => ['12 34567890'],
            'letters' => ['abcdefghij'],
            'empty' => [''],
        ];
    }

    public function test_missing_inn_field_returns_422(): void
    {
        $user = User::query()->create([
            'user_name' => 'Без поля',
            'email' => 'no-field@example.test',
            'password' => 'secret1234',
            'is_active' => true,
        ]);

        $this->patchJson('/api/v1/gm/users/me/inn', [], $this->gmHeaders($user))
            ->assertStatus(422)
            ->assertJsonPath('error', 'invalid_inn');
    }

    public function test_body_user_id_is_ignored(): void
    {
        $user = User::query()->create([
            'user_name' => 'Свой',
            'email' => 'self@example.test',
            'password' => 'secret1234',
            'is_active' => true,
        ]);

        $other = User::query()->create([
            'user_name' => 'Чужой',
            'email' => 'other@example.test',
            'password' => 'secret1234',
            'is_active' => true,
        ]);

        $this->patchJson('/api/v1/gm/users/me/inn', [
            'inn' => '1234567890',
            'userId' => $other->user_id,
        ], $this->gmHeaders($user))
            ->assertOk()
            ->assertJsonPath('userId', $user->user_id);

        $other->refresh();
        $this->assertNull($other->user_inn);
    }

    public function test_requires_bearer_token(): void
    {
        $user = User::query()->create([
            'user_name' => 'Без токена',
            'email' => 'no-token@example.test',
            'password' => 'secret1234',
            'is_active' => true,
        ]);

        $this->patchJson('/api/v1/gm/users/me/inn', [
            'inn' => '1234567890',
        ], [
            'X-GM-User-Id' => (string) $user->user_id,
        ])->assertUnauthorized();
    }

    public function test_missing_identity_returns_404(): void
    {
        $this->patchJson('/api/v1/gm/users/me/inn', [
            'inn' => '1234567890',
        ], [
            'Authorization' => 'Bearer test-gm-token',
        ])
            ->assertNotFound()
            ->assertJson(['error' => 'user_not_found']);
    }

    public function test_unknown_user_returns_404(): void
    {
        $this->patchJson('/api/v1/gm/users/me/inn', [
            'inn' => '1234567890',
        ], [
            'Authorization' => 'Bearer test-gm-token',
            'X-GM-User-Id' => '999999',
        ])
            ->assertNotFound()
            ->assertJson(['error' => 'user_not_found']);
    }

    /**
     * @return array<string, string>
     */
    private function gmHeaders(User $user): array
    {
        return [
            'Authorization' => 'Bearer test-gm-token',
            'X-GM-User-Id' => (string) $user->user_id,
            'X-GM-Email' => (string) $user->email,
            'X-GM-Login' => explode('@', (string) $user->email, 2)[0],
        ];
    }
}
