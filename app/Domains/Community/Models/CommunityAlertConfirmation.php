<?php

declare(strict_types=1);

namespace App\Domains\Community\Models;

use App\Domains\Shared\Concerns\HasUuid;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A single user's verification vote on an alert: confirm (still happening) or
 * dismiss (cleared / false). One row per user per alert, updated in place.
 */
final class CommunityAlertConfirmation extends Model
{
    use HasUuid;

    protected $fillable = [
        'community_alert_id',
        'user_id',
        'kind',
        'still_active',
        'impact',
        'comment',
    ];

    protected function casts(): array
    {
        return [
            'still_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<CommunityAlert, $this>
     */
    public function alert(): BelongsTo
    {
        return $this->belongsTo(CommunityAlert::class, 'community_alert_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
