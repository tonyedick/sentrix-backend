<?php

declare(strict_types=1);

namespace Tests\Feature\Responder;

use App\Domains\Authorization\Database\Seeders\PermissionCatalogueSeeder;
use App\Domains\Authorization\Support\Enums\OrganizationRole;
use App\Domains\Organization\Models\Organization;
use App\Domains\Organization\Services\MembershipService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Read endpoints that back the Responder Operations Workspace: per-responder
 * skills, assignment participation (current + history), and the current
 * assignment surfaced on show().
 */
final class ResponderWorkspaceApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionCatalogueSeeder::class);
        // No deferred offer-expiry jobs in tests.
        config(['sentrix.responders.assignment_acceptance_timeout_seconds' => 0]);
    }

    /**
     * @return array{0: User, 1: string, 2: User, 3: string}
     *               owner, orgId, responderUser(bob), responderId
     */
    private function ownerOrgResponder(): array
    {
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

        return [$owner, $orgId, $bob, $responderId];
    }

    public function test_responder_skills_can_be_listed(): void
    {
        [$owner, $orgId, , $responderId] = $this->ownerOrgResponder();

        $skillId = $this->actingAs($owner, 'sanctum')
            ->postJson("/api/v1/organizations/{$orgId}/skills", ['code' => 'medic', 'name' => 'Medic'])
            ->assertCreated()
            ->json('data.id');

        $this->actingAs($owner, 'sanctum')
            ->postJson("/api/v1/organizations/{$orgId}/responders/{$responderId}/skills", [
                'skill_id' => $skillId,
                'proficiency' => 'trained',
            ])
            ->assertOk();

        $this->actingAs($owner, 'sanctum')
            ->getJson("/api/v1/organizations/{$orgId}/responders/{$responderId}/skills")
            ->assertOk()
            ->assertJsonPath('data.0.id', $skillId)
            ->assertJsonPath('data.0.proficiency', 'trained');
    }

    public function test_responder_assignment_history_is_returned(): void
    {
        [$owner, $orgId, , $responderId] = $this->ownerOrgResponder();

        $incidentId = $this->actingAs($owner, 'sanctum')
            ->postJson("/api/v1/organizations/{$orgId}/incidents", ['title' => 'Structure fire'])
            ->json('data.id');
        $assignmentId = $this->actingAs($owner, 'sanctum')
            ->postJson("/api/v1/organizations/{$orgId}/assignments", ['incident_id' => $incidentId])
            ->json('data.id');

        $this->actingAs($owner, 'sanctum')
            ->postJson("/api/v1/organizations/{$orgId}/assignments/{$assignmentId}/responders", [
                'responder_id' => $responderId,
                'role' => 'primary',
            ])
            ->assertCreated();

        $this->actingAs($owner, 'sanctum')
            ->getJson("/api/v1/organizations/{$orgId}/responders/{$responderId}/assignments")
            ->assertOk()
            ->assertJsonPath('data.0.role', 'primary')
            ->assertJsonPath('data.0.incident.id', $incidentId)
            ->assertJsonPath('data.0.incident.title', 'Structure fire');
    }

    public function test_show_includes_current_assignment_after_accept(): void
    {
        [$owner, $orgId, $bob, $responderId] = $this->ownerOrgResponder();

        $incidentId = $this->actingAs($owner, 'sanctum')
            ->postJson("/api/v1/organizations/{$orgId}/incidents", ['title' => 'Collision'])
            ->json('data.id');
        $assignmentId = $this->actingAs($owner, 'sanctum')
            ->postJson("/api/v1/organizations/{$orgId}/assignments", ['incident_id' => $incidentId])
            ->json('data.id');
        $lineId = $this->actingAs($owner, 'sanctum')
            ->postJson("/api/v1/organizations/{$orgId}/assignments/{$assignmentId}/responders", [
                'responder_id' => $responderId,
                'role' => 'primary',
            ])
            ->assertCreated()
            ->json('data.id');

        // The responder accepts → current_assignment_id is set.
        $this->actingAs($bob, 'sanctum')
            ->postJson("/api/v1/organizations/{$orgId}/assignments/{$assignmentId}/responders/{$lineId}/accept")
            ->assertOk();

        $this->actingAs($owner, 'sanctum')
            ->getJson("/api/v1/organizations/{$orgId}/responders/{$responderId}")
            ->assertOk()
            ->assertJsonPath('data.current_assignment.incident.id', $incidentId)
            ->assertJsonPath('data.current_assignment.status', 'accepted');
    }
}
