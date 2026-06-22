<?php

declare(strict_types=1);

namespace App\Domains\Rewards\Services;

use App\Domains\Rewards\Models\RewardAccount;
use App\Domains\Rewards\Models\RewardLedgerEntry;
use App\Models\User;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * Gamification layer over the points economy: derived badges, missions, a
 * date-based daily streak, a leaderboard, and points -> Premium conversion.
 *
 * Earned/progress state is DERIVED from the reward account + ledger at read time
 * (no per-badge/mission tables) — catalogues live in config('sentrix.rewards').
 * User-scoped (ADR-0001). Points are integers throughout.
 */
final readonly class RewardGamificationService
{
    public function __construct(
        private DatabaseManager $db,
        private RewardService $rewards,
    ) {}

    /**
     * The caller's earned badges + progress, derived from lifetime activity.
     *
     * @return list<array{id:string,name:string,description:string,counter:string,target:int,progress:int,earned:bool}>
     */
    public function badgesFor(User $user): array
    {
        $account = $this->rewards->accountFor($user);
        $metrics = $this->lifetimeMetrics($user, $account);

        /** @var list<array<string, mixed>> $catalogue */
        $catalogue = config('sentrix.rewards.badges', []);

        $out = [];
        foreach ($catalogue as $badge) {
            $target = (int) $badge['target'];
            $value = (int) ($metrics[$badge['counter']] ?? 0);
            $progress = min($value, $target);

            $out[] = [
                'id' => (string) $badge['id'],
                'name' => (string) $badge['name'],
                'description' => (string) $badge['description'],
                'counter' => (string) $badge['counter'],
                'target' => $target,
                'progress' => $progress,
                'earned' => $progress >= $target,
            ];
        }

        return $out;
    }

    /**
     * Available daily/weekly missions + the caller's progress in the rolling
     * window (daily = since midnight, weekly = last 7 days).
     *
     * @return list<array{id:string,scope:string,title:string,points:int,counter:string,target:int,progress:int,done:bool}>
     */
    public function missionsFor(User $user): array
    {
        /** @var list<array<string, mixed>> $catalogue */
        $catalogue = config('sentrix.rewards.missions', []);

        $dailyCounts = $this->reasonCountsSince($user, Carbon::today());
        $weeklyCounts = $this->reasonCountsSince($user, Carbon::today()->subDays(6));
        $account = $this->rewards->accountFor($user);
        $checkedInToday = $account->last_activity_on !== null
            && Carbon::parse($account->last_activity_on)->isSameDay(Carbon::today());

        $out = [];
        foreach ($catalogue as $mission) {
            $scope = (string) $mission['scope'];
            $counter = (string) $mission['counter'];
            $target = (int) $mission['target'];

            if ($counter === 'checkin') {
                $value = $checkedInToday ? 1 : 0;
            } else {
                $value = $scope === 'weekly'
                    ? (int) ($weeklyCounts[$counter] ?? 0)
                    : (int) ($dailyCounts[$counter] ?? 0);
            }

            $progress = min($value, $target);

            $out[] = [
                'id' => (string) $mission['id'],
                'scope' => $scope,
                'title' => (string) $mission['title'],
                'points' => (int) $mission['points'],
                'counter' => $counter,
                'target' => $target,
                'progress' => $progress,
                'done' => $progress >= $target,
            ];
        }

        return $out;
    }

    /**
     * Top users by points (bounded window), the caller's rank always included.
     *
     * @return array{entries:list<array{rank:int,user_id:string,points:int,is_you:bool}>,you:array{rank:int,points:int}}
     */
    public function leaderboard(User $user): array
    {
        $size = (int) config('sentrix.rewards.leaderboard_size', 20);

        /** @var list<RewardAccount> $top */
        $top = RewardAccount::query()
            ->orderByDesc('points_balance')
            ->orderBy('user_id')
            ->limit($size)
            ->get()
            ->all();

        $callerId = $user->getKey();
        $callerPoints = $this->rewards->accountFor($user)->points_balance;

        $entries = [];
        foreach ($top as $i => $account) {
            $entries[] = [
                'rank' => $i + 1,
                'user_id' => (string) $account->user_id,
                'points' => (int) $account->points_balance,
                'is_you' => $account->user_id === $callerId,
            ];
        }

        // The caller's true rank = 1 + everyone strictly ahead of them.
        $ahead = (int) RewardAccount::query()
            ->where('points_balance', '>', $callerPoints)
            ->count();
        $callerRank = $ahead + 1;

        return [
            'entries' => $entries,
            'you' => ['rank' => $callerRank, 'points' => $callerPoints],
        ];
    }

    /**
     * Real, date-based daily streak. Idempotent per calendar day: the first
     * activity of a new day increments the streak (+1 on a consecutive day,
     * reset to 1 after any gap); same-day repeats are a no-op. Uses whole days
     * (Carbon 3 diffInDays returns a float — cast to int).
     */
    public function recordDailyActivity(User $user): RewardAccount
    {
        return $this->db->transaction(function () use ($user): RewardAccount {
            $account = RewardAccount::query()
                ->where('user_id', $user->getKey())
                ->lockForUpdate()
                ->firstOrCreate(['user_id' => $user->getKey()]);

            $today = Carbon::today();
            $last = $account->last_activity_on !== null
                ? Carbon::parse($account->last_activity_on)->startOfDay()
                : null;

            if ($last !== null && $last->isSameDay($today)) {
                $account->wasRecentlyCreated = false;

                return $account; // already counted today — idempotent no-op
            }

            $gapDays = $last !== null ? (int) $last->diffInDays($today) : null;
            $account->streak_days = $gapDays === 1 ? $account->streak_days + 1 : 1;
            $account->last_activity_on = $today->toDateString();
            $account->save();

            $account->wasRecentlyCreated = false;

            return $account->refresh();
        });
    }

    /**
     * Convert points into Premium days. Deducts the pack cost from the points
     * balance (422 on shortfall) and records the granted Premium days on the
     * account. Billing handoff: until a subscription hook lands, the grant is
     * tracked here as premium_days_granted; SubscriptionService would consume it.
     *
     * @return array{account:RewardAccount,days:int,premium_until:string}
     */
    public function convertPointsToPremium(User $user, string $packId): array
    {
        /** @var array<string, array{days:int,cost:int}> $packs */
        $packs = config('sentrix.rewards.premium_packs', []);
        $pack = $packs[$packId] ?? null;

        if ($pack === null) {
            throw ValidationException::withMessages(['pack_id' => ['Unknown premium pack.']]);
        }

        $cost = (int) $pack['cost'];
        $days = (int) $pack['days'];

        return $this->db->transaction(function () use ($user, $packId, $cost, $days): array {
            $account = RewardAccount::query()
                ->where('user_id', $user->getKey())
                ->lockForUpdate()
                ->firstOrCreate(['user_id' => $user->getKey()]);

            if ($account->points_balance < $cost) {
                throw ValidationException::withMessages(['points' => ['Insufficient points balance.']]);
            }

            RewardLedgerEntry::create([
                'user_id' => $user->getKey(),
                'type' => 'redeem',
                'points' => -$cost,
                'reason' => "convert:premium:{$packId}",
                'metadata' => ['pack_id' => $packId, 'premium_days' => $days],
            ]);

            $account->points_balance -= $cost;
            $account->premium_days_granted += $days;
            $account->save();
            $account->refresh();

            return [
                'account' => $account,
                'days' => $days,
                'premium_until' => Carbon::now()->addDays($days)->toIso8601String(),
            ];
        });
    }

    /**
     * Lifetime activity counters used for badges.
     *
     * @return array<string, int>
     */
    private function lifetimeMetrics(User $user, RewardAccount $account): array
    {
        $byReason = $this->reasonCountsSince($user, null);
        $lifetimePoints = (int) RewardLedgerEntry::query()
            ->where('user_id', $user->getKey())
            ->where('points', '>', 0)
            ->sum('points');

        return [
            'reports' => ($byReason['report'] ?? 0) + ($byReason['verified_report'] ?? 0),
            'trips' => $byReason['safe_trip'] ?? 0,
            'verifications' => $byReason['verify_alert'] ?? 0,
            'streak_days' => (int) $account->streak_days,
            'lifetime_points' => $lifetimePoints,
        ];
    }

    /**
     * Count earn entries grouped by their `reason`, optionally since a date.
     * Reasons are normalised to their leading token (e.g. "convert:premium:pp7"
     * groups under "convert").
     *
     * @return array<string, int>
     */
    private function reasonCountsSince(User $user, ?Carbon $since): array
    {
        $query = RewardLedgerEntry::query()
            ->where('user_id', $user->getKey())
            ->where('type', 'earn')
            ->whereNotNull('reason');

        if ($since !== null) {
            $query->where('created_at', '>=', $since);
        }

        $counts = [];
        foreach ($query->pluck('reason') as $reason) {
            $key = explode(':', (string) $reason, 2)[0];
            $counts[$key] = ($counts[$key] ?? 0) + 1;
        }

        return $counts;
    }
}
