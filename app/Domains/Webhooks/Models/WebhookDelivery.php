<?php

declare(strict_types=1);

namespace App\Domains\Webhooks\Models;

use App\Domains\Shared\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An immutable-ish record of one webhook delivery attempt: the event, the signed
 * payload, the computed signature, the HTTP outcome (status_code/success/error),
 * the attempt count, and when it was delivered. Append-and-update: created on the
 * first attempt and updated as retries report their outcome — no updated_at.
 */
final class WebhookDelivery extends Model
{
    use HasUuid;

    /** Append-and-update ledger: track creation only. */
    public const UPDATED_AT = null;

    protected $fillable = [
        'webhook_id',
        'event',
        'payload',
        'signature',
        'status_code',
        'success',
        'error',
        'attempts',
        'delivered_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'status_code' => 'integer',
            'success' => 'boolean',
            'attempts' => 'integer',
            'delivered_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Webhook, $this>
     */
    public function webhook(): BelongsTo
    {
        return $this->belongsTo(Webhook::class);
    }
}
