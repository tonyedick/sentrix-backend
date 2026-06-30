<?php

declare(strict_types=1);

namespace Tests\Feature\Core;

use App\Domains\Authorization\Database\Seeders\PermissionCatalogueSeeder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The SentrixCore tool gateway: POST /api/tools/{name} (X-Service-Token authed)
 * answers Core's agent tools with live, org-scoped data. This is what makes the
 * assistant's read tools operate on real incidents instead of honest-failing.
 */
final class CoreToolGatewayTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionCatalogueSeeder::class);
        config(['sentrix.core.api_key' => 'svc-secret']);
    }

    /** Create an org (via API) with one open incident; return [orgId]. */
    private function orgWithIncident(): string
    {
        $owner = User::factory()->create();
        $orgId = $this->actingAs($owner, 'sanctum')
            ->postJson('/api/v1/organizations', ['name' => 'Acme Inc'])
            ->json('data.id');

        $this->actingAs($owner, 'sanctum')
            ->postJson("/api/v1/organizations/{$orgId}/incidents", [
                'title' => 'Perimeter breach at Gate B',
                'severity' => 'high',
            ])
            ->assertCreated();

        return $orgId;
    }

    public function test_get_alerts_returns_live_incidents_for_the_org(): void
    {
        $orgId = $this->orgWithIncident();

        $this->withHeaders(['X-Service-Token' => 'svc-secret'])
            ->postJson('/api/tools/get_alerts', ['orgId' => $orgId, 'args' => []])
            ->assertOk()
            ->assertJsonPath('data.count', 1)
            ->assertJsonPath('data.alerts.0.kind', 'incident')
            ->assertJsonPath('data.alerts.0.title', 'Perimeter breach at Gate B')
            ->assertJsonPath('data.alerts.0.severity', 'high');
    }

    public function test_command_overview_returns_live_aggregate(): void
    {
        $orgId = $this->orgWithIncident();

        $this->withHeaders(['X-Service-Token' => 'svc-secret'])
            ->postJson('/api/tools/command_overview', ['orgId' => $orgId])
            ->assertOk()
            ->assertJsonPath('data.open_incidents_total', 1)
            ->assertJsonPath('data.open_incidents_by_severity.high', 1);
    }

    public function test_unknown_tenant_returns_empty_but_valid_payload(): void
    {
        $this->withHeaders(['X-Service-Token' => 'svc-secret'])
            ->postJson('/api/tools/get_alerts', ['orgId' => 'no-such-org', 'args' => []])
            ->assertOk()
            ->assertJsonPath('data.count', 0)
            ->assertJsonPath('data.alerts', []);
    }

    public function test_unhandled_tool_is_404_so_core_degrades_gracefully(): void
    {
        $this->withHeaders(['X-Service-Token' => 'svc-secret'])
            ->postJson('/api/tools/teleport', ['orgId' => 'acme'])
            ->assertNotFound();
    }

    public function test_requires_the_service_token(): void
    {
        $this->postJson('/api/tools/get_alerts', ['orgId' => 'acme'])
            ->assertUnauthorized();
    }
}
