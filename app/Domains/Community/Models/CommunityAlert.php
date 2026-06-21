<?php

declare(strict_types=1);

namespace App\Domains\Community\Models;

use App\Domains\Community\Support\Enums\AlertCategory;
use App\Domains\Community\Support\Enums\AlertImpact;
use App\Domains\Community\Support\Enums\AlertStatus;
use App\Domains\Shared\Concerns\HasUuid;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A crowdsourced, geo-located community alert. User-scoped (a reporter) and
 * surfaced as a nearby feed; confirmed/dismissed by other users.
 */
final class CommunityAlert extends Model
{
    use HasUuid;

    protected $fillable = [
        'reporter_id',
        'category',
        'title',
        'note',
        'impact',
        'status',
        'lat',
        'lng',
        'confirmations_count',
        'dismissals_count',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'category' => AlertCategory::class,
            'impact' => AlertImpact::class,
            'status' => AlertStatus::class,
            'lat' => 'float',
            'lng' => 'float',
            'confirmations_count' => 'integer',
            'dismissals_count' => 'integer',
            'expires_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reporter_id');
    }

    /**
     * @return HasMany<CommunityAlertConfirmation, $this>
     */
    public function confirmations(): HasMany
    {
        return $this->hasMany(CommunityAlertConfirmation::class);
    }
}
