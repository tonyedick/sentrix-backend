<?php

declare(strict_types=1);

namespace App\Domains\Billing\Models;

use App\Domains\Shared\Concerns\HasUuid;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A billing-history line item (Billing History screen).
 */
final class Invoice extends Model
{
    use HasUuid;

    protected $fillable = [
        'user_id',
        'number',
        'plan_key',
        'amount_cents',
        'currency',
        'status',
        'issued_at',
    ];

    protected function casts(): array
    {
        return [
            'amount_cents' => 'integer',
            'issued_at' => 'datetime',
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
