<?php

declare(strict_types=1);

namespace Tests\Feature\Consumer;

use App\Domains\Authorization\Database\Seeders\PermissionCatalogueSeeder;
use App\Domains\Emergency\Models\Emergency;
use App\Domains\Organization\Database\Seeders\MonitoringOrganizationSeeder;
use App\Domains\Organization\Models\Organization;
use App\Domains\Trip\Models\Trip;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * The consumer ↔ operations bridge (ADR-0001): user-scoped /v1/me trips + SOS,
 * served by the seeded Sentrix monitoring organization.
 */
final class ConsumerBridgeTest extends TestCase
{
    use RefreshDatabase;

    private Organization $servingOrg;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionCatalogueSeeder::class);
        $this->seed(MonitoringOrganizationSeeder::class);
        $this->servingOrg = Organization::query()->where('slug', 'sentrix-monitoring')->firstOrFail();
    }

    public function test_sos_creates_emergency_served_by_monitoring_org(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/me/emergencies', ['lat' => 6.5244, 'lng' => 3.3792, 'message' => 'Help'])
            ->assertCreated()
            ->assertJsonPath('data.user_id', $user->getKey());

        $this->assertDatabaseHas('emergencies', [
            'user_id' => $user->getKey(),
            'organization_id' => $this->servingOrg->getKey(),
        ]);
    }

    public function test_sos_is_idempotent_with_a_key(): void
    {
        $user = User::factory()->create();
        $headers = ['Idempotency-Key' => 'panic-123'];

        $first = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/me/emergencies', ['message' => 'SOS'], $headers)
            ->assertCreated();

        $second = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/me/emergencies', ['message' => 'SOS'], $headers)
            ->assertOk();

        $this->assertSame($first->json('data.id'), $second->json('data.id'));
        $this->assertSame(1, Emergency::query()->where('user_id', $user->getKey())->count());
    }

    public function test_consumer_can_start_and_list_trips(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/me/trips', ['origin_label' => 'Home', 'destination_label' => 'Office'])
            ->assertCreated()
            ->assertJsonPath('data.user_id', $user->getKey());

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/me/trips')
            ->assertOk()
            ->assertJsonPath('data.0.user_id', $user->getKey());

        $this->assertDatabaseHas('trips', [
            'user_id' => $user->getKey(),
            'organization_id' => $this->servingOrg->getKey(),
        ]);
    }

    public function test_cannot_view_another_users_trip(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();

        $tripId = $this->actingAs($alice, 'sanctum')
            ->postJson('/api/v1/me/trips', ['destination_label' => 'Somewhere'])
            ->json('data.id');

        $this->actingAs($bob, 'sanctum')
            ->getJson("/api/v1/me/trips/{$tripId}")
            ->assertNotFound();
    }

    public function test_trip_location_ingest(): void
    {
        $user = User::factory()->create();

        $tripId = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/me/trips', ['destination_label' => 'Office'])
            ->json('data.id');

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/me/trips/{$tripId}/locations", [
                'fixes' => [
                    ['id' => (string) Str::uuid(), 'lat' => 6.5, 'lng' => 3.3, 'recorded_at' => now()->toIso8601String()],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('data.received', 1);
    }
}
