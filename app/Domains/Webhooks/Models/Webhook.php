<?php

declare(strict_types=1);

namespace App\Domains\Webhooks\Models;

use App\Domains\Organization\Models\Organization;
use App\Domains\Shared\Concerns\HasUuid;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * An organization's outbound partner-integration endpoint. Carries the URL,
 * the list of subscribed event keys, and a signing secret used to HMAC-sign
 * every delivery body.
 */
final class Webhook extends Model
{
    use HasUuid;

    protected $fillable = [
        'organization_id',
        'created_by',
        'url',
        'events',
        'secret',
        'active',
        'description',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'events' => 'array',
            'active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return HasMany<WebhookDelivery, $this>
     */
    public function deliveries(): HasMany
    {
        return $this->hasMany(WebhookDelivery::class);
    }
}
