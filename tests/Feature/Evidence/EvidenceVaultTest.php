<?php

declare(strict_types=1);

namespace Tests\Feature\Evidence;

use App\Domains\Authorization\Database\Seeders\PermissionCatalogueSeeder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class EvidenceVaultTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionCatalogueSeeder::class);
    }

    /**
     * @return array{0: User, 1: string}
     */
    private function ownerWithOrganization(): array
    {
        $owner = User::factory()->create();
        $org = $this->actingAs($owner, 'sanctum')
            ->postJson('/api/v1/organizations', ['name' => 'Acme'])
            ->json('data.id');

        return [$owner, $org];
    }

    public function test_owner_can_batch_observe(): void
    {
        [$owner, $org] = $this->ownerWithOrganization();

        $response = $this->actingAs($owner, 'sanctum')
            ->postJson("/api/v1/organizations/{$org}/evidence/observe", [
                'observations' => [
                    ['kind' => 'face', 'label' => 'man in red jacket', 'attributes' => ['color' => 'red']],
                    ['kind' => 'vehicle', 'plate' => 'KJA-456-CV', 'attributes' => ['make' => 'Toyota', 'color' => 'black']],
                    ['kind' => 'audio', 'severity' => 'critical', 'attributes' => ['soundType' => 'gunshot']],
                ],
            ])
            ->assertCreated()
            ->assertJsonPath('data.recorded', 3);

        $this->assertCount(3, $response->json('data.ids'));
    }

    public function test_search_filters_by_kind(): void
    {
        [$owner, $org] = $this->ownerWithOrganization();
        $this->seedObservations($owner, $org);

        $this->actingAs($owner, 'sanctum')
            ->getJson("/api/v1/organizations/{$org}/evidence/search?kind=vehicle")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.kind', 'vehicle');
    }

    public function test_search_filters_by_plate(): void
    {
        [$owner, $org] = $this->ownerWithOrganization();
        $this->seedObservations($owner, $org);

        $this->actingAs($owner, 'sanctum')
            ->getJson("/api/v1/organizations/{$org}/evidence/search?plate=KJA456CV")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.plate', 'KJA-456-CV');
    }

    public function test_search_filters_by_label_substring(): void
    {
        [$owner, $org] = $this->ownerWithOrganization();
        $this->seedObservations($owner, $org);

        // Exercises the `q` facet (label ILIKE '%term%'), backed by the
        // observations_label_trgm trigram index.
        $this->actingAs($owner, 'sanctum')
            ->getJson("/api/v1/organizations/{$org}/evidence/search?q=red+jacket")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.label', 'man in red jacket');
    }

    public function test_search_filters_by_date_range(): void
    {
        [$owner, $org] = $this->ownerWithOrganization();

        $this->actingAs($owner, 'sanctum')
            ->postJson("/api/v1/organizations/{$org}/evidence/observe", [
                'observations' => [
                    ['kind' => 'scene', 'observed_at' => '2026-01-01T10:00:00Z'],
                    ['kind' => 'scene', 'observed_at' => '2026-06-01T10:00:00Z'],
                ],
            ])
            ->assertCreated();

        $this->actingAs($owner, 'sanctum')
            ->getJson("/api/v1/organizations/{$org}/evidence/search?from=2026-05-01T00:00:00Z&to=2026-07-01T00:00:00Z")
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_vehicle_journey_returns_the_trail_across_cameras(): void
    {
        [$owner, $org] = $this->ownerWithOrganization();

        $this->actingAs($owner, 'sanctum')
            ->postJson("/api/v1/organizations/{$org}/evidence/observe", [
                'observations' => [
                    ['kind' => 'plate', 'plate' => 'ABC-12-DE', 'lat' => 6.44, 'lng' => 3.42, 'observed_at' => '2026-06-01T10:00:00Z'],
                    ['kind' => 'plate', 'plate' => 'ABC-12-DE', 'lat' => 6.50, 'lng' => 3.50, 'observed_at' => '2026-06-01T11:00:00Z'],
                    ['kind' => 'plate', 'plate' => 'OTHER-99', 'observed_at' => '2026-06-01T12:00:00Z'],
                ],
            ])
            ->assertCreated();

        $this->actingAs($owner, 'sanctum')
            ->getJson("/api/v1/organizations/{$org}/evidence/vehicle/ABC-12-DE")
            ->assertOk()
            ->assertJsonPath('data.plate', 'ABC-12-DE')
            ->assertJsonPath('data.sightings', 2)
            ->assertJsonCount(2, 'data.trail');
    }

    public function test_stats_returns_the_rollup_keys(): void
    {
        [$owner, $org] = $this->ownerWithOrganization();
        $this->seedObservations($owner, $org);

        $this->actingAs($owner, 'sanctum')
            ->getJson("/api/v1/organizations/{$org}/evidence/stats")
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'total',
                    'by_kind',
                    'by_retention_tier',
                    'on_legal_hold',
                    'bookmarked',
                    'awaiting_review',
                ],
            ])
            ->assertJsonPath('data.total', 3);
    }

    public function test_hold_toggles_and_held_observation_shows_in_status_search(): void
    {
        [$owner, $org] = $this->ownerWithOrganization();

        $id = $this->actingAs($owner, 'sanctum')
            ->postJson("/api/v1/organizations/{$org}/evidence/observe", [
                'observations' => [['kind' => 'object', 'label' => 'green bag']],
            ])
            ->json('data.ids.0');

        $this->actingAs($owner, 'sanctum')
            ->postJson("/api/v1/organizations/{$org}/evidence/{$id}/hold", ['hold' => true])
            ->assertOk()
            ->assertJsonPath('data.hold', true);

        $this->actingAs($owner, 'sanctum')
            ->getJson("/api/v1/organizations/{$org}/evidence/search?status=hold")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $id);
    }

    public function test_outsider_cannot_observe(): void
    {
        [, $org] = $this->ownerWithOrganization();
        $outsider = User::factory()->create();

        $this->actingAs($outsider, 'sanctum')
            ->postJson("/api/v1/organizations/{$org}/evidence/observe", [
                'observations' => [['kind' => 'face']],
            ])
            ->assertForbidden();
    }

    public function test_cross_org_observation_returns_404_on_hold(): void
    {
        [$owner, $org] = $this->ownerWithOrganization();
        [$otherOwner, $otherOrg] = $this->ownerWithOrganization();

        $foreignId = $this->actingAs($otherOwner, 'sanctum')
            ->postJson("/api/v1/organizations/{$otherOrg}/evidence/observe", [
                'observations' => [['kind' => 'face']],
            ])
            ->json('data.ids.0');

        // Owner of $org tries to hold an observation that belongs to $otherOrg.
        $this->actingAs($owner, 'sanctum')
            ->postJson("/api/v1/organizations/{$org}/evidence/{$foreignId}/hold", ['hold' => true])
            ->assertNotFound();
    }

    private function seedObservations(User $owner, string $org): void
    {
        $this->actingAs($owner, 'sanctum')
            ->postJson("/api/v1/organizations/{$org}/evidence/observe", [
                'observations' => [
                    ['kind' => 'face', 'label' => 'man in red jacket', 'attributes' => ['color' => 'red']],
                    ['kind' => 'vehicle', 'plate' => 'KJA-456-CV', 'attributes' => ['make' => 'Toyota']],
                    ['kind' => 'audio', 'severity' => 'critical'],
                ],
            ])
            ->assertCreated();
    }
}
