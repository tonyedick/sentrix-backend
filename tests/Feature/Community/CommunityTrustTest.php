<?php

declare(strict_types=1);

namespace Tests\Feature\Community;

use App\Domains\Authorization\Database\Seeders\PermissionCatalogueSeeder;
use App\Domains\Authorization\Services\RoleService;
use App\Domains\Community\Models\CommunityAlert;
use App\Domains\Community\Support\Enums\AlertStatus;
use App\Domains\Places\Database\Seeders\PlacesSeeder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Trust-weighted verification (verify / dispute / resolve), the safe-places
 * directory, and SuperAdmin-gated official publishing. Geo queries require
 * PostGIS (the project's test DB). Crowd confirm/dismiss tallies are already
 * covered by CommunityAlertTest — not re-asserted here.
 */
final class CommunityTrustTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionCatalogueSeeder::class);
    }

    private function superAdmin(): User
    {
        $admin = User::factory()->create();
        app(RoleService::class)->assignSuperAdmin($admin);

        return $admin;
    }

    private function reportAlert(User $user, array $overrides = []): string
    {
        return $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/me/alerts', array_merge([
                'category' => 'security',
                'title' => 'Break-in reported on Marina Road',
                'lat' => 6.5244,
                'lng' => 3.3792,
            ], $overrides))
            ->assertCreated()
            ->json('data.id');
    }

    public function test_verify_raises_confidence_and_promotes_unverified_alert(): void
    {
        $reporter = User::factory()->create();
        // An unverified, pending alert (e.g. AI-suggested) awaiting crowd trust.
        $alert = CommunityAlert::create([
            'reporter_id' => $reporter->getKey(),
            'category' => 'security',
            'title' => 'Possible disturbance',
            'impact' => 'moderate',
            'status' => AlertStatus::Unverified->value,
            'source' => 'community',
            'lat' => 6.5244,
            'lng' => 3.3792,
            'expires_at' => now()->addHours(6),
        ]);

        // Three KYC-verified members confirm (weight 2 each = confidence 6 >= 3).
        foreach (range(1, 3) as $i) {
            $voter = User::factory()->create(['email_verified_at' => now()]);
            $this->actingAs($voter, 'sanctum')
                ->postJson("/api/v1/me/alerts/{$alert->id}/verify")
                ->assertOk();
        }

        $fresh = CommunityAlert::findOrFail($alert->id);
        $this->assertSame('active', $fresh->status->value);
        $this->assertGreaterThanOrEqual(3, $fresh->confidence);
        $this->assertIsInt($fresh->confidence);
    }

    public function test_dispute_resolves_an_alert_past_the_threshold(): void
    {
        $reporter = User::factory()->create();
        $alertId = $this->reportAlert($reporter); // starts active

        // Two verified disputers (weight 2 each = -4 <= -2) drop it.
        foreach (range(1, 2) as $i) {
            $disputer = User::factory()->create(['email_verified_at' => now()]);
            $this->actingAs($disputer, 'sanctum')
                ->postJson("/api/v1/me/alerts/{$alertId}/dispute")
                ->assertOk();
        }

        $this->assertSame('resolved', CommunityAlert::findOrFail($alertId)->status->value);
    }

    public function test_one_vote_per_user_is_idempotent(): void
    {
        $reporter = User::factory()->create();
        $alertId = $this->reportAlert($reporter);

        $voter = User::factory()->create(['email_verified_at' => now()]);
        $this->actingAs($voter, 'sanctum')->postJson("/api/v1/me/alerts/{$alertId}/verify")->assertOk();
        $this->actingAs($voter, 'sanctum')->postJson("/api/v1/me/alerts/{$alertId}/verify")->assertOk();

        $this->assertSame(1, CommunityAlert::findOrFail($alertId)->confirmations_count);
    }

    public function test_citizen_can_resolve_own_community_alert(): void
    {
        $reporter = User::factory()->create();
        $alertId = $this->reportAlert($reporter);

        $this->actingAs($reporter, 'sanctum')
            ->postJson("/api/v1/me/alerts/{$alertId}/resolve")
            ->assertOk()
            ->assertJsonPath('data.status', 'resolved');
    }

    public function test_safe_places_returns_nearby_safe_locations(): void
    {
        $this->seed(PlacesSeeder::class);
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/me/alerts/safe-places?lat=6.4281&lng=3.4219&radius=5000')
            ->assertOk()
            ->assertJsonPath('data.0.category', 'police');
    }

    public function test_superadmin_publishes_official_verified_alert(): void
    {
        $admin = $this->superAdmin();

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/v1/me/alerts/publish', [
                'source' => 'official',
                'category' => 'security',
                'title' => 'Police checkpoint on Third Mainland Bridge',
                'impact' => 'high',
                'lat' => 6.5244,
                'lng' => 3.3792,
            ])
            ->assertCreated()
            ->assertJsonPath('data.source', 'official')
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.verified', true);
    }

    public function test_non_superadmin_cannot_publish(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/me/alerts/publish', [
                'source' => 'official',
                'category' => 'security',
                'title' => 'Fake official alert',
                'lat' => 6.5244,
                'lng' => 3.3792,
            ])
            ->assertForbidden();
    }
}
