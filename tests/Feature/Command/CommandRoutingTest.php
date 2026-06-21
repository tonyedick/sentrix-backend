<?php

declare(strict_types=1);

namespace Tests\Feature\Command;

use App\Domains\Authorization\Database\Seeders\PermissionCatalogueSeeder;
use App\Domains\Authorization\Services\RoleService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class CommandRoutingTest extends TestCase
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
     * @return array{0: User, 1: string, 2: string, 3: string}
     *   [admin, agencyId, nationalCommandId, divisionalCommandId]
     */
    private function seedNpfStructure(): array
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

        // National HQ (no coords) — the fallback for coordless / out-of-range alerts.
        $nationalId = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/v1/command/commands', [
                'agency_id' => $agencyId,
                'tier' => 'national',
                'name' => 'Force Headquarters',
                'area' => 'Abuja',
            ])
            ->assertCreated()
            ->json('data.id');

        // Divisional command WITH coords (Lekki/VI), under HQ for this slice.
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

        return [$admin, $agencyId, $nationalId, $divisionalId];
    }

    public function test_routes_a_crime_incident_to_the_nearest_divisional_command_with_sla_clocks(): void
    {
        [$admin, $agencyId, , $divisionalId] = $this->seedNpfStructure();

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/v1/command/incidents/route', [
                'category' => 'crime',
                'severity' => 'critical',
                'summary' => 'Armed robbery in progress',
                'lat' => 6.4480,   // ~75m from the divisional command
                'lng' => 3.4740,
                'source_type' => 'sos',
            ])
            ->assertCreated()
            ->assertJsonPath('data.command_id', $divisionalId)
            ->assertJsonPath('data.agency_id', $agencyId)
            ->assertJsonPath('data.status', 'new')
            ->assertJsonPath('data.category', 'crime');

        $this->assertNotNull($response->json('data.sla_dispatch_due_at'));
        $this->assertNotNull($response->json('data.sla_onscene_due_at'));
    }

    public function test_act_advances_through_the_happy_path_to_resolved(): void
    {
        [$admin] = $this->seedNpfStructure();

        $incidentId = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/v1/command/incidents/route', [
                'category' => 'crime',
                'severity' => 'high',
                'summary' => 'Break-in reported',
                'lat' => 6.4480,
                'lng' => 3.4740,
            ])
            ->json('data.id');

        foreach ([['acknowledge', 'acknowledged'], ['en_route', 'en_route'], ['on_scene', 'on_scene'], ['resolve', 'resolved']] as [$action, $expected]) {
            $this->actingAs($admin, 'sanctum')
                ->postJson("/api/v1/command/incidents/{$incidentId}/act", ['action' => $action])
                ->assertOk()
                ->assertJsonPath('data.status', $expected);
        }

        $this->actingAs($admin, 'sanctum')
            ->getJson("/api/v1/command/incidents/{$incidentId}")
            ->assertOk()
            ->assertJsonPath('data.status', 'resolved');
    }

    public function test_acting_on_a_resolved_incident_is_rejected(): void
    {
        [$admin] = $this->seedNpfStructure();

        $incidentId = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/v1/command/incidents/route', [
                'category' => 'crime',
                'severity' => 'medium',
                'summary' => 'Suspicious loitering',
                'lat' => 6.4480,
                'lng' => 3.4740,
            ])
            ->json('data.id');

        foreach (['acknowledge', 'resolve'] as $action) {
            $this->actingAs($admin, 'sanctum')
                ->postJson("/api/v1/command/incidents/{$incidentId}/act", ['action' => $action])
                ->assertOk();
        }

        // resolved is terminal — any further action is a 422.
        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/command/incidents/{$incidentId}/act", ['action' => 'en_route'])
            ->assertStatus(422);
    }

    public function test_non_super_admin_is_forbidden(): void
    {
        $this->seedNpfStructure();
        $outsider = User::factory()->create();

        $this->actingAs($outsider, 'sanctum')
            ->getJson('/api/v1/command/overview')
            ->assertForbidden();

        $this->actingAs($outsider, 'sanctum')
            ->postJson('/api/v1/command/agencies', [
                'code' => 'XXX',
                'name' => 'Rogue',
                'categories' => ['crime'],
            ])
            ->assertForbidden();
    }

    public function test_resolve_returns_the_onboarded_agency_by_key(): void
    {
        [$admin, $agencyId] = $this->seedNpfStructure();

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/v1/command/agencies/resolve?key=npf')
            ->assertOk()
            ->assertJsonPath('data.matched', true)
            ->assertJsonPath('data.agency.id', $agencyId)
            ->assertJsonPath('data.agency.code', 'NPF');
    }
}
