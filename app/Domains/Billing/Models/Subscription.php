<?php

declare(strict_types=1);

namespace App\Domains\Billing\Models;

use App\Domains\Shared\Concerns\HasUuid;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A user's current subscription state. Plan definitions/entitlements live in
 * config('sentrix.billing.plans'); this row holds the active plan + period.
 */
final class Subscription extends Model
{
    use HasUuid;

    protected $fillable = [
        'user_id',
        'plan_key',
        'status',
        'auto_renew',
        'payment_method_label',
        'current_period_end',
    ];

    protected function casts(): array
    {
        return [
            'auto_renew' => 'boolean',
            'current_period_end' => 'datetime',
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
