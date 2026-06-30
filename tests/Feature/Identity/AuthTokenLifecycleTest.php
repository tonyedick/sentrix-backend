<?php

declare(strict_types=1);

namespace Tests\Feature\Identity;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class AuthTokenLifecycleTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Log in with a device name and return the unwrapped `data` payload.
     *
     * @return array<string, mixed>
     */
    private function login(string $email = 'op@example.com'): array
    {
        User::factory()->create(['email' => $email, 'password' => 'password-123']);

        return $this->postJson('/api/v1/auth/login', [
            'email' => $email,
            'password' => 'password-123',
            'device_name' => 'dashboard',
        ])->assertOk()->json('data');
    }

    public function test_login_issues_an_access_and_refresh_token_pair(): void
    {
        $data = $this->login();

        $this->assertArrayHasKey('token', $data);
        $this->assertArrayHasKey('access_token', $data);
        $this->assertArrayHasKey('refresh_token', $data);
        $this->assertArrayHasKey('expires_at', $data);

        // `token` stays an alias of the access token (backward compatibility).
        $this->assertSame($data['token'], $data['access_token']);
        $this->assertNotSame($data['access_token'], $data['refresh_token']);
    }

    public function test_access_token_authenticates_protected_routes(): void
    {
        $data = $this->login();

        $this->withToken($data['access_token'])
            ->getJson('/api/v1/auth/me')
            ->assertOk();
    }

    public function test_refresh_token_rotates_into_a_fresh_pair(): void
    {
        $data = $this->login();
        $oldRefresh = $data['refresh_token'];
        // Sanctum plaintext tokens are "<id>|<secret>"; the id lets us assert the
        // row is gone independently of the auth guard.
        $oldRefreshId = (int) explode('|', $oldRefresh, 2)[0];

        $rotated = $this->withToken($oldRefresh)
            ->postJson('/api/v1/auth/refresh')
            ->assertOk()
            ->assertJsonStructure(['data' => ['token', 'access_token', 'refresh_token', 'expires_at']])
            ->json('data');

        $this->assertNotSame($oldRefresh, $rotated['refresh_token']);

        // Rotation: the used refresh token is deleted from the database entirely.
        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $oldRefreshId]);

        // The new access token works. Reset the guard first so the bearer token is
        // re-validated rather than reusing the user cached from the call above
        // (feature tests share one app instance, so Sanctum caches the resolution).
        $this->app['auth']->forgetGuards();
        $this->withToken($rotated['access_token'])->getJson('/api/v1/auth/me')->assertOk();

        // The rotated-out refresh token no longer authenticates.
        $this->app['auth']->forgetGuards();
        $this->withToken($oldRefresh)->postJson('/api/v1/auth/refresh')->assertUnauthorized();
    }

    public function test_access_token_cannot_be_exchanged_at_refresh(): void
    {
        $data = $this->login();

        $this->withToken($data['access_token'])
            ->postJson('/api/v1/auth/refresh')
            ->assertForbidden();
    }
}
