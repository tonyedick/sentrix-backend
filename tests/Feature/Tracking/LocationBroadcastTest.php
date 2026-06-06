<?php

declare(strict_types=1);

namespace Tests\Feature\Tracking;

use App\Domains\Authorization\Database\Seeders\PermissionCatalogueSeeder;
use App\Domains\Authorization\Support\Enums\OrganizationRole;
use App\Domains\Organization\Models\Organization;
use App\Domains\Organization\Services\MembershipService;
use App\Domains\Tracking\Events\TripLocationUpdated;
use App\Models\User;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Tests\TestCase;

final class LocationBroadcastTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionCatalogueSeeder::class);
    }

    /**
     * @return array{0: User, 1: string, 2: string}  traveler, orgId, tripId
     */
    private function activeTrip(): array
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

        return [$traveler, $organizationId, $tripId];
    }

    public function test_live_position_broadcasts_are_coalesced(): void
    {
        [$traveler, $organizationId, $tripId] = $this->activeTrip();

        Event::fake([TripLocationUpdated::class]);

        $url = "/api/v1/organizations/{$organizationId}/trips/{$tripId}/locations";

        // Two batches inside the coalesce window.
        $this->actingAs($traveler, 'sanctum')->postJson($url, ['fixes' => [
            ['id' => (string) Str::uuid(), 'lat' => 1.0, 'lng' => 2.0, 'recorded_at' => now()->subSeconds(2)->toIso8601String()],
        ]])->assertStatus(202);

        $this->actingAs($traveler, 'sanctum')->postJson($url, ['fixes' => [
            ['id' => (string) Str::uuid(), 'lat' => 1.1, 'lng' => 2.1, 'recorded_at' => now()->toIso8601String()],
        ]])->assertStatus(202);

        // Only one broadcast fires for the window.
        Event::assertDispatchedTimes(TripLocationUpdated::class, 1);
        Event::assertDispatched(
            TripLocationUpdated::class,
            static fn (TripLocationUpdated $event): bool => $event->tripId === $tripId && $event->organization === $organizationId,
        );
    }

    public function test_the_location_event_broadcasts_on_the_private_org_channel(): void
    {
        $event = new TripLocationUpdated(
            organization: '11111111-1111-1111-1111-111111111111',
            tripId: '22222222-2222-2222-2222-222222222222',
            lat: 1.5,
            lng: 2.5,
            recordedAt: '2026-06-04T00:00:00+00:00',
        );

        $this->assertInstanceOf(ShouldBroadcast::class, $event);
        $this->assertSame('private-organizations.'.$event->organization, (string) $event->broadcastOn()[0]);
        $this->assertSame('trip.location', $event->broadcastAs());
        $this->assertSame(1.5, $event->broadcastWith()['lat']);
        $this->assertSame($event->tripId, $event->broadcastWith()['trip_id']);
    }
}
