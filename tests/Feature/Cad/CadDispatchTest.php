<?php

declare(strict_types=1);

namespace Tests\Feature\Cad;

use App\Domains\Authorization\Database\Seeders\PermissionCatalogueSeeder;
use App\Domains\Authorization\Services\RoleService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class CadDispatchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionCatalogueSeeder::class);
    }

    private function superAdmin(): User
    {
        $admin = User::factory()->create();
        app(RoleService::class)->assignSuperAdmin($admin);

        return $admin;
    }

    /**
     * Seed an NPF agency, a divisional command WITH coords, and a routed crime
     * incident near that command (via the Command domain's own endpoints).
     *
     * @return array{0: User, 1: string, 2: string, 3: string}
     *   [admin, agencyId, commandId, incidentId]
     */
    private function seedStructureWithIncident(): array
    {
        $admin = $this->superAdmin();

        $agencyId = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/v1/command/agencies', [
                'code' => 'NPF',
                'name' => 'Nigeria Police Force',
                'country' => 'NG',
                'categories' => ['crime'],
                'hotline' => '112',
            ])
            ->assertCreated()
            ->json('data.id');

        $nationalId = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/v1/command/commands', [
                'agency_id' => $agencyId,
                'tier' => 'national',
                'name' => 'Force Headquarters',
                'area' => 'Abuja',
            ])
            ->assertCreated()
            ->json('data.id');

        $divisionalId = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/v1/command/commands', [
                'agency_id' => $agencyId,
                'parent_id' => $nationalId,
                'tier' => 'divisional',
                'name' => 'Gate District Division',
                'area' => 'Gate',
                'lat' => 6.4474,
                'lng' => 3.4736,
            ])
            ->assertCreated()
            ->json('data.id');

        $incidentId = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/v1/command/incidents/route', [
                'category' => 'crime',
                'severity' => 'critical',
                'summary' => 'Armed robbery in progress',
                'lat' => 6.4480,
                'lng' => 3.4740,
                'source_type' => 'sos',
            ])
            ->assertCreated()
            ->json('data.id');

        return [$admin, $agencyId, $divisionalId, $incidentId];
    }

    public function test_super_admin_can_create_a_unit(): void
    {
        [$admin, $agencyId, $commandId] = $this->seedStructureWithIncident();

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/v1/command/units', [
                'command_id' => $commandId,
                'call_sign' => 'NPF-PATROL-1',
                'kind' => 'patrol',
                'lat' => 6.4478,
                'lng' => 3.4738,
            ])
            ->assertCreated()
            ->assertJsonPath('data.call_sign', 'NPF-PATROL-1')
            ->assertJsonPath('data.kind', 'patrol')
            ->assertJsonPath('data.status', 'available')
            ->assertJsonPath('data.agency_id', $agencyId); // denormalized from command

        $this->actingAs($admin, 'sanctum')
            ->getJson("/api/v1/command/units?command_id={$commandId}")
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_closest_units_returns_the_available_same_agency_unit_with_a_distance(): void
    {
        [$admin, , $commandId, $incidentId] = $this->seedStructureWithIncident();

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/v1/command/units', [
                'command_id' => $commandId,
                'call_sign' => 'NPF-PATROL-1',
                'kind' => 'patrol',
                'lat' => 6.4478,
                'lng' => 3.4738,
            ])
            ->assertCreated();

        $response = $this->actingAs($admin, 'sanctum')
            ->getJson("/api/v1/command/incidents/{$incidentId}/closest-units")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.unit.call_sign', 'NPF-PATROL-1')
            ->assertJsonPath('data.0.kind_match', true);

        $this->assertNotNull($response->json('data.0.distance_km'));
    }

    public function test_dispatch_assigns_the_unit_and_advances_the_incident(): void
    {
        [$admin, , $commandId, $incidentId] = $this->seedStructureWithIncident();

        $unitId = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/v1/command/units', [
                'command_id' => $commandId,
                'call_sign' => 'NPF-PATROL-1',
                'kind' => 'patrol',
                'lat' => 6.4478,
                'lng' => 3.4738,
            ])
            ->json('data.id');

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/command/incidents/{$incidentId}/dispatch", ['unit_id' => $unitId])
            ->assertCreated()
            ->assertJsonPath('data.unit_id', $unitId)
            ->assertJsonPath('data.command_incident_id', $incidentId);

        // Unit is now assigned to the incident, and a dispatch record exists.
        $this->actingAs($admin, 'sanctum')
            ->getJson("/api/v1/command/units?command_id={$commandId}")
            ->assertOk()
            ->assertJsonPath('data.0.status', 'assigned')
            ->assertJsonPath('data.0.assigned_incident_id', $incidentId);

        $this->assertDatabaseHas('unit_dispatches', [
            'unit_id' => $unitId,
            'command_incident_id' => $incidentId,
        ]);

        // The incident is no longer 'new'.
        $status = $this->actingAs($admin, 'sanctum')
            ->getJson("/api/v1/command/incidents/{$incidentId}")
            ->assertOk()
            ->json('data.status');

        $this->assertNotSame('new', $status);
    }

    public function test_dispatching_an_already_assigned_unit_is_rejected(): void
    {
        [$admin, , $commandId, $incidentId] = $this->seedStructureWithIncident();

        $unitId = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/v1/command/units', [
                'command_id' => $commandId,
                'call_sign' => 'NPF-PATROL-1',
                'kind' => 'patrol',
            ])
            ->json('data.id');

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/command/incidents/{$incidentId}/dispatch", ['unit_id' => $unitId])
            ->assertCreated();

        // Second dispatch of the same (now assigned) unit → 422.
        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/command/incidents/{$incidentId}/dispatch", ['unit_id' => $unitId])
            ->assertStatus(422);
    }

    public function test_dispatching_a_unit_from_a_different_agency_is_rejected(): void
    {
        [$admin, , , $incidentId] = $this->seedStructureWithIncident();

        // A second agency (FRSC) with its own command + unit.
        $frscId = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/v1/command/agencies', [
                'code' => 'FRSC',
                'name' => 'Federal Road Safety Corps',
                'country' => 'NG',
                'categories' => ['traffic'],
            ])
            ->json('data.id');

        $frscCommandId = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/v1/command/commands', [
                'agency_id' => $frscId,
                'tier' => 'national',
                'name' => 'FRSC HQ',
                'area' => 'Abuja',
            ])
            ->json('data.id');

        $foreignUnitId = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/v1/command/units', [
                'command_id' => $frscCommandId,
                'call_sign' => 'FRSC-TRAFFIC-1',
                'kind' => 'traffic',
            ])
            ->json('data.id');

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/command/incidents/{$incidentId}/dispatch", ['unit_id' => $foreignUnitId])
            ->assertStatus(422);
    }

    public function test_issue_list_and_clear_a_bolo(): void
    {
        [$admin, $agencyId, $commandId] = $this->seedStructureWithIncident();

        $boloId = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/v1/command/bolos', [
                'command_id' => $commandId,
                'kind' => 'vehicle',
                'subject' => 'Black SUV plate ABC-123 wanted',
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.agency_id', $agencyId)
            ->json('data.id');

        $this->actingAs($admin, 'sanctum')
            ->getJson("/api/v1/command/bolos?agency_id={$agencyId}")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $boloId);

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/command/bolos/{$boloId}/clear")
            ->assertOk()
            ->assertJsonPath('data.status', 'cleared');

        // Default list (active only) is now empty.
        $this->actingAs($admin, 'sanctum')
            ->getJson("/api/v1/command/bolos?agency_id={$agencyId}")
            ->assertOk()
            ->assertJsonCount(0, 'data');

        // Clear is idempotent.
        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/command/bolos/{$boloId}/clear")
            ->assertOk()
            ->assertJsonPath('data.status', 'cleared');
    }

    public function test_non_super_admin_is_forbidden(): void
    {
        [, , $commandId, $incidentId] = $this->seedStructureWithIncident();
        $outsider = User::factory()->create();

        $this->actingAs($outsider, 'sanctum')
            ->getJson('/api/v1/command/units')
            ->assertForbidden();

        $this->actingAs($outsider, 'sanctum')
            ->postJson('/api/v1/command/units', [
                'command_id' => $commandId,
                'call_sign' => 'X-1',
            ])
            ->assertForbidden();

        $this->actingAs($outsider, 'sanctum')
            ->getJson("/api/v1/command/incidents/{$incidentId}/closest-units")
            ->assertForbidden();

        $this->actingAs($outsider, 'sanctum')
            ->postJson('/api/v1/command/bolos', [
                'command_id' => $commandId,
                'subject' => 'X',
            ])
            ->assertForbidden();
    }
}
