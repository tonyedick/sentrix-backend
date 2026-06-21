<?php

declare(strict_types=1);

namespace App\Domains\RidesMarket\Models;

use App\Domains\RidesMarket\Support\Enums\OfferStatus;
use App\Domains\RidesMarket\Support\Enums\PricingFlag;
use App\Domains\Shared\Concerns\HasUuid;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A rider's name-your-price ride offer. User-scoped (ADR-0001): belongs to the
 * authenticated rider. Drivers respond with RideBids; on accept a real Ride is
 * materialised in the Rides domain and matched_ride_id points at it.
 * ALL MONEY IS INTEGER CENTS.
 */
final class RideOffer extends Model
{
    use HasUuid;

    protected $fillable = [
        'user_id',
        'origin_label',
        'origin_lat',
        'origin_lng',
        'dest_label',
        'dest_lat',
        'dest_lng',
        'distance_km',
        'proposed_fare_cents',
        'fair_estimate_cents',
        'pricing_flag',
        'status',
        'matched_ride_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'origin_lat' => 'decimal:7',
            'origin_lng' => 'decimal:7',
            'dest_lat' => 'decimal:7',
            'dest_lng' => 'decimal:7',
            'distance_km' => 'decimal:2',
            'proposed_fare_cents' => 'integer',
            'fair_estimate_cents' => 'integer',
            'pricing_flag' => PricingFlag::class,
            'status' => OfferStatus::class,
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
     * @return HasMany<RideBid, $this>
     */
    public function bids(): HasMany
    {
        return $this->hasMany(RideBid::class);
    }
}
