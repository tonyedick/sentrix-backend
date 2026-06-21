<?php

declare(strict_types=1);

namespace App\Domains\Wallet\Models;

use App\Domains\Shared\Concerns\HasUuid;
use App\Domains\Wallet\Support\Enums\PaymentMethodKind;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A rider's payment method. `cash` and `wallet` are non-removable system methods
 * seeded lazily on first read; `card` methods store the LAST 4 DIGITS ONLY —
 * never a PAN. User-scoped (ADR-0001).
 */
final class PaymentMethod extends Model
{
    use HasUuid;

    protected $fillable = [
        'user_id',
        'kind',
        'label',
        'brand',
        'last4',
        'is_default',
        'removable',
    ];

    protected function casts(): array
    {
        return [
            'kind' => PaymentMethodKind::class,
            'is_default' => 'boolean',
            'removable' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
