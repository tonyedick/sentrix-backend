<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

final class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_user_can_register_and_receive_a_token(): void
    {
        Queue::fake(); // default-organization provisioning is queued

        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Ada Lovelace',
            'email' => 'ada@example.com',
            'password' => 'password-123',
            'password_confirmation' => 'password-123',
            'device_name' => 'iphone-15',
        ]);

        $response->assertCreated()
            ->assertJsonStructure(['success', 'message', 'data' => ['user' => ['id', 'email'], 'token']]);

        $this->assertDatabaseHas('users', ['email' => 'ada@example.com']);
    }

    public function test_a_user_can_log_in_with_a_device_to_get_a_token(): void
    {
        User::factory()->create([
            'email' => 'grace@example.com',
            'password' => 'password-123',
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'grace@example.com',
            'password' => 'password-123',
            'device_name' => 'pixel-9',
        ]);

        $response->assertOk()->assertJsonStructure(['success', 'message', 'data' => ['user', 'token']]);
    }

    public function test_invalid_credentials_are_rejected(): void
    {
        User::factory()->create(['email' => 'grace@example.com', 'password' => 'password-123']);

        $this->postJson('/api/v1/auth/login', [
            'email' => 'grace@example.com',
            'password' => 'wrong',
            'device_name' => 'pixel-9',
        ])->assertUnprocessable()->assertJsonValidationErrors('email');
    }

    public function test_authenticated_user_can_fetch_themselves(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('data.id', $user->id);
    }
}
