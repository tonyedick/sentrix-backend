<?php

declare(strict_types=1);

namespace Tests\Feature\Trip;

use App\Domains\Authorization\Database\Seeders\PermissionCatalogueSeeder;
use App\Domains\Authorization\Support\Enums\OrganizationRole;
use App\Domains\Organization\Models\Organization;
use App\Domains\Organization\Services\MembershipService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class TripLifecycleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionCatalogueSeeder::class);
    }

    private function ownerWithOrganization(): array
    {
        $owner = User::factory()->create();
        $organizationId = $this->actingAs($owner, 'sanctum')
            ->postJson('/api/v1/organizations', ['name' => 'Acme Inc'])
            ->json('data.id');

        return [$owner, $organizationId];
    }

    public function test_owner_can_start_and_complete_a_trip(): void
    {
        [$owner, $organizationId] = $this->ownerWithOrganization();

        $tripId = $this->actingAs($owner, 'sanctum')
            ->postJson("/api/v1/organizations/{$organizationId}/trips", [
                'destination_label' => 'HQ',
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'active')
            ->json('data.id');

        $this->actingAs($owner, 'sanctum')
            ->postJson("/api/v1/organizations/{$organizationId}/trips/{$tripId}/complete")
            ->assertOk()
            ->assertJsonPath('data.status', 'completed');

        $this->assertDatabaseHas('trips', ['id' => $tripId, 'status' => 'completed']);
    }

    public function test_field_users_see_only_their_own_trips(): void
    {
        [$owner, $organizationId] = $this->ownerWithOrganization();
        $organization = Organization::find($organizationId);

        $alice = User::factory()->create();
        $bob = User::factory()->create();
        app(MembershipService::class)->addMember($organization, $alice, OrganizationRole::User->value);
        app(MembershipService::class)->addMember($organization, $bob, OrganizationRole::User->value);

        // Alice starts a trip.
        $this->actingAs($alice, 'sanctum')
            ->postJson("/api/v1/organizations/{$organizationId}/trips", [])
            ->assertCreated();

        // Bob (a field user) sees none of Alice's trips.
        $this->actingAs($bob, 'sanctum')
            ->getJson("/api/v1/organizations/{$organizationId}/trips")
            ->assertOk()
            ->assertJsonCount(0, 'data');

        // The owner (operator) sees the trip.
        $this->actingAs($owner, 'sanctum')
            ->getJson("/api/v1/organizations/{$organizationId}/trips")
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_completing_a_terminal_trip_is_rejected(): void
    {
        [$owner, $organizationId] = $this->ownerWithOrganization();

        $tripId = $this->actingAs($owner, 'sanctum')
            ->postJson("/api/v1/organizations/{$organizationId}/trips", [])
            ->json('data.id');

        $this->actingAs($owner, 'sanctum')
            ->postJson("/api/v1/organizations/{$organizationId}/trips/{$tripId}/cancel")
            ->assertOk();

        $this->actingAs($owner, 'sanctum')
            ->postJson("/api/v1/organizations/{$organizationId}/trips/{$tripId}/complete")
            ->assertStatus(422);
    }
}
