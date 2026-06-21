<?php

declare(strict_types=1);

namespace App\Domains\Notification\Models;

use App\Domains\Organization\Models\Organization;
use App\Domains\Shared\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Per-organization notification policy: the set of enabled delivery channels.
 * Resolved by NotificationPolicyResolver, which returns a transient config-default
 * instance for organizations without a saved row.
 */
final class NotificationPolicy extends Model
{
    use HasUuid;

    protected $fillable = [
        'organization_id',
        'channels',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'channels' => 'array',
            'metadata' => 'array',
        ];
    }

    /**
     * @return list<string>
     */
    public function enabledChannels(): array
    {
        return array_values($this->channels ?? []);
    }

    /**
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
