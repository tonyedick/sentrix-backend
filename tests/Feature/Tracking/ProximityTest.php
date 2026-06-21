<?php

declare(strict_types=1);

namespace Tests\Feature\Tracking;

use App\Domains\Authorization\Database\Seeders\PermissionCatalogueSeeder;
use App\Domains\Authorization\Support\Enums\OrganizationRole;
use App\Domains\Organization\Models\Organization;
use App\Domains\Organization\Services\MembershipService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * PostGIS proximity. Skipped on non-PostgreSQL drivers (the geography columns and
 * spatial functions require the PostGIS extension).
 */
final class ProximityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        if (DB::getDriverName() !== 'pgsql') {
            $this->markTestSkipped('Proximity requires PostGIS (PostgreSQL).');
        }

        $this->seed(PermissionCatalogueSeeder::class);
    }

    /**
     * @return array{0: User, 1: string}  an operator owner + org id
     */
    private function operatorOrganization(): array
    {
        $owner = User::factory()->create();
        $organizationId = $this->actingAs($owner, 'sanctum')
            ->postJson('/api/v1/organizations', ['name' => 'Acme Inc'])
            ->json('data.id');

        return [$owner, $organizationId];
    }

    /**
     * A field user on an active trip reporting one position.
     *
     * @return array{0: User, 1: string}  traveler + trip id
     */
    private function travelerTripAt(string $organizationId, float $lat, float $lng): array
    {
        $traveler = User::factory()->create();
        app(MembershipService::class)->addMember(Organization::find($organizationId), $traveler, OrganizationRole::User->value);

        $tripId = $this->actingAs($traveler, 'sanctum')
            ->postJson("/api/v1/organizations/{$organizationId}/trips", [])
            ->json('data.id');

        $this->actingAs($traveler, 'sanctum')
            ->postJson("/api/v1/organizations/{$organizationId}/trips/{$tripId}/locations", ['fixes' => [
                ['id' => (string) Str::uuid(), 'lat' => $lat, 'lng' => $lng, 'recorded_at' => now()->toIso8601String()],
            ]])->assertStatus(202);

        return [$traveler, $tripId];
    }

    public function test_nearby_returns_active_trips_within_the_radius(): void
    {
        [$owner, $organizationId] = $this->operatorOrganization();

        [, $nearTrip] = $this->travelerTripAt($organizationId, 0.001, 0.0); // ~111 m from (0,0)
        [, $farTrip] = $this->travelerTripAt($organizationId, 1.0, 0.0);    // ~111 km from (0,0)

        $data = collect(
            $this->actingAs($owner, 'sanctum')
                ->getJson("/api/v1/organizations/{$organizationId}/locations/nearby?lat=0&lng=0&radius=10000")
                ->assertOk()
                ->json('data')
        );

        $this->assertTrue($data->pluck('trip_id')->contains($nearTrip));
        $this->assertFalse($data->pluck('trip_id')->contains($farTrip));
        $this->assertLessThan(1000, (float) $data->firstWhere('trip_id', $nearTrip)['distance_m']);
    }

    public function test_nearby_to_emergency_excludes_the_emergencys_own_trip(): void
    {
        [$owner, $organizationId] = $this->operatorOrganization();

        [$subject, $subjectTrip] = $this->travelerTripAt($organizationId, 0.0005, 0.0); // ~55 m
        [, $otherTrip] = $this->travelerTripAt($organizationId, 0.001, 0.0);            // ~111 m

        $emergencyId = $this->actingAs($subject, 'sanctum')
            ->postJson("/api/v1/organizations/{$organizationId}/emergencies", [
                'severity' => 'high',
                'lat' => 0,
                'lng' => 0,
                'trip_id' => $subjectTrip,
            ])
            ->assertCreated()
            ->json('data.id');

        $ids = collect(
            $this->actingAs($owner, 'sanctum')
                ->getJson("/api/v1/organizations/{$organizationId}/emergencies/{$emergencyId}/nearby-trips?radius=10000")
                ->assertOk()
                ->json('data')
        )->pluck('trip_id');

        $this->assertTrue($ids->contains($otherTrip));
        $this->assertFalse($ids->contains($subjectTrip)); // the emergency's own trip is excluded
    }
}
