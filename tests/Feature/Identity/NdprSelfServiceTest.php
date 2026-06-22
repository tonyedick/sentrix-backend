<?php

declare(strict_types=1);

namespace Tests\Feature\Identity;

use App\Domains\Identity\Models\SafetyContact;
use App\Domains\Identity\Models\SavedLocation;
use App\Domains\Organization\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * NDPR/GDPR consumer self-service: change-password, right-of-access export, and
 * right-to-erasure account deletion (with the org-owner guard).
 */
final class NdprSelfServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_change_password_rejects_a_wrong_current_password(): void
    {
        $user = User::factory()->create(['password' => Hash::make('current-secret')]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/auth/change-password', [
                'current_password' => 'wrong-secret',
                'new_password' => 'a-brand-new-secret',
                'new_password_confirmation' => 'a-brand-new-secret',
            ])
            ->assertStatus(422)
            ->assertJsonPath('success', false);

        // Password unchanged.
        $this->assertTrue(Hash::check('current-secret', $user->fresh()->password));
    }

    public function test_change_password_succeeds_and_revokes_other_tokens(): void
    {
        $user = User::factory()->create(['password' => Hash::make('current-secret')]);

        // A token from another device that should be revoked by the change.
        $user->createToken('other-device');
        $this->assertSame(1, $user->tokens()->count());

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/auth/change-password', [
                'current_password' => 'current-secret',
                'new_password' => 'a-brand-new-secret',
                'new_password_confirmation' => 'a-brand-new-secret',
            ])
            ->assertOk()
            ->assertJsonPath('data.changed', true);

        $this->assertTrue(Hash::check('a-brand-new-secret', $user->fresh()->password));

        // actingAs(sanctum) uses a transient guard token (not a DB row), so the
        // only persisted token — the other device — is revoked.
        $this->assertSame(0, $user->fresh()->tokens()->count());
    }

    public function test_export_returns_profile_and_related_collections(): void
    {
        $user = User::factory()->create(['email' => 'rider@example.com']);

        SafetyContact::create([
            'user_id' => $user->getKey(),
            'name' => 'Mum',
            'phone' => '+2348000000001',
            'is_primary' => true,
        ]);

        SavedLocation::create([
            'user_id' => $user->getKey(),
            'label' => 'Home',
            'kind' => 'home',
            'address' => 'Lekki',
            'lat' => 6.4474,
            'lng' => 3.4709,
        ]);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/auth/me/export')
            ->assertOk()
            ->assertJsonPath('data.exported_for', 'rider@example.com')
            ->assertJsonPath('data.profile.email', 'rider@example.com')
            ->assertJsonCount(1, 'data.safety_contacts')
            ->assertJsonPath('data.safety_contacts.0.name', 'Mum')
            ->assertJsonCount(1, 'data.emergency_contacts')
            ->assertJsonCount(1, 'data.saved_locations')
            ->assertJsonPath('data.saved_locations.0.label', 'Home')
            ->assertJsonCount(0, 'data.recent_searches');
    }

    public function test_delete_me_erases_a_plain_consumer_account(): void
    {
        $user = User::factory()->create();
        $user->createToken('device');

        $this->actingAs($user, 'sanctum')
            ->deleteJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('data.deleted', true);

        // Hard-deleted (forceDelete) — not merely soft-deleted.
        $this->assertDatabaseMissing('users', ['id' => $user->getKey()]);
        $this->assertSame(0, $user->tokens()->count());
    }

    public function test_delete_me_is_refused_for_an_organization_owner(): void
    {
        $owner = User::factory()->create();

        Organization::create([
            'name' => 'Acme Security',
            'slug' => 'acme-security',
            'owner_id' => $owner->getKey(),
        ]);

        $this->actingAs($owner, 'sanctum')
            ->deleteJson('/api/v1/auth/me')
            ->assertStatus(422)
            ->assertJsonPath('success', false);

        // The account (and tenant) survive.
        $this->assertDatabaseHas('users', ['id' => $owner->getKey()]);
    }
}
