<?php

declare(strict_types=1);

namespace App\Domains\Wallet\Models;

use App\Domains\Shared\Concerns\HasUuid;
use App\Domains\Wallet\Support\Enums\TransactionDirection;
use App\Domains\Wallet\Support\Enums\TransactionStatus;
use App\Domains\Wallet\Support\Enums\TransactionType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An append-only wallet movement. `amount_cents` is always positive; `direction`
 * (credit|debit) carries the sign. `balance_after_cents` snapshots the wallet
 * balance immediately after this movement. `reference` is the idempotency key
 * for top-ups (unique where present). ALL MONEY IS INTEGER CENTS.
 */
final class WalletTransaction extends Model
{
    use HasUuid;

    /** Append-only: no updated_at. */
    public const UPDATED_AT = null;

    protected $fillable = [
        'wallet_id',
        'type',
        'direction',
        'amount_cents',
        'balance_after_cents',
        'method',
        'reference',
        'status',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'type' => TransactionType::class,
            'direction' => TransactionDirection::class,
            'status' => TransactionStatus::class,
            'amount_cents' => 'integer',
            'balance_after_cents' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Wallet, $this>
     */
    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }
}
