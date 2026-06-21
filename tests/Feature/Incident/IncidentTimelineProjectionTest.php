<?php

declare(strict_types=1);

namespace Tests\Feature\Incident;

use App\Domains\Authorization\Database\Seeders\PermissionCatalogueSeeder;
use App\Domains\Authorization\Support\Enums\OrganizationRole;
use App\Domains\Incident\Models\Incident;
use App\Domains\Incident\Models\IncidentTimelineEntry;
use App\Domains\Incident\Support\IncidentTimelineDeriver;
use App\Domains\Organization\Models\Organization;
use App\Domains\Organization\Services\MembershipService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Verifies that operational domain events are projected onto the incident
 * timeline (incident_timeline_entries) by RecordTimelineEntryFromDomainEvent.
 */
final class IncidentTimelineProjectionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionCatalogueSeeder::class);
        config(['sentrix.responders.assignment_acceptance_timeout_seconds' => 0]);
    }

    public function test_domain_events_are_projected_onto_the_incident_timeline(): void
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

        // Create incident → projects incident.opened.
        $incidentId = $this->actingAs($owner, 'sanctum')
            ->postJson("/api/v1/organizations/{$orgId}/incidents", ['title' => 'Structure fire', 'severity' => 'high'])
            ->json('data.id');

        // Assign + accept → projects assignment.created and assignment.responder_accepted.
        $assignmentId = $this->actingAs($owner, 'sanctum')
            ->postJson("/api/v1/organizations/{$orgId}/assignments", ['incident_id' => $incidentId])
            ->json('data.id');
        $lineId = $this->actingAs($owner, 'sanctum')
            ->postJson("/api/v1/organizations/{$orgId}/assignments/{$assignmentId}/responders", ['responder_id' => $responderId, 'role' => 'primary'])
            ->json('data.id');
        $this->actingAs($bob, 'sanctum')
            ->postJson("/api/v1/organizations/{$orgId}/assignments/{$assignmentId}/responders/{$lineId}/accept")
            ->assertOk();

        // Update incident status → projects incident.status_changed.
        $this->actingAs($owner, 'sanctum')
            ->postJson("/api/v1/organizations/{$orgId}/incidents/{$incidentId}/investigate")
            ->assertOk();

        foreach ([
            ['type' => 'incident.opened', 'source' => 'incident'],
            ['type' => 'assignment.created', 'source' => 'assignment'],
            ['type' => 'assignment.responder_accepted', 'source' => 'assignment'],
            ['type' => 'incident.status_changed', 'source' => 'incident'],
        ] as $expected) {
            $this->assertDatabaseHas('incident_timeline_entries', [
                'incident_id' => $incidentId,
                'organization_id' => $orgId,
                'type' => $expected['type'],
                'source' => $expected['source'],
            ]);
        }

        // Every projected entry is scoped to the incident and carries a known source.
        IncidentTimelineEntry::query()
            ->where('incident_id', $incidentId)
            ->get()
            ->each(function (IncidentTimelineEntry $entry): void {
                $this->assertContains($entry->source, ['incident', 'assignment']);
            });
    }

    /**
     * Parity: every event type the DERIVED timeline shows must also exist in the
     * PROJECTED table — so the table is a safe future read source. Drives a rich
     * path (full dispatch progression + every incident milestone) and compares.
     */
    public function test_projected_timeline_reaches_parity_with_derived_timeline(): void
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

        $incidentId = $this->actingAs($owner, 'sanctum')
            ->postJson("/api/v1/organizations/{$orgId}/incidents", ['title' => 'Major incident', 'severity' => 'high'])
            ->json('data.id');

        // Full dispatch progression: create → offer → accept → en-route → on-scene → complete line.
        $assignmentId = $this->actingAs($owner, 'sanctum')
            ->postJson("/api/v1/organizations/{$orgId}/assignments", ['incident_id' => $incidentId])
            ->json('data.id');
        $lineId = $this->actingAs($owner, 'sanctum')
            ->postJson("/api/v1/organizations/{$orgId}/assignments/{$assignmentId}/responders", ['responder_id' => $responderId, 'role' => 'primary'])
            ->json('data.id');
        $base = "/api/v1/organizations/{$orgId}/assignments/{$assignmentId}/responders/{$lineId}";
        $this->actingAs($bob, 'sanctum')->postJson("{$base}/accept")->assertOk();
        $this->actingAs($bob, 'sanctum')->postJson("{$base}/en-route")->assertOk();
        $this->actingAs($bob, 'sanctum')->postJson("{$base}/on-scene")->assertOk();
        $this->actingAs($bob, 'sanctum')->postJson("{$base}/complete")->assertOk();

        // Every incident milestone: investigate → escalate → resolve → close.
        $inc = "/api/v1/organizations/{$orgId}/incidents/{$incidentId}";
        $this->actingAs($owner, 'sanctum')->postJson("{$inc}/investigate")->assertOk();
        $this->actingAs($owner, 'sanctum')->postJson("{$inc}/escalate")->assertOk();
        $this->actingAs($owner, 'sanctum')->postJson("{$inc}/resolve")->assertOk();
        $this->actingAs($owner, 'sanctum')->postJson("{$inc}/close")->assertOk();

        $incident = Incident::findOrFail($incidentId);

        // Compare the independent derivation (legacy logic, now backfill-only) to
        // the projector-populated table — proving the projector matches what
        // derivation would produce, even though the live read no longer derives.
        $derivedTypes = collect(app(IncidentTimelineDeriver::class)->derive($incident))
            ->pluck('type')->unique()->sort()->values();

        $projectedTypes = IncidentTimelineEntry::query()
            ->where('incident_id', $incidentId)
            ->pluck('type')->unique()->sort()->values();

        $missing = $derivedTypes->diff($projectedTypes)->values();

        $this->assertTrue(
            $missing->isEmpty(),
            'Projected timeline is missing derived event types: '.$missing->implode(', '),
        );
        // Sanity: the scenario actually produced a meaningful timeline.
        $this->assertGreaterThanOrEqual(8, $derivedTypes->count());
    }
}
