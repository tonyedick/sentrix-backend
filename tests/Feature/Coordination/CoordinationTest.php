<?php

declare(strict_types=1);

namespace Tests\Feature\Coordination;

use App\Domains\Authorization\Database\Seeders\PermissionCatalogueSeeder;
use App\Domains\Authorization\Services\RoleService;
use App\Domains\Cad\Models\Unit;
use App\Domains\Cad\Models\UnitDispatch;
use App\Domains\Command\Models\Agency;
use App\Domains\Command\Models\Command;
use App\Domains\Command\Models\CommandIncident;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Coordination: mutual aid, unit comms, command analytics, taskings + duty book.
 */
final class CoordinationTest extends TestCase
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
     * @return array{agency: Agency, command: Command, command2: Command, incident: CommandIncident, unit: Unit}
     */
    private function commandGraph(): array
    {
        $agency = Agency::create([
            'code' => 'NPF', 'name' => 'Police', 'country' => 'NG',
            'categories' => ['crime'], 'status' => 'active',
        ]);
        $command = Command::create([
            'agency_id' => $agency->getKey(), 'tier' => 'national',
            'name' => 'Force HQ', 'lat' => 6.5, 'lng' => 3.3,
        ]);
        $command2 = Command::create([
            'agency_id' => $agency->getKey(), 'tier' => 'state',
            'name' => 'Lagos Command', 'lat' => 6.6, 'lng' => 3.4,
        ]);
        $incident = CommandIncident::create([
            'command_id' => $command->getKey(), 'agency_id' => $agency->getKey(),
            'category' => 'crime', 'severity' => 'high', 'status' => 'new',
            'source_type' => 'manual', 'summary' => 'Break-in', 'lat' => 6.5, 'lng' => 3.3,
            'opened_at' => now()->subMinutes(3), 'sla_dispatch_due_at' => now()->addMinutes(2),
        ]);
        $unit = Unit::create([
            'command_id' => $command->getKey(), 'agency_id' => $agency->getKey(),
            'call_sign' => 'A1', 'kind' => 'patrol', 'capabilities' => [], 'crew' => 2,
            'status' => 'available',
        ]);

        return compact('agency', 'command', 'command2', 'incident', 'unit');
    }

    public function test_mutual_aid_request_then_accept(): void
    {
        $admin = $this->superAdmin();
        $g = $this->commandGraph();

        $id = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/v1/command/mutual-aid', [
                'command_incident_id' => $g['incident']->getKey(),
                'requesting_command_id' => $g['command']->getKey(),
                'responding_command_id' => $g['command2']->getKey(),
                'message' => 'Need an extra unit',
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'requested')
            ->json('data.id');

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/command/mutual-aid/{$id}/accept")
            ->assertOk()
            ->assertJsonPath('data.status', 'accepted');
    }

    public function test_unit_message_thread(): void
    {
        $admin = $this->superAdmin();
        $g = $this->commandGraph();

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/command/units/{$g['unit']->getKey()}/messages", ['body' => 'Proceed to scene'])
            ->assertCreated()
            ->assertJsonPath('data.direction', 'dispatch_to_unit');

        $this->actingAs($admin, 'sanctum')
            ->getJson("/api/v1/command/units/{$g['unit']->getKey()}/messages")
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_analytics_returns_aggregate(): void
    {
        $admin = $this->superAdmin();
        $g = $this->commandGraph();

        // A dispatch before the SLA due time → 100% dispatch compliance.
        UnitDispatch::create([
            'unit_id' => $g['unit']->getKey(),
            'command_incident_id' => $g['incident']->getKey(),
            'dispatched_at' => now()->subMinutes(2),
        ]);

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/v1/command/analytics')
            ->assertOk()
            ->assertJsonPath('data.incidents.total', 1)
            // A whole-number percentage serializes to JSON as 100 (no .0), so assert the int.
            ->assertJsonPath('data.sla_dispatch.compliance_pct', 100);
    }

    public function test_tasking_lifecycle_and_illegal_resolve(): void
    {
        $admin = $this->superAdmin();

        $id = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/v1/command/taskings', ['title' => 'Review camera 7', 'kind' => 'detection'])
            ->assertCreated()
            ->assertJsonPath('data.status', 'sent')
            ->json('data.id');

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/command/taskings/{$id}/ack")
            ->assertOk()
            ->assertJsonPath('data.status', 'acknowledged');

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/command/taskings/{$id}/resolve")
            ->assertOk()
            ->assertJsonPath('data.status', 'resolved');

        // Resolving an already-resolved tasking is rejected.
        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/command/taskings/{$id}/resolve")
            ->assertStatus(422);
    }

    public function test_duty_recorded_and_listed(): void
    {
        $admin = $this->superAdmin();
        $g = $this->commandGraph();

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/v1/command/duty', [
                'action' => 'sign_in',
                'scope_type' => 'command',
                'scope_id' => $g['command']->getKey(),
                'person_name' => 'Officer Ada',
                'role' => 'watch_officer',
            ])
            ->assertCreated()
            ->assertJsonPath('data.action', 'sign_in');

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/v1/command/duty')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_non_superadmin_is_forbidden(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/command/taskings', ['title' => 'x'])
            ->assertForbidden();
    }
}
