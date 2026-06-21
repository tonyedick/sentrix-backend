<?php

declare(strict_types=1);

namespace App\Domains\Wallet\Models;

use App\Domains\Shared\Concerns\HasUuid;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A claimed referral: the claimer redeemed the referrer's (derived) code. A user
 * may claim exactly once (unique claimer_id). Both sides receive a referral_credit
 * wallet transaction. ALL MONEY IS INTEGER CENTS. User-scoped (ADR-0001).
 */
final class ReferralClaim extends Model
{
    use HasUuid;

    public $timestamps = false;

    protected $fillable = [
        'code',
        'referrer_id',
        'claimer_id',
        'amount_cents',
        'claimed_at',
    ];

    protected function casts(): array
    {
        return [
            'amount_cents' => 'integer',
            'claimed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function referrer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'referrer_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function claimer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'claimer_id');
    }
}
