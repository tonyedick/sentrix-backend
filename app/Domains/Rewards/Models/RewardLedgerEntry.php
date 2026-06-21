<?php

declare(strict_types=1);

namespace App\Domains\Rewards\Models;

use App\Domains\Shared\Concerns\HasUuid;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An append-only points movement (earn or redeem). `points` is signed.
 */
final class RewardLedgerEntry extends Model
{
    use HasUuid;

    protected $fillable = [
        'user_id',
        'type',
        'points',
        'reason',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'points' => 'integer',
            'metadata' => 'array',
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
