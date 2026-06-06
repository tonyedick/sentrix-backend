<?php

declare(strict_types=1);

namespace App\Domains\Tracking\Models;

use App\Domains\Trip\Models\Trip;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A single recorded position on a trip's track. Append-only (no updated_at);
 * written in batches via insertOrIgnore, queried for history/proximity.
 */
final class TripLocation extends Model
{
    public const UPDATED_AT = null;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $table = 'trip_locations';

    protected $fillable = [
        'id',
        'trip_id',
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
     * @return BelongsTo<Trip, $this>
     */
    public function trip(): BelongsTo
    {
        return $this->belongsTo(Trip::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
