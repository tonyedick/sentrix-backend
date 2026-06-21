<?php

declare(strict_types=1);

namespace Tests\Feature\Assignment;

use App\Domains\Assignment\Models\Assignment;
use App\Domains\Assignment\Models\AssignmentResponder;
use App\Domains\Authorization\Database\Seeders\PermissionCatalogueSeeder;
use App\Domains\Authorization\Support\Enums\OrganizationRole;
use App\Domains\Organization\Models\Organization;
use App\Domains\Organization\Services\MembershipService;
use App\Domains\Responder\Models\Responder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Covers the Increment 1.5 + Increment 2 reliability behaviours. The acceptance
 * timeout is disabled (0) so the synchronous test queue runs the real
 * decline→reassign→escalate and closure→release chains without the delayed
 * timeout job firing spuriously.
 */
final class AssignmentReliabilityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionCatalogueSeeder::class);
        config([
            'sentrix.responders.assignment_acceptance_timeout_seconds' => 0,
            'sentrix.assignments.auto_reassign' => true,
            'sentrix.assignments.auto_dispatch' => true,
        ]);
    }

    private function owner(): array
    {
        $owner = User::factory()->create();
        $orgId = $this->actingAs($owner, 'sanctum')
            ->postJson('/api/v1/organizations', ['name' => 'Acme Inc'])
            ->json('data.id');

        return [$owner, $orgId, Organization::findOrFail($orgId)];
    }

    private function availableResponder(Organization $org, string $orgId, User $owner): array
    {
        $user = User::factory()->create();
        app(MembershipService::class)->addMember($org, $user, OrganizationRole::Responder->value);
        $responderId = $this->actingAs($owner, 'sanctum')
            ->postJson("/api/v1/organizations/{$orgId}/responders", ['user_id' => $user->getKey()])
            ->json('data.id');
        $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/organizations/{$orgId}/responders/{$responderId}/status", ['status' => 'available'])
            ->assertOk();

        return [$user, $responderId];
    }

    private function incident(User $owner, string $orgId): string
    {
        return $this->actingAs($owner, 'sanctum')
            ->postJson("/api/v1/organizations/{$orgId}/incidents", ['title' => 'Test', 'severity' => 'high'])
            ->json('data.id');
    }

    public function test_resolving_incident_releases_the_assignment(): void
    {
        [$owner, $orgId, $org] = $this->owner();
        [$bob, $responderId] = $this->availableResponder($org, $orgId, $owner);
        $incidentId = $this->incident($owner, $orgId);

        $assignmentId = $this->actingAs($owner, 'sanctum')
            ->postJson("/api/v1/organizations/{$orgId}/assignments", ['incident_id' => $incidentId])
            ->json('data.id');
        $lineId = $this->actingAs($owner, 'sanctum')
            ->postJson("/api/v1/organizations/{$orgId}/assignments/{$assignmentId}/responders", ['responder_id' => $responderId, 'role' => 'primary'])
            ->json('data.id');
        $this->actingAs($bob, 'sanctum')->postJson("/api/v1/organizations/{$orgId}/assignments/{$assignmentId}/responders/{$lineId}/accept")->assertOk();

        // Resolve the incident → ReleaseAssignmentOnIncidentClosure completes the assignment.
        $this->actingAs($owner, 'sanctum')
            ->postJson("/api/v1/organizations/{$orgId}/incidents/{$incidentId}/resolve")
            ->assertOk();

        $this->assertSame('completed', Assignment::findOrFail($assignmentId)->status->value);
        $this->assertSame('available', Responder::findOrFail($responderId)->status->value);
        $this->assertDatabaseHas('incidents', ['id' => $incidentId, 'assigned_to' => null]);
    }

    public function test_decline_auto_reassigns_to_next_candidate(): void
    {
        [$owner, $orgId, $org] = $this->owner();
        [$alice, $aliceId] = $this->availableResponder($org, $orgId, $owner);
        [, $bobId] = $this->availableResponder($org, $orgId, $owner);
        $incidentId = $this->incident($owner, $orgId);

        $assignmentId = $this->actingAs($owner, 'sanctum')
            ->postJson("/api/v1/organizations/{$orgId}/assignments", ['incident_id' => $incidentId])
            ->json('data.id');
        $lineId = $this->actingAs($owner, 'sanctum')
            ->postJson("/api/v1/organizations/{$orgId}/assignments/{$assignmentId}/responders", ['responder_id' => $aliceId, 'role' => 'primary'])
            ->json('data.id');

        // Alice declines → auto-reassignment offers the other responder.
        $this->actingAs($alice, 'sanctum')
            ->postJson("/api/v1/organizations/{$orgId}/assignments/{$assignmentId}/responders/{$lineId}/decline")
            ->assertOk();

        $this->assertDatabaseHas('assignment_responders', [
            'assignment_id' => $assignmentId,
            'responder_id' => $bobId,
            'role' => 'primary',
            'status' => 'offered',
        ]);
        $this->assertDatabaseHas('assignment_responders', [
            'id' => $lineId,
            'status' => 'declined',
        ]);
    }

    public function test_decline_with_no_other_candidate_escalates(): void
    {
        [$owner, $orgId, $org] = $this->owner();
        [$alice, $aliceId] = $this->availableResponder($org, $orgId, $owner);
        $incidentId = $this->incident($owner, $orgId);

        $assignmentId = $this->actingAs($owner, 'sanctum')
            ->postJson("/api/v1/organizations/{$orgId}/assignments", ['incident_id' => $incidentId])
            ->json('data.id');
        $lineId = $this->actingAs($owner, 'sanctum')
            ->postJson("/api/v1/organizations/{$orgId}/assignments/{$assignmentId}/responders", ['responder_id' => $aliceId, 'role' => 'primary'])
            ->json('data.id');

        $this->actingAs($alice, 'sanctum')
            ->postJson("/api/v1/organizations/{$orgId}/assignments/{$assignmentId}/responders/{$lineId}/decline")
            ->assertOk();

        $this->assertSame('escalated', Assignment::findOrFail($assignmentId)->status->value);
        $this->assertGreaterThanOrEqual(1, Assignment::findOrFail($assignmentId)->escalation_level);
    }

    public function test_auto_dispatch_offers_a_primary_on_open(): void
    {
        [$owner, $orgId, $org] = $this->owner();
        [, $responderId] = $this->availableResponder($org, $orgId, $owner);
        $incidentId = $this->incident($owner, $orgId);

        $assignmentId = $this->actingAs($owner, 'sanctum')
            ->postJson("/api/v1/organizations/{$orgId}/assignments", ['incident_id' => $incidentId, 'dispatch_mode' => 'auto'])
            ->assertCreated()
            ->json('data.id');

        // QueueAutoDispatch → DispatchAssignmentJob offered the recommended primary.
        $this->assertDatabaseHas('assignment_responders', [
            'assignment_id' => $assignmentId,
            'responder_id' => $responderId,
            'role' => 'primary',
            'status' => 'offered',
        ]);
    }

    public function test_connectivity_sweep_stands_down_a_gone_dark_responder(): void
    {
        [$owner, $orgId, $org] = $this->owner();
        [$bob, $responderId] = $this->availableResponder($org, $orgId, $owner);
        $incidentId = $this->incident($owner, $orgId);

        $assignmentId = $this->actingAs($owner, 'sanctum')
            ->postJson("/api/v1/organizations/{$orgId}/assignments", ['incident_id' => $incidentId])
            ->json('data.id');
        $lineId = $this->actingAs($owner, 'sanctum')
            ->postJson("/api/v1/organizations/{$orgId}/assignments/{$assignmentId}/responders", ['responder_id' => $responderId, 'role' => 'primary'])
            ->json('data.id');
        $this->actingAs($bob, 'sanctum')->postJson("/api/v1/organizations/{$orgId}/assignments/{$assignmentId}/responders/{$lineId}/accept")->assertOk();

        // Responder goes dark (stale last_seen) then the sweep runs.
        Responder::query()->whereKey($responderId)->update(['last_seen_at' => Carbon::now()->subHour()]);
        $this->artisan('assignments:reconcile-connectivity')->assertSuccessful();

        $this->assertDatabaseHas('assignment_responders', ['id' => $lineId, 'status' => 'stood_down']);
        $this->assertSame('available', Responder::findOrFail($responderId)->status->value);
    }
}
