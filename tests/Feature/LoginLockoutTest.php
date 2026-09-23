<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class LoginLockoutTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('users');
        Schema::create('users', function (Blueprint $table) {
            $table->id('user_id');
            $table->string('user_name')->nullable();
            $table->string('email')->unique();
            $table->string('password');
            $table->boolean('is_active')->default(true);
            $table->rememberToken();
            $table->timestamps();
        });
    }

    public function test_lockout_after_five_failed_attempts(): void
    {
        $email = 'lockout-test@example.com';

        for ($i = 0; $i < 5; $i++) {
            $response = $this->from('/login')->post('/login', [
                'email' => $email,
                'password' => 'wrong-password',
            ]);
            $response->assertSessionHasErrors('email');
        }

        $locked = $this->from('/login')->post('/login', [
            'email' => $email,
            'password' => 'wrong-password',
        ]);

        $locked->assertSessionHasErrors('email');
        $this->assertStringContainsString(
            'Слишком много неудачных попыток',
            collect(session('errors')->get('email'))->first() ?? ''
        );
    }
}
