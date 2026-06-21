<?php

declare(strict_types=1);

namespace Tests\Feature\Access;

use App\Domains\Authorization\Database\Seeders\PermissionCatalogueSeeder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Visitor passes + gate verification: issue, scan (granted/denied), single-use
 * consumption, revocation, and the immutable gate log.
 */
final class VisitorPassGateTest extends TestCase
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
            ->postJson('/api/v1/organizations', ['name' => 'Acme Estates'])
            ->json('data.id');

        return [$owner, $organizationId];
    }

    private function issuePass(User $host, string $org, array $overrides = []): array
    {
        return $this->actingAs($host, 'sanctum')
            ->postJson("/api/v1/organizations/{$org}/passes", array_merge([
                'visitor_name' => 'Chidi (guest)',
                'type' => 'single',
            ], $overrides))
            ->assertCreated()
            ->json('data');
    }

    public function test_host_can_issue_and_list_a_pass(): void
    {
        [$owner, $org] = $this->ownerWithOrganization();

        $pass = $this->issuePass($owner, $org);

        $this->assertSame('active', $pass['status']);
        $this->assertNotEmpty($pass['code']);

        $this->actingAs($owner, 'sanctum')
            ->getJson("/api/v1/organizations/{$org}/passes")
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_single_use_pass_is_granted_once_then_consumed(): void
    {
        [$owner, $org] = $this->ownerWithOrganization();
        $code = $this->issuePass($owner, $org)['code'];

        $this->actingAs($owner, 'sanctum')
            ->postJson("/api/v1/organizations/{$org}/gate/scan", ['code' => $code])
            ->assertOk()
            ->assertJsonPath('data.result', 'granted');

        // Second entry on a single-use pass is denied as already consumed.
        $this->actingAs($owner, 'sanctum')
            ->postJson("/api/v1/organizations/{$org}/gate/scan", ['code' => $code])
            ->assertOk()
            ->assertJsonPath('data.result', 'denied')
            ->assertJsonPath('data.reason', 'consumed');

        $this->assertDatabaseHas('access_passes', ['code' => $code, 'status' => 'consumed']);
    }

    public function test_recurring_pass_allows_multiple_entries(): void
    {
        [$owner, $org] = $this->ownerWithOrganization();
        $code = $this->issuePass($owner, $org, ['type' => 'recurring'])['code'];

        foreach (range(1, 3) as $_) {
            $this->actingAs($owner, 'sanctum')
                ->postJson("/api/v1/organizations/{$org}/gate/scan", ['code' => $code])
                ->assertOk()
                ->assertJsonPath('data.result', 'granted');
        }

        $this->assertDatabaseHas('access_passes', ['code' => $code, 'status' => 'active', 'uses_count' => 3]);
    }

    public function test_revoked_pass_is_denied_at_the_gate(): void
    {
        [$owner, $org] = $this->ownerWithOrganization();
        $pass = $this->issuePass($owner, $org, ['type' => 'recurring']);

        $this->actingAs($owner, 'sanctum')
            ->postJson("/api/v1/organizations/{$org}/passes/{$pass['id']}/revoke")
            ->assertOk()
            ->assertJsonPath('data.status', 'revoked');

        $this->actingAs($owner, 'sanctum')
            ->postJson("/api/v1/organizations/{$org}/gate/scan", ['code' => $pass['code']])
            ->assertOk()
            ->assertJsonPath('data.result', 'denied')
            ->assertJsonPath('data.reason', 'revoked');
    }

    public function test_unknown_code_is_denied_and_logged(): void
    {
        [$owner, $org] = $this->ownerWithOrganization();

        $this->actingAs($owner, 'sanctum')
            ->postJson("/api/v1/organizations/{$org}/gate/scan", ['code' => 'ZZZZZZ'])
            ->assertOk()
            ->assertJsonPath('data.result', 'denied')
            ->assertJsonPath('data.reason', 'unknown_code');

        $this->actingAs($owner, 'sanctum')
            ->getJson("/api/v1/organizations/{$org}/gate")
            ->assertOk()
            ->assertJsonPath('data.0.result', 'denied');
    }

    public function test_expired_pass_is_denied(): void
    {
        [$owner, $org] = $this->ownerWithOrganization();
        // Valid window entirely in the past.
        $pass = $this->issuePass($owner, $org, [
            'type' => 'recurring',
            'valid_from' => now()->subDays(2)->toIso8601String(),
            'valid_until' => now()->subDay()->toIso8601String(),
        ]);

        $this->actingAs($owner, 'sanctum')
            ->postJson("/api/v1/organizations/{$org}/gate/scan", ['code' => $pass['code']])
            ->assertOk()
            ->assertJsonPath('data.result', 'denied')
            ->assertJsonPath('data.reason', 'expired');
    }

    public function test_outsider_cannot_issue_a_pass_in_another_org(): void
    {
        [, $org] = $this->ownerWithOrganization();
        $outsider = User::factory()->create();

        $this->actingAs($outsider, 'sanctum')
            ->postJson("/api/v1/organizations/{$org}/passes", [
                'visitor_name' => 'Intruder',
                'type' => 'single',
            ])
            ->assertForbidden();
    }
}
