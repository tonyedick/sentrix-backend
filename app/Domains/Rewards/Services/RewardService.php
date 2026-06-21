<?php

declare(strict_types=1);

namespace App\Domains\Rewards\Services;

use App\Domains\Rewards\Models\RewardAccount;
use App\Domains\Rewards\Models\RewardLedgerEntry;
use App\Models\User;
use Illuminate\Database\DatabaseManager;
use Illuminate\Validation\ValidationException;

/**
 * Points economy: earn, redeem, and balance. Each movement writes a ledger
 * entry and updates the cached account balance atomically. An active boost
 * multiplies earned points. User-scoped (ADR-0001).
 */
final readonly class RewardService
{
    public function __construct(private DatabaseManager $db) {}

    public function accountFor(User $user): RewardAccount
    {
        $account = RewardAccount::query()->firstOrCreate(['user_id' => $user->getKey()]);

        // A read should respond 200, not 201, even when the account is created lazily.
        $account->wasRecentlyCreated = false;

        return $account;
    }

    public function earn(User $user, int $points, ?string $reason = null): RewardAccount
    {
        if ($points <= 0) {
            throw ValidationException::withMessages(['points' => ['Points to earn must be positive.']]);
        }

        return $this->db->transaction(function () use ($user, $points, $reason): RewardAccount {
            $account = RewardAccount::query()->where('user_id', $user->getKey())->lockForUpdate()->firstOrCreate(['user_id' => $user->getKey()]);

            $multiplier = $account->boostActive() ? $account->boost_multiplier : 1.0;
            $awarded = (int) floor($points * $multiplier);

            RewardLedgerEntry::create([
                'user_id' => $user->getKey(),
                'type' => 'earn',
                'points' => $awarded,
                'reason' => $reason,
            ]);

            $account->points_balance += $awarded;
            $account->save();

            return $account->refresh();
        });
    }

    public function redeem(User $user, int $points, ?string $reason = null): RewardAccount
    {
        if ($points <= 0) {
            throw ValidationException::withMessages(['points' => ['Points to redeem must be positive.']]);
        }

        return $this->db->transaction(function () use ($user, $points, $reason): RewardAccount {
            $account = RewardAccount::query()->where('user_id', $user->getKey())->lockForUpdate()->firstOrCreate(['user_id' => $user->getKey()]);

            if ($account->points_balance < $points) {
                throw ValidationException::withMessages(['points' => ['Insufficient points balance.']]);
            }

            RewardLedgerEntry::create([
                'user_id' => $user->getKey(),
                'type' => 'redeem',
                'points' => -$points,
                'reason' => $reason,
            ]);

            $account->points_balance -= $points;
            $account->save();

            return $account->refresh();
        });
    }
}
