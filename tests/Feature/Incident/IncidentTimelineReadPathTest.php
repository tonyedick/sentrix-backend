<?php

declare(strict_types=1);

namespace Tests\Feature\Incident;

use App\Domains\Assignment\Models\Assignment;
use App\Domains\Assignment\Models\AssignmentEvent;
use App\Domains\Authorization\Database\Seeders\PermissionCatalogueSeeder;
use App\Domains\Authorization\Support\Enums\OrganizationRole;
use App\Domains\Incident\Models\IncidentTimelineEntry;
use App\Domains\Organization\Models\Organization;
use App\Domains\Organization\Services\MembershipService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Proves the timeline API is powered EXCLUSIVELY by incident_timeline_entries
 * after the read-path flip: it returns rows from the table (including ones that
 * derivation could never produce) and ignores assignment_events that were not
 * projected into the table.
 */
final class IncidentTimelineReadPathTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionCatalogueSeeder::class);
    }

    /**
     * @return array{0: User, 1: string, 2: string}
     */
    private function ownerOrgIncident(): array
    {
        $owner = User::factory()->create();
        $orgId = $this->actingAs($owner, 'sanctum')
            ->postJson('/api/v1/organizations', ['name' => 'Acme Inc'])
            ->json('data.id');
        $incidentId = $this->actingAs($owner, 'sanctum')
            ->postJson("/api/v1/organizations/{$orgId}/incidents", ['title' => 'T', 'severity' => 'high'])
            ->json('data.id');

        return [$owner, $orgId, $incidentId];
    }

    public function test_timeline_returns_rows_straight_from_the_table(): void
    {
        [$owner, $orgId, $incidentId] = $this->ownerOrgIncident();

        // An entry that NO derivation could produce — it only exists in the table.
        IncidentTimelineEntry::create([
            'organization_id' => $orgId,
            'incident_id' => $incidentId,
            'type' => 'ai.risk_assessed',
            'source' => 'ai',
            'occurred_at' => Carbon::now(),
            'payload' => ['score' => 0.9],
        ]);

        $this->actingAs($owner, 'sanctum')
            ->getJson("/api/v1/organizations/{$orgId}/incidents/{$incidentId}/timeline")
            ->assertOk()
            ->assertJsonFragment(['type' => 'ai.risk_assessed'])  // table-only row surfaced
            ->assertJsonFragment(['type' => 'incident.opened']);  // projected row surfaced
    }

    public function test_timeline_ignores_assignment_events_not_projected_to_the_table(): void
    {
        [$owner, $orgId, $incidentId] = $this->ownerOrgIncident();

        // An assignment_events row that was NOT projected into the timeline table.
        // The OLD derived read would have surfaced it; the table-backed read must not.
        $assignment = Assignment::create([
            'organization_id' => $orgId,
            'incident_id' => $incidentId,
            'status' => 'pending',
            'dispatch_mode' => 'manual',
            'required_primary' => true,
            'required_supporting' => 0,
        ]);
        AssignmentEvent::create([
            'assignment_id' => $assignment->getKey(),
            'organization_id' => $orgId,
            'type' => 'assignment.orphan_marker',
            'payload' => null,
            'created_at' => Carbon::now(),
        ]);

        $this->actingAs($owner, 'sanctum')
            ->getJson("/api/v1/organizations/{$orgId}/incidents/{$incidentId}/timeline")
            ->assertOk()
            ->assertJsonFragment(['type' => 'incident.opened'])
            ->assertJsonMissing(['type' => 'assignment.orphan_marker']);
    }

    public function test_timeline_preserves_chronological_ordering(): void
    {
        [$owner, $orgId, $incidentId] = $this->ownerOrgIncident();

        // Insert newer first to prove ordering comes from occurred_at, not insert order.
        IncidentTimelineEntry::create([
            'organization_id' => $orgId, 'incident_id' => $incidentId,
            'type' => 'x.newer', 'source' => 'system', 'occurred_at' => Carbon::now()->addMinutes(10),
        ]);
        IncidentTimelineEntry::create([
            'organization_id' => $orgId, 'incident_id' => $incidentId,
            'type' => 'x.older', 'source' => 'system', 'occurred_at' => Carbon::now()->addMinutes(5),
        ]);

        $types = collect(
            $this->actingAs($owner, 'sanctum')
                ->getJson("/api/v1/organizations/{$orgId}/incidents/{$incidentId}/timeline")
                ->assertOk()
                ->json('data')
        )->pluck('type');

        $this->assertLessThan(
            $types->search('x.newer'),
            $types->search('x.older'),
            'Older entry should appear before newer entry.',
        );
    }

    public function test_timeline_requires_view_permission(): void
    {
        [, $orgId, $incidentId] = $this->ownerOrgIncident();

        // A member whose role lacks incidents.view (User role) cannot read it —
        // authorization behaviour is preserved by the flip.
        $organization = Organization::findOrFail($orgId);
        $fieldUser = User::factory()->create();
        app(MembershipService::class)->addMember($organization, $fieldUser, OrganizationRole::User->value);

        $this->actingAs($fieldUser, 'sanctum')
            ->getJson("/api/v1/organizations/{$orgId}/incidents/{$incidentId}/timeline")
            ->assertForbidden();
    }
}
