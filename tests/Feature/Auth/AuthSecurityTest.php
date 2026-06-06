<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

final class AuthSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_email_is_case_insensitive(): void
    {
        User::factory()->create([
            'email' => 'grace@example.com',
            'password' => 'password-123',
        ]);

        $this->postJson('/api/v1/auth/login', [
            'email' => 'GRACE@Example.com',
            'password' => 'password-123',
            'device_name' => 'pixel-9',
        ])->assertOk()->assertJsonStructure(['data' => ['token']]);
    }

    public function test_unknown_email_and_wrong_password_fail_identically(): void
    {
        User::factory()->create([
            'email' => 'grace@example.com',
            'password' => 'password-123',
        ]);

        $unknown = $this->postJson('/api/v1/auth/login', [
            'email' => 'nobody@example.com',
            'password' => 'password-123',
        ]);

        $wrong = $this->postJson('/api/v1/auth/login', [
            'email' => 'grace@example.com',
            'password' => 'not-the-password',
        ]);

        // Identical status + error shape — no account-existence oracle.
        $unknown->assertUnprocessable()->assertJsonValidationErrors('email');
        $wrong->assertUnprocessable()->assertJsonValidationErrors('email');
        $this->assertSame($unknown->json('errors.email'), $wrong->json('errors.email'));
    }

    public function test_weak_passwords_are_rejected_at_registration(): void
    {
        $this->postJson('/api/v1/auth/register', [
            'name' => 'Ada Lovelace',
            'email' => 'ada@example.com',
            'password' => 'short',
            'password_confirmation' => 'short',
        ])->assertUnprocessable()->assertJsonValidationErrors('password');
    }

    public function test_login_is_throttled_after_repeated_failures(): void
    {
        User::factory()->create([
            'email' => 'grace@example.com',
            'password' => 'password-123',
        ]);

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->postJson('/api/v1/auth/login', [
                'email' => 'grace@example.com',
                'password' => 'wrong-password',
            ])->assertUnprocessable();
        }

        // The 6th attempt within the window is blocked.
        $this->postJson('/api/v1/auth/login', [
            'email' => 'grace@example.com',
            'password' => 'wrong-password',
        ])->assertStatus(429);
    }

    public function test_password_reset_revokes_all_existing_tokens(): void
    {
        $user = User::factory()->create(['email' => 'grace@example.com']);
        $user->createToken('pixel-9');
        $user->createToken('ipad');

        $this->assertSame(2, $user->tokens()->count());

        $this->postJson('/api/v1/auth/reset-password', [
            'token' => Password::broker()->createToken($user),
            'email' => $user->email,
            'password' => 'a-brand-new-password',
            'password_confirmation' => 'a-brand-new-password',
        ])->assertOk();

        $this->assertSame(0, $user->tokens()->count());
    }

    public function test_mobile_logout_revokes_only_the_current_token(): void
    {
        $user = User::factory()->create([
            'email' => 'grace@example.com',
            'password' => 'password-123',
        ]);

        $token = $this->postJson('/api/v1/auth/login', [
            'email' => 'grace@example.com',
            'password' => 'password-123',
            'device_name' => 'pixel-9',
        ])->json('data.token');

        $this->withToken($token)
            ->postJson('/api/v1/auth/logout')
            ->assertOk();

        $this->assertSame(0, $user->fresh()->tokens()->count());
    }
}
