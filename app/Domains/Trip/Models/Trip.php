<?php

declare(strict_types=1);

namespace App\Domains\Trip\Models;

use App\Domains\Organization\Models\Organization;
use App\Domains\Shared\Concerns\HasUuid;
use App\Domains\Trip\Support\Enums\TripStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A monitored journey belonging to one organization and one user.
 */
final class Trip extends Model
{
    use HasUuid;
    use SoftDeletes;

    protected $fillable = [
        'organization_id',
        'user_id',
        'status',
        'origin_label',
        'origin_lat',
        'origin_lng',
        'destination_label',
        'destination_lat',
        'destination_lng',
        'started_at',
        'expected_arrival_at',
        'completed_at',
        'cancelled_at',
        'notes',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'status' => TripStatus::class,
            'origin_lat' => 'decimal:7',
            'origin_lng' => 'decimal:7',
            'destination_lat' => 'decimal:7',
            'destination_lng' => 'decimal:7',
            'started_at' => 'datetime',
            'expected_arrival_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'last_location_at' => 'datetime',
            'last_lat' => 'float',
            'last_lng' => 'float',
            'lost_contact_at' => 'datetime',
            'metadata' => 'array',
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
     * The monitored individual.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Active trips whose expected arrival has elapsed — candidates to flag overdue.
     *
     * @param  Builder<Trip>  $query
     * @return Builder<Trip>
     */
    public function scopeOverdueCandidates(Builder $query): Builder
    {
        return $query
            ->where('status', TripStatus::Active->value)
            ->whereNotNull('expected_arrival_at')
            ->where('expected_arrival_at', '<', now());
    }
}
