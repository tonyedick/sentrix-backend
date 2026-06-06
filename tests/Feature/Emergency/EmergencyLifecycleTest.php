<?php

declare(strict_types=1);

namespace Tests\Feature\Emergency;

use App\Domains\Authorization\Database\Seeders\PermissionCatalogueSeeder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class EmergencyLifecycleTest extends TestCase
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

    public function test_emergency_can_be_triggered_acknowledged_and_resolved(): void
    {
        [$owner, $organizationId] = $this->ownerWithOrganization();

        $emergencyId = $this->actingAs($owner, 'sanctum')
            ->postJson("/api/v1/organizations/{$organizationId}/emergencies", ['severity' => 'high'])
            ->assertCreated()
            ->assertJsonPath('data.status', 'triggered')
            ->json('data.id');

        $this->actingAs($owner, 'sanctum')
            ->postJson("/api/v1/organizations/{$organizationId}/emergencies/{$emergencyId}/acknowledge")
            ->assertOk()
            ->assertJsonPath('data.status', 'acknowledged');

        $this->actingAs($owner, 'sanctum')
            ->postJson("/api/v1/organizations/{$organizationId}/emergencies/{$emergencyId}/resolve", [
                'resolution' => 'Stood down on scene.',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'resolved');

        $this->assertDatabaseHas('emergencies', ['id' => $emergencyId, 'status' => 'resolved']);
    }

    public function test_a_resolved_emergency_cannot_be_acknowledged(): void
    {
        [$owner, $organizationId] = $this->ownerWithOrganization();

        $emergencyId = $this->actingAs($owner, 'sanctum')
            ->postJson("/api/v1/organizations/{$organizationId}/emergencies", [])
            ->json('data.id');

        $this->actingAs($owner, 'sanctum')
            ->postJson("/api/v1/organizations/{$organizationId}/emergencies/{$emergencyId}/resolve")
            ->assertOk();

        $this->actingAs($owner, 'sanctum')
            ->postJson("/api/v1/organizations/{$organizationId}/emergencies/{$emergencyId}/acknowledge")
            ->assertStatus(422);
    }
}
