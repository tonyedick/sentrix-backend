<?php

declare(strict_types=1);

namespace Tests\Feature\Rewards;

use App\Domains\Rewards\Models\RewardAccount;
use App\Domains\Rewards\Services\RewardService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Gamification: derived badges, leaderboard ranking, missions with progress,
 * points -> Premium conversion, and the date-based daily streak. Balance/redeem
 * basics are covered by RewardsTest — not re-asserted here.
 */
final class RewardsGamificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_badges_reflect_earned_state_after_activity(): void
    {
        $user = User::factory()->create();
        $rewards = app(RewardService::class);
        // One safe trip earns the "First Steps" trip badge.
        $rewards->earn($user, 50, 'safe_trip');

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/me/rewards/badges')
            ->assertOk()
            ->assertJsonPath('data.0.id', 'first_trip')
            ->assertJsonPath('data.0.earned', true)
            ->assertJsonPath('data.0.progress', 1);
    }

    public function test_leaderboard_ranks_users_and_includes_caller(): void
    {
        $rewards = app(RewardService::class);

        $leader = User::factory()->create();
        $rewards->earn($leader, 500, 'safe_trip');

        $caller = User::factory()->create();
        $rewards->earn($caller, 100, 'safe_trip');

        $response = $this->actingAs($caller, 'sanctum')
            ->getJson('/api/v1/me/rewards/leaderboard')
            ->assertOk()
            ->assertJsonPath('data.entries.0.points', 500)
            ->assertJsonPath('data.you.points', 100);

        // The caller sits behind the 500-point leader.
        $this->assertSame(2, $response->json('data.you.rank'));
    }

    public function test_missions_list_with_progress(): void
    {
        $user = User::factory()->create();
        app(RewardService::class)->earn($user, 30, 'verify_alert');

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/me/rewards/missions')
            ->assertOk();

        $missions = collect($response->json('data'));
        $verifyMission = $missions->firstWhere('id', 'd_verify2');
        $this->assertNotNull($verifyMission);
        $this->assertSame(1, $verifyMission['progress']);
        $this->assertFalse($verifyMission['done']);
    }

    public function test_convert_points_to_premium_deducts_points(): void
    {
        $user = User::factory()->create();
        app(RewardService::class)->earn($user, 1000, 'safe_trip');

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/me/rewards/convert', ['pack_id' => 'pp7'])
            ->assertOk()
            ->assertJsonPath('data.days', 7)
            ->assertJsonPath('data.points_balance', 400) // 1000 - 600
            ->assertJsonPath('data.premium_days_granted', 7);
    }

    public function test_convert_rejects_insufficient_points(): void
    {
        $user = User::factory()->create();
        app(RewardService::class)->earn($user, 100, 'safe_trip');

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/me/rewards/convert', ['pack_id' => 'pp7'])
            ->assertStatus(422);
    }

    public function test_daily_streak_increments_on_a_new_day(): void
    {
        $user = User::factory()->create();

        // Day one: streak becomes 1.
        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/me/rewards/record-activity')
            ->assertOk()
            ->assertJsonPath('data.streak_days', 1);

        // Same day again: idempotent, still 1.
        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/me/rewards/record-activity')
            ->assertOk()
            ->assertJsonPath('data.streak_days', 1);

        // Simulate yesterday's check-in, then today should advance to 2.
        RewardAccount::query()->where('user_id', $user->getKey())
            ->update(['last_activity_on' => Carbon::yesterday()->toDateString(), 'streak_days' => 1]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/me/rewards/record-activity')
            ->assertOk()
            ->assertJsonPath('data.streak_days', 2);

        // The streak is also exposed on the rewards profile.
        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/me/rewards')
            ->assertOk()
            ->assertJsonPath('data.streak_days', 2);
    }
}
