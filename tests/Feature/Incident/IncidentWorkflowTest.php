<?php

declare(strict_types=1);

namespace Tests\Feature\Incident;

use App\Domains\Authorization\Database\Seeders\PermissionCatalogueSeeder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class IncidentWorkflowTest extends TestCase
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

    public function test_incident_can_be_opened_and_escalated(): void
    {
        [$owner, $organizationId] = $this->ownerWithOrganization();

        $incidentId = $this->actingAs($owner, 'sanctum')
            ->postJson("/api/v1/organizations/{$organizationId}/incidents", [
                'title' => 'Missing check-in',
                'severity' => 'high',
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'open')
            ->json('data.id');

        $this->actingAs($owner, 'sanctum')
            ->postJson("/api/v1/organizations/{$organizationId}/incidents/{$incidentId}/escalate")
            ->assertOk()
            ->assertJsonPath('data.status', 'escalated');

        $this->assertDatabaseHas('incidents', ['id' => $incidentId, 'status' => 'escalated']);
    }

    public function test_illegal_transition_from_closed_is_rejected(): void
    {
        [$owner, $organizationId] = $this->ownerWithOrganization();

        $incidentId = $this->actingAs($owner, 'sanctum')
            ->postJson("/api/v1/organizations/{$organizationId}/incidents", ['title' => 'X'])
            ->json('data.id');

        // open -> closed is allowed.
        $this->actingAs($owner, 'sanctum')
            ->postJson("/api/v1/organizations/{$organizationId}/incidents/{$incidentId}/close")
            ->assertOk()
            ->assertJsonPath('data.status', 'closed');

        // closed is terminal: escalation must be refused.
        $this->actingAs($owner, 'sanctum')
            ->postJson("/api/v1/organizations/{$organizationId}/incidents/{$incidentId}/escalate")
            ->assertStatus(422);
    }
}
