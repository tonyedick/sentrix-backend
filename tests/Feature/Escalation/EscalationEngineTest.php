<?php

declare(strict_types=1);

namespace Tests\Feature\Escalation;

use App\Domains\Assignment\Models\Assignment;
use App\Domains\Authorization\Database\Seeders\PermissionCatalogueSeeder;
use App\Domains\Authorization\Support\Enums\OrganizationRole;
use App\Domains\Escalation\Jobs\EscalateStalledResponder;
use App\Domains\Escalation\Jobs\EscalateUnassignedIncident;
use App\Domains\Escalation\Models\EscalationPolicy;
use App\Domains\Escalation\Services\EscalationPolicyResolver;
use App\Domains\Assignment\Services\EscalationService;
use App\Domains\Incident\Models\Incident;
use App\Domains\Incident\Services\IncidentService;
use App\Domains\Organization\Models\Organization;
use App\Domains\Organization\Services\MembershipService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

final class EscalationEngineTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionCatalogueSeeder::class);
        config(['sentrix.responders.assignment_acceptance_timeout_seconds' => 0]);
    }

    /** @return array{0: User, 1: string, 2: Organization} */
    private function org(): array
    {
        $owner = User::factory()->create();
        $orgId = $this->actingAs($owner, 'sanctum')
            ->postJson('/api/v1/organizations', ['name' => 'Acme Inc'])->json('data.id');

        return [$owner, $orgId, Organization::findOrFail($orgId)];
    }

    private function incidentId(User $owner, string $orgId): string
    {
        return $this->actingAs($owner, 'sanctum')
            ->postJson("/api/v1/organizations/{$orgId}/incidents", ['title' => 'T', 'severity' => 'high'])
            ->json('data.id');
    }

    // ---- Configuration model -------------------------------------------------

    public function test_resolver_returns_config_defaults_without_a_policy_row(): void
    {
        [, $orgId] = $this->org();

        $policy = app(EscalationPolicyResolver::class)->for($orgId);

        $this->assertSame(300, $policy->incident_unassigned_seconds);
        $this->assertFalse($policy->incident_escalation_enabled); // opt-in default
    }

    public function test_resolver_returns_saved_policy_row(): void
    {
        [, $orgId] = $this->org();
        EscalationPolicy::create([
            'organization_id' => $orgId,
            'incident_unassigned_seconds' => 45,
            'incident_escalation_enabled' => true,
        ]);

        $policy = app(EscalationPolicyResolver::class)->for($orgId);

        $this->assertSame(45, $policy->incident_unassigned_seconds);
        $this->assertTrue($policy->incident_escalation_enabled);
    }

    // ---- Incident escalation job --------------------------------------------

    public function test_incident_job_escalates_an_unassigned_incident(): void
    {
        [$owner, $orgId] = $this->org();
        $incidentId = $this->incidentId($owner, $orgId);

        (new EscalateUnassignedIncident($incidentId))->handle(app(IncidentService::class));

        $this->assertSame('escalated', Incident::findOrFail($incidentId)->status->value);
    }

    public function test_incident_job_skips_when_an_assignment_exists(): void
    {
        [$owner, $orgId] = $this->org();
        $incidentId = $this->incidentId($owner, $orgId);
        $this->actingAs($owner, 'sanctum')
            ->postJson("/api/v1/organizations/{$orgId}/assignments", ['incident_id' => $incidentId])
            ->assertCreated();

        (new EscalateUnassignedIncident($incidentId))->handle(app(IncidentService::class));

        $this->assertSame('open', Incident::findOrFail($incidentId)->status->value);
    }

    // ---- Responder (no-progression) escalation job --------------------------

    /** @return array{0: string, 1: string} [assignmentId, lineId] */
    private function acceptedLine(User $owner, string $orgId, Organization $org): array
    {
        $bob = User::factory()->create();
        app(MembershipService::class)->addMember($org, $bob, OrganizationRole::Responder->value);
        $responderId = $this->actingAs($owner, 'sanctum')
            ->postJson("/api/v1/organizations/{$orgId}/responders", ['user_id' => $bob->getKey()])->json('data.id');
        $this->actingAs($bob, 'sanctum')
            ->postJson("/api/v1/organizations/{$orgId}/responders/{$responderId}/status", ['status' => 'available'])->assertOk();

        $incidentId = $this->incidentId($owner, $orgId);
        $assignmentId = $this->actingAs($owner, 'sanctum')
            ->postJson("/api/v1/organizations/{$orgId}/assignments", ['incident_id' => $incidentId])->json('data.id');
        $lineId = $this->actingAs($owner, 'sanctum')
            ->postJson("/api/v1/organizations/{$orgId}/assignments/{$assignmentId}/responders", ['responder_id' => $responderId, 'role' => 'primary'])->json('data.id');
        $this->actingAs($bob, 'sanctum')
            ->postJson("/api/v1/organizations/{$orgId}/assignments/{$assignmentId}/responders/{$lineId}/accept")->assertOk();

        return [$assignmentId, $lineId, $bob];
    }

    public function test_responder_job_escalates_a_stalled_acceptance(): void
    {
        [$owner, $orgId, $org] = $this->org();
        [$assignmentId, $lineId] = $this->acceptedLine($owner, $orgId, $org);

        (new EscalateStalledResponder($lineId))->handle(app(EscalationService::class));

        $this->assertSame('escalated', Assignment::findOrFail($assignmentId)->status->value);
    }

    public function test_responder_job_skips_when_responder_progressed(): void
    {
        [$owner, $orgId, $org] = $this->org();
        [$assignmentId, $lineId, $bob] = $this->acceptedLine($owner, $orgId, $org);

        // Responder progresses → no longer stalled.
        $this->actingAs($bob, 'sanctum')
            ->postJson("/api/v1/organizations/{$orgId}/assignments/{$assignmentId}/responders/{$lineId}/en-route")->assertOk();

        (new EscalateStalledResponder($lineId))->handle(app(EscalationService::class));

        $this->assertNotSame('escalated', Assignment::findOrFail($assignmentId)->status->value);
    }

    // ---- Scheduling (delayed jobs onto Redis/Horizon) -----------------------

    public function test_incident_creation_schedules_escalation_when_enabled(): void
    {
        config(['sentrix.escalation.incident_escalation_enabled' => true]);
        Queue::fake();
        [$owner, $orgId] = $this->org();

        $this->incidentId($owner, $orgId);

        Queue::assertPushed(EscalateUnassignedIncident::class);
    }

    public function test_incident_creation_does_not_schedule_when_disabled(): void
    {
        // Default config: incident escalation disabled.
        Queue::fake();
        [$owner, $orgId] = $this->org();

        $this->incidentId($owner, $orgId);

        Queue::assertNotPushed(EscalateUnassignedIncident::class);
    }

    public function test_acceptance_schedules_responder_escalation_when_enabled(): void
    {
        config(['sentrix.escalation.responder_escalation_enabled' => true]);
        Queue::fake();
        [$owner, $orgId, $org] = $this->org();

        $this->acceptedLine($owner, $orgId, $org);

        Queue::assertPushed(EscalateStalledResponder::class);
    }
}
