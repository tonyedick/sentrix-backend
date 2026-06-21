<?php

declare(strict_types=1);

namespace Tests\Feature\Incident;

use App\Domains\Authorization\Database\Seeders\PermissionCatalogueSeeder;
use App\Domains\Authorization\Support\Enums\OrganizationRole;
use App\Domains\Organization\Models\Organization;
use App\Domains\Organization\Services\MembershipService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The first operational vertical slice, end to end across the Incident,
 * Assignment, and Responder domains:
 *   create incident → assign responder → accept → update status →
 *   record timeline → view incident detail.
 */
final class IncidentOperationalSliceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionCatalogueSeeder::class);
        // Keep the acceptance-timeout job from firing; this slice is synchronous.
        config(['sentrix.responders.assignment_acceptance_timeout_seconds' => 0]);
    }

    public function test_incident_create_assign_accept_progress_and_view(): void
    {
        // Org owner (dispatcher abilities) + an available responder.
        $owner = User::factory()->create();
        $orgId = $this->actingAs($owner, 'sanctum')
            ->postJson('/api/v1/organizations', ['name' => 'Acme Inc'])
            ->json('data.id');
        $org = Organization::findOrFail($orgId);

        $bob = User::factory()->create();
        app(MembershipService::class)->addMember($org, $bob, OrganizationRole::Responder->value);
        $responderId = $this->actingAs($owner, 'sanctum')
            ->postJson("/api/v1/organizations/{$orgId}/responders", ['user_id' => $bob->getKey()])
            ->json('data.id');
        $this->actingAs($bob, 'sanctum')
            ->postJson("/api/v1/organizations/{$orgId}/responders/{$responderId}/status", ['status' => 'available'])
            ->assertOk();

        // 1. Create incident.
        $incidentId = $this->actingAs($owner, 'sanctum')
            ->postJson("/api/v1/organizations/{$orgId}/incidents", ['title' => 'Structure fire', 'severity' => 'high'])
            ->assertCreated()
            ->assertJsonPath('data.status', 'open')
            ->json('data.id');

        // 2. Assign responder (open assignment + offer primary).
        $assignmentId = $this->actingAs($owner, 'sanctum')
            ->postJson("/api/v1/organizations/{$orgId}/assignments", ['incident_id' => $incidentId])
            ->assertCreated()
            ->json('data.id');
        $lineId = $this->actingAs($owner, 'sanctum')
            ->postJson("/api/v1/organizations/{$orgId}/assignments/{$assignmentId}/responders", [
                'responder_id' => $responderId,
                'role' => 'primary',
            ])
            ->assertCreated()
            ->json('data.id');

        // 3. Accept assignment (the responder, self-service).
        $this->actingAs($bob, 'sanctum')
            ->postJson("/api/v1/organizations/{$orgId}/assignments/{$assignmentId}/responders/{$lineId}/accept")
            ->assertOk()
            ->assertJsonPath('data.status', 'accepted');
        $this->assertDatabaseHas('incidents', ['id' => $incidentId, 'assigned_to' => $bob->getKey()]);

        // 4. Update incident status.
        $this->actingAs($owner, 'sanctum')
            ->postJson("/api/v1/organizations/{$orgId}/incidents/{$incidentId}/investigate")
            ->assertOk()
            ->assertJsonPath('data.status', 'investigating');

        // 5. Timeline records the steps.
        $this->actingAs($owner, 'sanctum')
            ->getJson("/api/v1/organizations/{$orgId}/incidents/{$incidentId}/timeline")
            ->assertOk()
            ->assertJsonFragment(['type' => 'incident.opened'])
            ->assertJsonFragment(['type' => 'assignment.responder_offered'])
            ->assertJsonFragment(['type' => 'assignment.responder_accepted']);

        // 6. View incident detail: incident + active assignment + responder line + timeline.
        $this->actingAs($owner, 'sanctum')
            ->getJson("/api/v1/organizations/{$orgId}/incidents/{$incidentId}")
            ->assertOk()
            ->assertJsonPath('data.incident.id', $incidentId)
            ->assertJsonPath('data.incident.status', 'investigating')
            ->assertJsonPath('data.assignment.id', $assignmentId)
            ->assertJsonFragment(['id' => $lineId, 'status' => 'accepted'])
            ->assertJsonPath('data.incident.assigned_to', $bob->getKey());

        $this->assertNotEmpty(
            $this->actingAs($owner, 'sanctum')
                ->getJson("/api/v1/organizations/{$orgId}/incidents/{$incidentId}")
                ->json('data.timeline'),
        );
    }
}
