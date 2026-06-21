<?php

declare(strict_types=1);

namespace App\Domains\Responder\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A single recorded responder position. Append-only (no updated_at); written in
 * idempotent batches and queried for history/proximity.
 */
final class ResponderLocation extends Model
{
    public const UPDATED_AT = null;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $table = 'responder_locations';

    protected $fillable = [
        'id',
        'responder_id',
        'organization_id',
        'user_id',
        'client_fix_id',
        'lat',
        'lng',
        'accuracy',
        'speed',
        'heading',
        'recorded_at',
        'received_at',
    ];

    protected function casts(): array
    {
        return [
            'lat' => 'float',
            'lng' => 'float',
            'accuracy' => 'float',
            'speed' => 'float',
            'heading' => 'float',
            'recorded_at' => 'datetime',
            'received_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Responder, $this>
     */
    public function responder(): BelongsTo
    {
        return $this->belongsTo(Responder::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
