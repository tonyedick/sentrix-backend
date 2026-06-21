<?php

declare(strict_types=1);

namespace App\Domains\Rides\Models;

use App\Domains\Rides\Support\Enums\EvidenceKind;
use App\Domains\Shared\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An evidence clip banked from the in-car dashcam during a ride. Append-only.
 */
final class RideEvidence extends Model
{
    use HasUuid;

    protected $table = 'ride_evidence';

    // Append-only log: no updated_at column.
    public const UPDATED_AT = null;

    protected $fillable = [
        'ride_id',
        'kind',
        'url',
        'recorded_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => EvidenceKind::class,
            'recorded_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Ride, $this>
     */
    public function ride(): BelongsTo
    {
        return $this->belongsTo(Ride::class);
    }
}
