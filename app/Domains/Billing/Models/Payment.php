<?php

declare(strict_types=1);

namespace App\Domains\Billing\Models;

use App\Domains\Shared\Concerns\HasUuid;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A PSP checkout payment. Created PENDING at checkout, marked PAID by the signed
 * webhook (charge.success) or the sandbox simulate endpoint. Money is integer
 * minor units (cents). User-scoped (ADR-0001).
 */
final class Payment extends Model
{
    use HasUuid;

    protected $fillable = [
        'user_id',
        'reference',
        'plan_key',
        'amount_cents',
        'currency',
        'status',
        'provider',
        'region',
        'metadata',
        'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'amount_cents' => 'integer',
            'metadata' => 'array',
            'paid_at' => 'datetime',
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
