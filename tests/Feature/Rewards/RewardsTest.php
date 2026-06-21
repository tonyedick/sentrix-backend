<?php

declare(strict_types=1);

namespace Tests\Feature\Rewards;

use App\Domains\Rewards\Services\RewardService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class RewardsTest extends TestCase
{
    use RefreshDatabase;

    public function test_show_returns_zero_balance_for_new_user(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/me/rewards')
            ->assertOk()
            ->assertJsonPath('data.points_balance', 0)
            ->assertJsonPath('data.boost_active', false);
    }

    public function test_earn_then_redeem_updates_balance_and_ledger(): void
    {
        $user = User::factory()->create();
        app(RewardService::class)->earn($user, 100, 'verified_report');

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/me/rewards')
            ->assertOk()
            ->assertJsonPath('data.points_balance', 100);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/me/rewards/redeem', ['points' => 40, 'reason' => 'voucher'])
            ->assertOk()
            ->assertJsonPath('data.points_balance', 60);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/me/rewards/ledger')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_cannot_redeem_more_than_balance(): void
    {
        $user = User::factory()->create();
        app(RewardService::class)->earn($user, 10);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/me/rewards/redeem', ['points' => 50])
            ->assertStatus(422);
    }
}
