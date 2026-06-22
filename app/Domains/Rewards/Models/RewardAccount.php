<?php

declare(strict_types=1);

namespace App\Domains\Rewards\Models;

use App\Domains\Shared\Concerns\HasUuid;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A user's rewards account: cached points balance and an optional active boost
 * multiplier. The authoritative history lives in reward_ledger_entries.
 */
final class RewardAccount extends Model
{
    use HasUuid;

    protected $fillable = [
        'user_id',
        'points_balance',
        'boost_multiplier',
        'boost_expires_at',
        'streak_days',
        'last_activity_on',
        'premium_days_granted',
    ];

    /**
     * Defaults so a lazily-created account is fully populated in memory (not just
     * at the DB level) on the same request it is created.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'points_balance' => 0,
        'boost_multiplier' => 1.0,
        'streak_days' => 0,
        'premium_days_granted' => 0,
    ];

    protected function casts(): array
    {
        return [
            'points_balance' => 'integer',
            'boost_multiplier' => 'float',
            'boost_expires_at' => 'datetime',
            'streak_days' => 'integer',
            'last_activity_on' => 'date',
            'premium_days_granted' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function boostActive(): bool
    {
        return $this->boost_multiplier > 1.0
            && ($this->boost_expires_at === null || $this->boost_expires_at->isFuture());
    }
}
