<?php

declare(strict_types=1);

namespace Tests\Feature\Insurance;

use App\Domains\Authorization\Database\Seeders\PermissionCatalogueSeeder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class InsuranceWorkflowTest extends TestCase
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
        $organizationId = $this->actingAs($owner, 'sanctum')
            ->postJson('/api/v1/organizations', ['name' => 'Acme Inc'])
            ->json('data.id');

        return [$owner, $organizationId];
    }

    public function test_risk_endpoint_returns_a_score_and_band(): void
    {
        [$owner, $org] = $this->ownerWithOrganization();

        $response = $this->actingAs($owner, 'sanctum')
            ->getJson("/api/v1/organizations/{$org}/insurance/risk")
            ->assertOk();

        $score = $response->json('data.score');
        self::assertIsInt($score);
        self::assertGreaterThanOrEqual(0, $score);
        self::assertLessThanOrEqual(100, $score);
        self::assertContains($response->json('data.band'), ['low', 'moderate', 'high']);
        $response->assertJsonPath('data.factors.window_days', 90);
    }

    public function test_quote_endpoint_returns_a_premium_and_saving(): void
    {
        [$owner, $org] = $this->ownerWithOrganization();

        $response = $this->actingAs($owner, 'sanctum')
            ->postJson("/api/v1/organizations/{$org}/insurance/quote")
            ->assertOk();

        self::assertIsInt($response->json('data.premium_cents'));
        self::assertIsInt($response->json('data.sentrix_saving_cents'));
        $response->assertJsonPath('data.currency', 'USD');
    }

    public function test_policy_can_be_created_and_a_claim_filed_and_decided(): void
    {
        [$owner, $org] = $this->ownerWithOrganization();

        $policyId = $this->actingAs($owner, 'sanctum')
            ->postJson("/api/v1/organizations/{$org}/insurance/policies", [
                'title' => 'Annual liability cover',
                'premium_cents' => 900_000,
                'currency' => 'USD',
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'draft')
            ->json('data.id');

        $this->actingAs($owner, 'sanctum')
            ->getJson("/api/v1/organizations/{$org}/insurance/policies")
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $claimId = $this->actingAs($owner, 'sanctum')
            ->postJson("/api/v1/organizations/{$org}/insurance/claims", [
                'policy_id' => $policyId,
                'amount_cents' => 150_000,
                'description' => 'Break-in at site B, evidence attached.',
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'filed')
            ->json('data.id');

        $this->actingAs($owner, 'sanctum')
            ->postJson("/api/v1/organizations/{$org}/insurance/claims/{$claimId}/decide", [
                'decision' => 'approved',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'approved');

        $this->assertDatabaseHas('insurance_claims', ['id' => $claimId, 'status' => 'approved']);
    }

    public function test_deciding_an_already_decided_claim_is_rejected(): void
    {
        [$owner, $org] = $this->ownerWithOrganization();

        $policyId = $this->actingAs($owner, 'sanctum')
            ->postJson("/api/v1/organizations/{$org}/insurance/policies", [
                'title' => 'Cover',
                'premium_cents' => 100_000,
            ])
            ->json('data.id');

        $claimId = $this->actingAs($owner, 'sanctum')
            ->postJson("/api/v1/organizations/{$org}/insurance/claims", [
                'policy_id' => $policyId,
                'amount_cents' => 50_000,
            ])
            ->json('data.id');

        $this->actingAs($owner, 'sanctum')
            ->postJson("/api/v1/organizations/{$org}/insurance/claims/{$claimId}/decide", ['decision' => 'approved'])
            ->assertOk();

        // A decided claim can no longer be decided.
        $this->actingAs($owner, 'sanctum')
            ->postJson("/api/v1/organizations/{$org}/insurance/claims/{$claimId}/decide", ['decision' => 'rejected'])
            ->assertStatus(422);
    }

    public function test_outsider_is_forbidden(): void
    {
        [, $org] = $this->ownerWithOrganization();
        $outsider = User::factory()->create();

        $this->actingAs($outsider, 'sanctum')
            ->getJson("/api/v1/organizations/{$org}/insurance/risk")
            ->assertForbidden();

        $this->actingAs($outsider, 'sanctum')
            ->postJson("/api/v1/organizations/{$org}/insurance/policies", [
                'title' => 'X',
                'premium_cents' => 1,
            ])
            ->assertForbidden();
    }
}
