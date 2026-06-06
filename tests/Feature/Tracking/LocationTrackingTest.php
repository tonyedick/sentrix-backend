<?php

declare(strict_types=1);

namespace Tests\Feature\Tracking;

use App\Domains\Authorization\Database\Seeders\PermissionCatalogueSeeder;
use App\Domains\Authorization\Support\Enums\OrganizationRole;
use App\Domains\Organization\Models\Organization;
use App\Domains\Organization\Services\MembershipService;
use App\Domains\Trip\Models\Trip;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

final class LocationTrackingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionCatalogueSeeder::class);
    }

    /**
     * @return array{0: User, 1: User, 2: string, 3: string}  owner, traveler, orgId, tripId
     */
    private function tripWithTraveler(): array
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

        return [$owner, $traveler, $organizationId, $tripId];
    }

    public function test_a_field_user_ingests_a_batch_and_last_known_advances(): void
    {
        [, $traveler, $organizationId, $tripId] = $this->tripWithTraveler();

        $fixes = [
            ['id' => (string) Str::uuid(), 'lat' => 1.0, 'lng' => 2.0, 'recorded_at' => now()->subMinutes(2)->toIso8601String()],
            ['id' => (string) Str::uuid(), 'lat' => 1.5, 'lng' => 2.5, 'recorded_at' => now()->subMinute()->toIso8601String()],
        ];

        $this->actingAs($traveler, 'sanctum')
            ->postJson("/api/v1/organizations/{$organizationId}/trips/{$tripId}/locations", ['fixes' => $fixes])
            ->assertStatus(202)
            ->assertJsonPath('data.stored', 2);

        $this->assertDatabaseCount('trip_locations', 2);

        // Last-known advanced to the newest fix.
        $trip = Trip::find($tripId);
        $this->assertEqualsWithDelta(1.5, $trip->last_lat, 0.0001);
        $this->assertEqualsWithDelta(2.5, $trip->last_lng, 0.0001);
        $this->assertNotNull($trip->last_location_at);
    }

    public function test_resent_fixes_are_deduplicated(): void
    {
        [, $traveler, $organizationId, $tripId] = $this->tripWithTraveler();

        $fixes = [
            ['id' => (string) Str::uuid(), 'lat' => 1.0, 'lng' => 2.0, 'recorded_at' => now()->subMinute()->toIso8601String()],
        ];
        $url = "/api/v1/organizations/{$organizationId}/trips/{$tripId}/locations";

        $this->actingAs($traveler, 'sanctum')->postJson($url, ['fixes' => $fixes])
            ->assertStatus(202)->assertJsonPath('data.stored', 1);

        // The exact same batch (flaky-network retry) stores nothing new.
        $this->actingAs($traveler, 'sanctum')->postJson($url, ['fixes' => $fixes])
            ->assertStatus(202)->assertJsonPath('data.stored', 0);

        $this->assertDatabaseCount('trip_locations', 1);
    }

    public function test_a_late_arriving_batch_does_not_rewind_last_known(): void
    {
        [, $traveler, $organizationId, $tripId] = $this->tripWithTraveler();
        $url = "/api/v1/organizations/{$organizationId}/trips/{$tripId}/locations";

        // Newest fix first.
        $this->actingAs($traveler, 'sanctum')->postJson($url, ['fixes' => [
            ['id' => (string) Str::uuid(), 'lat' => 5.0, 'lng' => 5.0, 'recorded_at' => now()->toIso8601String()],
        ]])->assertStatus(202);

        // A buffered, older batch arrives afterwards.
        $this->actingAs($traveler, 'sanctum')->postJson($url, ['fixes' => [
            ['id' => (string) Str::uuid(), 'lat' => 9.0, 'lng' => 9.0, 'recorded_at' => now()->subMinutes(10)->toIso8601String()],
        ]])->assertStatus(202);

        // Last-known still points at the newer fix.
        $this->assertEqualsWithDelta(5.0, Trip::find($tripId)->last_lat, 0.0001);
    }

    public function test_only_the_trips_own_user_may_ingest(): void
    {
        [, , $organizationId, $tripId] = $this->tripWithTraveler();

        $other = User::factory()->create();
        app(MembershipService::class)->addMember(Organization::find($organizationId), $other, OrganizationRole::User->value);

        $this->actingAs($other, 'sanctum')
            ->postJson("/api/v1/organizations/{$organizationId}/trips/{$tripId}/locations", ['fixes' => [
                ['id' => (string) Str::uuid(), 'lat' => 1.0, 'lng' => 2.0, 'recorded_at' => now()->toIso8601String()],
            ]])
            ->assertForbidden();
    }

    public function test_an_operator_sees_live_positions(): void
    {
        [$owner, $traveler, $organizationId, $tripId] = $this->tripWithTraveler();

        $this->actingAs($traveler, 'sanctum')
            ->postJson("/api/v1/organizations/{$organizationId}/trips/{$tripId}/locations", ['fixes' => [
                ['id' => (string) Str::uuid(), 'lat' => 1.0, 'lng' => 2.0, 'recorded_at' => now()->toIso8601String()],
            ]])->assertStatus(202);

        $this->actingAs($owner, 'sanctum')
            ->getJson("/api/v1/organizations/{$organizationId}/locations/latest")
            ->assertOk()
            ->assertJsonPath('data.0.trip_id', $tripId);
    }
}
