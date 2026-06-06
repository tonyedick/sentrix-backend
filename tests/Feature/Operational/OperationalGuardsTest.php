<?php

declare(strict_types=1);

namespace Tests\Feature\Operational;

use App\Domains\Authorization\Database\Seeders\PermissionCatalogueSeeder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class OperationalGuardsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionCatalogueSeeder::class);
    }

    /**
     * @return array{0: User, 1: string}
     */
    private function ownerWithOrganization(): array
    {
        $owner = User::factory()->create();
        $organizationId = $this->actingAs($owner, 'sanctum')
            ->postJson('/api/v1/organizations', ['name' => 'Acme Inc'])
            ->json('data.id');

        return [$owner, $organizationId];
    }

    public function test_an_emergency_cannot_be_acknowledged_twice(): void
    {
        [$owner, $organizationId] = $this->ownerWithOrganization();

        $emergencyId = $this->actingAs($owner, 'sanctum')
            ->postJson("/api/v1/organizations/{$organizationId}/emergencies", ['severity' => 'high'])
            ->json('data.id');

        $this->actingAs($owner, 'sanctum')
            ->postJson("/api/v1/organizations/{$organizationId}/emergencies/{$emergencyId}/acknowledge")
            ->assertOk();

        // The lock-and-recheck rejects the second acknowledgement.
        $this->actingAs($owner, 'sanctum')
            ->postJson("/api/v1/organizations/{$organizationId}/emergencies/{$emergencyId}/acknowledge")
            ->assertStatus(422);
    }

    public function test_a_partial_coordinate_is_rejected(): void
    {
        [$owner, $organizationId] = $this->ownerWithOrganization();

        $this->actingAs($owner, 'sanctum')
            ->postJson("/api/v1/organizations/{$organizationId}/trips", ['origin_lat' => 1.5])
            ->assertStatus(422)
            ->assertJsonValidationErrors('origin_lng');

        $this->actingAs($owner, 'sanctum')
            ->postJson("/api/v1/organizations/{$organizationId}/emergencies", ['lng' => 2.5])
            ->assertStatus(422)
            ->assertJsonValidationErrors('lat');
    }
}
