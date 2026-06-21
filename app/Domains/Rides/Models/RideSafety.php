<?php

declare(strict_types=1);

namespace App\Domains\Rides\Models;

use App\Domains\Shared\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * In-ride safety state for a ride (1:1). Mirrors the SentrixGo dashcam/guardian
 * cover: armed → recording + guardians notified; off_route / overdue / check_in
 * are computed-trouble flags surfaced during the trip.
 */
final class RideSafety extends Model
{
    use HasUuid;

    protected $table = 'ride_safeties';

    protected $fillable = [
        'ride_id',
        'armed',
        'recording',
        'guardians_notified',
        'off_route',
        'overdue',
        'check_in_due',
        'evidence_count',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'armed' => 'boolean',
            'recording' => 'boolean',
            'guardians_notified' => 'boolean',
            'off_route' => 'boolean',
            'overdue' => 'boolean',
            'check_in_due' => 'boolean',
            'evidence_count' => 'integer',
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
