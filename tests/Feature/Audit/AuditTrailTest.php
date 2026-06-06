<?php

declare(strict_types=1);

namespace Tests\Feature\Audit;

use App\Domains\Authorization\Database\Seeders\PermissionCatalogueSeeder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class AuditTrailTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionCatalogueSeeder::class);
    }

    public function test_operational_events_write_audit_rows_and_are_listable(): void
    {
        $owner = User::factory()->create();
        $organizationId = $this->actingAs($owner, 'sanctum')
            ->postJson('/api/v1/organizations', ['name' => 'Acme Inc'])
            ->json('data.id');

        // Creating the organization should already have left a trail entry.
        $this->assertDatabaseHas('audit_logs', [
            'organization_id' => $organizationId,
            'action' => 'organization.created',
        ]);

        $this->actingAs($owner, 'sanctum')
            ->postJson("/api/v1/organizations/{$organizationId}/emergencies", ['severity' => 'critical'])
            ->assertCreated();

        $this->assertDatabaseHas('audit_logs', [
            'organization_id' => $organizationId,
            'action' => 'emergency.triggered',
        ]);

        $actions = $this->actingAs($owner, 'sanctum')
            ->getJson("/api/v1/organizations/{$organizationId}/audit-logs")
            ->assertOk()
            ->json('data.*.action');

        $this->assertContains('emergency.triggered', $actions);
    }
}
