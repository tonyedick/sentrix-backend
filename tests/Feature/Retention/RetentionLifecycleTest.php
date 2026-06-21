<?php

declare(strict_types=1);

namespace Tests\Feature\Retention;

use App\Domains\Authorization\Database\Seeders\PermissionCatalogueSeeder;
use App\Domains\Evidence\Models\Observation;
use App\Domains\Evidence\Support\Enums\ObservationKind;
use App\Domains\Evidence\Support\Enums\RetentionTier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Storage lifecycle (Retention) over Evidence's observations: usage rollup,
 * the re-tiering sweep (which must never touch legal holds), archive-first
 * export (seal + mark archived), and archived-first purge.
 *
 * Default plan is `business` (hot 30d, warm 60d, cold 0d): an observation aged
 * 120 days is older than the warm ceiling (90d), so the sweep moves it to cold.
 */
final class RetentionLifecycleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionCatalogueSeeder::class);
    }

    /** @return array{0: User, 1: string} */
    private function ownerWithOrganization(): array
    {
        $owner = User::factory()->create();
        $org = $this->actingAs($owner, 'sanctum')
            ->postJson('/api/v1/organizations', ['name' => 'Acme'])
            ->json('data.id');

        return [$owner, $org];
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makeObservation(string $orgId, array $overrides = []): Observation
    {
        return Observation::create(array_merge([
            'organization_id' => $orgId,
            'kind' => ObservationKind::Vehicle->value,
            'observed_at' => Carbon::now(),
            'retention_tier' => RetentionTier::Hot->value,
            'hold' => false,
            'sealed' => false,
        ], $overrides));
    }

    public function test_storage_rollup_returns_counts_plan_and_quota(): void
    {
        [$owner, $org] = $this->ownerWithOrganization();

        $this->makeObservation($org, ['observed_at' => Carbon::now()]);
        $this->makeObservation($org, [
            'observed_at' => Carbon::now()->subDays(120),
            'retention_tier' => RetentionTier::Cold->value,
            'attributes' => ['bytes' => 1024],
        ]);
        $this->makeObservation($org, ['hold' => true]);

        $this->actingAs($owner, 'sanctum')
            ->getJson("/api/v1/organizations/{$org}/storage")
            ->assertOk()
            ->assertJsonPath('data.plan', 'business')
            ->assertJsonPath('data.quota_gb', 2048)
            ->assertJsonPath('data.total', 3)
            ->assertJsonPath('data.on_legal_hold', 1)
            ->assertJsonPath('data.estimated_bytes', 1024)
            ->assertJsonPath('data.counts_by_tier.hot', 2)
            ->assertJsonPath('data.counts_by_tier.cold', 1);
    }

    public function test_sweep_retiers_old_observation_and_leaves_held_one_untouched(): void
    {
        [$owner, $org] = $this->ownerWithOrganization();

        $old = $this->makeObservation($org, ['observed_at' => Carbon::now()->subDays(120)]);
        $heldOld = $this->makeObservation($org, [
            'observed_at' => Carbon::now()->subDays(120),
            'hold' => true,
        ]);

        $this->actingAs($owner, 'sanctum')
            ->postJson("/api/v1/organizations/{$org}/retention/sweep")
            ->assertOk()
            ->assertJsonPath('data.moved.cold', 1);

        $this->assertSame(RetentionTier::Cold->value, $old->fresh()->retention_tier->value);
        // The legal hold is never re-tiered.
        $this->assertSame(RetentionTier::Hot->value, $heldOld->fresh()->retention_tier->value);
    }

    public function test_archive_export_seals_cold_non_hold_observations_and_returns_manifest(): void
    {
        [$owner, $org] = $this->ownerWithOrganization();

        $cold = $this->makeObservation($org, [
            'observed_at' => Carbon::now()->subDays(120),
            'retention_tier' => RetentionTier::Cold->value,
        ]);
        $coldHeld = $this->makeObservation($org, [
            'observed_at' => Carbon::now()->subDays(120),
            'retention_tier' => RetentionTier::Cold->value,
            'hold' => true,
        ]);

        $this->actingAs($owner, 'sanctum')
            ->postJson("/api/v1/organizations/{$org}/evidence/archive/export")
            ->assertCreated()
            ->assertJsonPath('data.count', 1)
            ->assertJsonCount(1, 'data.manifest');

        $cold = $cold->fresh();
        $this->assertSame(RetentionTier::Archived->value, $cold->retention_tier->value);
        $this->assertTrue($cold->sealed);

        // The held cold observation is excluded from the archive.
        $coldHeld = $coldHeld->fresh();
        $this->assertSame(RetentionTier::Cold->value, $coldHeld->retention_tier->value);
        $this->assertFalse($coldHeld->sealed);
    }

    public function test_purge_deletes_archived_non_hold_and_held_survives(): void
    {
        [$owner, $org] = $this->ownerWithOrganization();

        $archived = $this->makeObservation($org, [
            'retention_tier' => RetentionTier::Archived->value,
            'sealed' => true,
        ]);
        $archivedHeld = $this->makeObservation($org, [
            'retention_tier' => RetentionTier::Archived->value,
            'sealed' => true,
            'hold' => true,
        ]);

        $this->actingAs($owner, 'sanctum')
            ->postJson("/api/v1/organizations/{$org}/evidence/purge")
            ->assertOk()
            ->assertJsonPath('data.purged', 1);

        $this->assertDatabaseMissing('observations', ['id' => $archived->getKey()]);
        $this->assertDatabaseHas('observations', ['id' => $archivedHeld->getKey()]);
    }

    public function test_outsider_is_forbidden(): void
    {
        [, $org] = $this->ownerWithOrganization();
        $outsider = User::factory()->create();

        $this->actingAs($outsider, 'sanctum')
            ->getJson("/api/v1/organizations/{$org}/storage")
            ->assertForbidden();

        $this->actingAs($outsider, 'sanctum')
            ->postJson("/api/v1/organizations/{$org}/retention/sweep")
            ->assertForbidden();
    }
}
