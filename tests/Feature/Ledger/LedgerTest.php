<?php

declare(strict_types=1);

namespace Tests\Feature\Ledger;

use App\Domains\Authorization\Database\Seeders\PermissionCatalogueSeeder;
use App\Domains\Authorization\Services\RoleService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class LedgerTest extends TestCase
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
     * Onboard a source as SuperAdmin and return [admin, sourceId, rawKey].
     *
     * @return array{0: User, 1: string, 2: string}
     */
    private function onboardSource(): array
    {
        $admin = $this->superAdmin();

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/v1/ledger/sources', [
                'name' => 'Fleet Telematics',
                'kind' => 'product',
                'product' => 'Sentrix Fleet',
            ])
            ->assertCreated()
            ->assertJsonPath('data.source.status', 'pending');

        $sourceId = $response->json('data.source.id');
        $rawKey = $response->json('data.ingest_key');

        $this->assertIsString($rawKey);
        $this->assertNotEmpty($rawKey);

        return [$admin, $sourceId, $rawKey];
    }

    public function test_onboard_activate_then_ingest_appears_in_feed_and_bumps_stats(): void
    {
        [$admin, $sourceId, $rawKey] = $this->onboardSource();

        // Activate the source.
        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/ledger/sources/{$sourceId}/activate")
            ->assertOk()
            ->assertJsonPath('data.status', 'active');

        // Ingest a write with the X-Ledger-Key header (NOT sanctum).
        $this->withHeaders(['X-Ledger-Key' => $rawKey])
            ->postJson('/api/v1/ledger/ingest', [
                'type' => 'telemetry',
                'summary' => 'GPS batch · 4 vehicles',
                'ref' => 'veh_1',
            ])
            ->assertCreated()
            ->assertJsonPath('data.type', 'telemetry');

        // It appears in the feed.
        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/v1/ledger/writes')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.type', 'telemetry');

        // It bumps stats.
        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/v1/ledger/stats')
            ->assertOk()
            ->assertJsonPath('data.total_writes', 1)
            ->assertJsonPath('data.active_sources', 1);
    }

    public function test_ingest_with_unknown_key_is_not_found(): void
    {
        $this->withHeaders(['X-Ledger-Key' => 'LKEY_does_not_exist'])
            ->postJson('/api/v1/ledger/ingest', ['type' => 'telemetry'])
            ->assertNotFound()
            ->assertJsonPath('errors.error', 'unknown_source');
    }

    public function test_ingest_to_a_suspended_source_is_conflict(): void
    {
        [$admin, $sourceId, $rawKey] = $this->onboardSource();

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/ledger/sources/{$sourceId}/activate")
            ->assertOk();

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/ledger/sources/{$sourceId}/suspend")
            ->assertOk()
            ->assertJsonPath('data.status', 'suspended');

        $this->withHeaders(['X-Ledger-Key' => $rawKey])
            ->postJson('/api/v1/ledger/ingest', ['type' => 'telemetry'])
            ->assertStatus(409)
            ->assertJsonPath('errors.error', 'source_suspended');
    }

    public function test_non_super_admin_hitting_an_admin_endpoint_is_forbidden(): void
    {
        $outsider = User::factory()->create();

        $this->actingAs($outsider, 'sanctum')
            ->getJson('/api/v1/ledger/stats')
            ->assertForbidden();

        $this->actingAs($outsider, 'sanctum')
            ->postJson('/api/v1/ledger/sources', [
                'name' => 'Rogue Feed',
                'kind' => 'integration',
            ])
            ->assertForbidden();
    }
}
