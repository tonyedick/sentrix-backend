<?php

declare(strict_types=1);

namespace Tests\Feature\Identity;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class MeProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_me_is_rejected(): void
    {
        $this->getJson('/api/v1/me')->assertUnauthorized();
    }

    public function test_me_returns_the_authenticated_user(): void
    {
        $user = User::factory()->create(['email' => 'rider@example.com']);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/me')
            ->assertOk()
            ->assertJsonPath('data.id', $user->getKey())
            ->assertJsonPath('data.email', 'rider@example.com');
    }

    public function test_profile_update_changes_name_and_resets_phone_verification(): void
    {
        $user = User::factory()->create([
            'phone' => '+15550000000',
            'phone_verified_at' => now(),
        ]);

        $this->actingAs($user, 'sanctum')
            ->patchJson('/api/v1/me', ['name' => 'New Name', 'phone' => '+15551112222'])
            ->assertOk()
            ->assertJsonPath('data.name', 'New Name')
            ->assertJsonPath('data.phone', '+15551112222')
            ->assertJsonPath('data.phone_verified', false);

        $this->assertNull($user->fresh()->phone_verified_at);
    }

    public function test_push_token_can_be_registered_and_removed(): void
    {
        $user = User::factory()->create(['push_tokens' => []]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/me/devices/push-token', ['token' => 'device-token-abc', 'platform' => 'ios'])
            ->assertCreated();

        $this->assertContains('device-token-abc', $user->fresh()->push_tokens);

        // Idempotent: registering the same token again does not duplicate it.
        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/me/devices/push-token', ['token' => 'device-token-abc'])
            ->assertCreated();
        $this->assertCount(1, $user->fresh()->push_tokens);

        $this->actingAs($user, 'sanctum')
            ->deleteJson('/api/v1/me/devices/push-token', ['token' => 'device-token-abc'])
            ->assertNoContent();

        $this->assertNotContains('device-token-abc', $user->fresh()->push_tokens ?? []);
    }

    public function test_preferences_are_saved_and_merged(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->patchJson('/api/v1/me', ['preferences' => ['travel_mode' => 'driving', 'home_city' => 'Lagos']])
            ->assertOk()
            ->assertJsonPath('data.preferences.travel_mode', 'driving')
            ->assertJsonPath('data.preferences.home_city', 'Lagos');

        // A later partial update merges, not overwrites.
        $this->actingAs($user, 'sanctum')
            ->patchJson('/api/v1/me', ['preferences' => ['add_vehicle_later' => true]])
            ->assertOk()
            ->assertJsonPath('data.preferences.travel_mode', 'driving')
            ->assertJsonPath('data.preferences.add_vehicle_later', true);
    }
}
