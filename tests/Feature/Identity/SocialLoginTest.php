<?php

declare(strict_types=1);

namespace Tests\Feature\Identity;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Social sign-in via the stub verifier (the dev/test driver trusts the token).
 */
final class SocialLoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_social_login_provisions_user_and_returns_token(): void
    {
        $this->postJson('/api/v1/auth/social', [
            'provider' => 'google',
            'id_token' => 'amara@example.com',
            'device_name' => 'iphone',
        ])
            ->assertOk()
            ->assertJsonPath('data.user.email', 'amara@example.com')
            ->assertJsonPath('data.user.email_verified', true);

        $this->assertDatabaseHas('users', ['email' => 'amara@example.com']);
        $this->assertDatabaseHas('social_accounts', ['provider' => 'google']);
    }

    public function test_returning_social_login_resolves_same_user(): void
    {
        $first = $this->postJson('/api/v1/auth/social', ['provider' => 'google', 'id_token' => 'amara@example.com'])
            ->assertOk()->json('data.user.id');

        $second = $this->postJson('/api/v1/auth/social', ['provider' => 'google', 'id_token' => 'amara@example.com'])
            ->assertOk()->json('data.user.id');

        $this->assertSame($first, $second);
        $this->assertSame(1, User::query()->where('email', 'amara@example.com')->count());
    }

    public function test_social_signup_creates_no_personal_org(): void
    {
        $userId = $this->postJson('/api/v1/auth/social', ['provider' => 'apple', 'id_token' => 'rider@example.com'])
            ->assertOk()->json('data.user.id');

        $this->assertFalse(User::findOrFail($userId)->organizations()->exists());
    }
}
