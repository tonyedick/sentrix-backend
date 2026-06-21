<?php

declare(strict_types=1);

namespace App\Domains\Notification\Models;

use App\Domains\Shared\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;

/**
 * A per-channel delivery record for one notification. Append-and-update: created
 * on first send attempt, updated to sent/failed as the framework reports outcome.
 */
final class NotificationDelivery extends Model
{
    use HasUuid;

    public const STATUS_PENDING = 'pending';
    public const STATUS_SENT = 'sent';
    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'notification_id',
        'notification_type',
        'channel',
        'organization_id',
        'notifiable_type',
        'notifiable_id',
        'status',
        'attempts',
        'error',
        'sent_at',
    ];

    protected function casts(): array
    {
        return [
            'attempts' => 'integer',
            'sent_at' => 'datetime',
        ];
    }
}
