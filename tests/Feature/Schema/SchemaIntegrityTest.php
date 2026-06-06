<?php

declare(strict_types=1);

namespace Tests\Feature\Schema;

use App\Domains\Authorization\Database\Seeders\PermissionCatalogueSeeder;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Verifies the database-level integrity layer (CHECK constraints + indexes).
 * PostgreSQL-only; skipped on other drivers.
 */
final class SchemaIntegrityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        if (DB::getDriverName() !== 'pgsql') {
            $this->markTestSkipped('Schema integrity constraints are PostgreSQL-specific.');
        }

        $this->seed(PermissionCatalogueSeeder::class);
    }

    /**
     * @return array{0: User, 1: string}
     */
    private function ownerWithOrganization(): array
    {
        $owner = User::factory()->create();
        $organizationId = $this->actingAs($owner, 'sanctum')
            ->postJson('/api/v1/organizations', ['name' => 'Acme Inc'])
            ->json('data.id');

        return [$owner, $organizationId];
    }

    public function test_an_invalid_trip_status_is_rejected_by_the_database(): void
    {
        [$owner, $organizationId] = $this->ownerWithOrganization();

        $this->expectException(QueryException::class);

        DB::table('trips')->insert([
            'id' => (string) Str::orderedUuid(),
            'organization_id' => $organizationId,
            'user_id' => $owner->id,
            'status' => 'not-a-real-status',
        ]);
    }

    public function test_an_invalid_emergency_severity_is_rejected_by_the_database(): void
    {
        [$owner, $organizationId] = $this->ownerWithOrganization();

        $this->expectException(QueryException::class);

        DB::table('emergencies')->insert([
            'id' => (string) Str::orderedUuid(),
            'organization_id' => $organizationId,
            'user_id' => $owner->id,
            'status' => 'triggered',
            'severity' => 'catastrophic',
            'triggered_at' => now(),
        ]);
    }

    public function test_foreign_keys_and_hot_paths_are_indexed(): void
    {
        $indexes = collect(DB::select("SELECT indexname FROM pg_indexes WHERE schemaname = 'public'"))
            ->pluck('indexname')
            ->all();

        // A sample of the previously-unindexed foreign keys.
        $this->assertContains('organizations_owner_id_index', $indexes);
        $this->assertContains('emergencies_user_id_index', $indexes);
        $this->assertContains('incidents_opened_by_index', $indexes);

        // Partial indexes for the live boards / overdue sweep.
        $this->assertContains('trips_overdue_sweep_index', $indexes);
        $this->assertContains('emergencies_live_per_org_index', $indexes);
    }
}
