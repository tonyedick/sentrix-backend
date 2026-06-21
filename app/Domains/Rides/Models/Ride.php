<?php

declare(strict_types=1);

namespace App\Domains\Rides\Models;

use App\Domains\Rides\Support\Enums\PaymentMethod;
use App\Domains\Rides\Support\Enums\RideClass;
use App\Domains\Rides\Support\Enums\RideStatus;
use App\Domains\Shared\Concerns\HasUuid;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * A rider's ride. User-scoped (ADR-0001): belongs to the authenticated rider.
 * The driver_* columns are a denormalised snapshot of the matched driver — the
 * canonical Driver record arrives with the later Driver domain (no FK yet).
 */
final class Ride extends Model
{
    use HasUuid;

    protected $fillable = [
        'user_id',
        'ride_class',
        'status',
        'origin_label',
        'origin_lat',
        'origin_lng',
        'dest_label',
        'dest_lat',
        'dest_lng',
        'distance_km',
        'fare_estimate_cents',
        'final_fare_cents',
        'tip_cents',
        'currency',
        'surge_multiplier',
        'payment_method',
        'match_code',
        'rating',
        'cancel_reason',
        'driver_id',
        'driver_name',
        'driver_plate',
        'driver_lat',
        'driver_lng',
        'driver_eta_minutes',
        'driver_speed_kph',
        'requested_at',
        'completed_at',
        'cancelled_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'ride_class' => RideClass::class,
            'status' => RideStatus::class,
            'payment_method' => PaymentMethod::class,
            'origin_lat' => 'decimal:7',
            'origin_lng' => 'decimal:7',
            'dest_lat' => 'decimal:7',
            'dest_lng' => 'decimal:7',
            'distance_km' => 'decimal:2',
            'fare_estimate_cents' => 'integer',
            'final_fare_cents' => 'integer',
            'tip_cents' => 'integer',
            'surge_multiplier' => 'decimal:2',
            'rating' => 'integer',
            'driver_lat' => 'decimal:7',
            'driver_lng' => 'decimal:7',
            'driver_eta_minutes' => 'integer',
            'driver_speed_kph' => 'integer',
            'requested_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasOne<RideSafety, $this>
     */
    public function safety(): HasOne
    {
        return $this->hasOne(RideSafety::class);
    }

    /**
     * @return HasMany<RideEvidence, $this>
     */
    public function evidence(): HasMany
    {
        return $this->hasMany(RideEvidence::class);
    }
}
