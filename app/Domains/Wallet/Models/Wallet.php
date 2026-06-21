<?php

declare(strict_types=1);

namespace App\Domains\Wallet\Models;

use App\Domains\Shared\Concerns\HasUuid;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A user's wallet: one per user. `balance_cents` is the authoritative cached
 * balance; the append-only wallet_transactions log is the history. ALL MONEY IS
 * INTEGER CENTS. User-scoped (ADR-0001).
 */
final class Wallet extends Model
{
    use HasUuid;

    protected $fillable = [
        'user_id',
        'balance_cents',
        'currency',
        'lifetime_topup_cents',
    ];

    /**
     * Defaults so a lazily-created wallet is fully populated in memory on the
     * same request it is created.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'balance_cents' => 0,
        'currency' => 'NGN',
        'lifetime_topup_cents' => 0,
    ];

    protected function casts(): array
    {
        return [
            'balance_cents' => 'integer',
            'lifetime_topup_cents' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<WalletTransaction, $this>
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(WalletTransaction::class);
    }
}
