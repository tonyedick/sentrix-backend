<?php

declare(strict_types=1);

namespace Tests\Feature\Tracking;

use App\Domains\Authorization\Database\Seeders\PermissionCatalogueSeeder;
use App\Domains\Authorization\Support\Enums\OrganizationRole;
use App\Domains\Emergency\Models\Emergency;
use App\Domains\Organization\Models\Organization;
use App\Domains\Organization\Services\MembershipService;
use App\Domains\Trip\Models\Trip;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

final class StalenessEscalationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionCatalogueSeeder::class);
    }

    /**
     * A trip whose last fix is older than the staleness threshold (default 300s).
     *
     * @return array{0: User, 1: string, 2: string}  traveler, orgId, tripId
     */
    private function darkTrip(): array
    {
        $owner = User::factory()->create();
        $organizationId = $this->actingAs($owner, 'sanctum')
            ->postJson('/api/v1/organizations', ['name' => 'Acme Inc'])
            ->json('data.id');

        $traveler = User::factory()->create();
        app(MembershipService::class)->addMember(Organization::find($organizationId), $traveler, OrganizationRole::User->value);

        $tripId = $this->actingAs($traveler, 'sanctum')
            ->postJson("/api/v1/organizations/{$organizationId}/trips", [])
            ->json('data.id');

        // A fix recorded 10 minutes ago — older than the 5-minute threshold.
        $this->actingAs($traveler, 'sanctum')
            ->postJson("/api/v1/organizations/{$organizationId}/trips/{$tripId}/locations", ['fixes' => [
                ['id' => (string) Str::uuid(), 'lat' => 1.0, 'lng' => 2.0, 'recorded_at' => now()->subMinutes(10)->toIso8601String()],
            ]])->assertStatus(202);

        return [$traveler, $organizationId, $tripId];
    }

    public function test_a_dark_trip_is_flagged_and_escalated_once(): void
    {
        [, , $tripId] = $this->darkTrip();

        $this->artisan('tracking:flag-stale')->assertSuccessful();

        $this->assertNotNull(Trip::find($tripId)->lost_contact_at);

        $emergency = Emergency::where('trip_id', $tripId)->firstOrFail();
        $this->assertSame('trip.lost_contact', $emergency->metadata['source']);

        // Idempotent: a second sweep neither re-flags nor raises a second emergency.
        $this->artisan('tracking:flag-stale')->assertSuccessful();
        $this->assertSame(1, Emergency::where('trip_id', $tripId)->count());
    }

    public function test_a_reconnecting_fix_clears_the_lost_contact_flag(): void
    {
        [$traveler, $organizationId, $tripId] = $this->darkTrip();

        $this->artisan('tracking:flag-stale')->assertSuccessful();
        $this->assertNotNull(Trip::find($tripId)->lost_contact_at);

        // A fresh fix arrives — contact restored.
        $this->actingAs($traveler, 'sanctum')
            ->postJson("/api/v1/organizations/{$organizationId}/trips/{$tripId}/locations", ['fixes' => [
                ['id' => (string) Str::uuid(), 'lat' => 1.2, 'lng' => 2.2, 'recorded_at' => now()->toIso8601String()],
            ]])->assertStatus(202);

        $this->assertNull(Trip::find($tripId)->lost_contact_at);
    }
}
