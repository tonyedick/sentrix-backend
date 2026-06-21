<?php

declare(strict_types=1);

namespace App\Domains\RidesMarket\Models;

use App\Domains\RidesMarket\Support\Enums\BidKind;
use App\Domains\RidesMarket\Support\Enums\BidStatus;
use App\Domains\Shared\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A (simulated) driver's bid on a rider's offer: accept the proposed price or
 * counter with their own. The driver is a denormalised SNAPSHOT — the canonical
 * record comes with the Driver domain. Append-only on create (no updated_at).
 * ALL MONEY IS INTEGER CENTS.
 */
final class RideBid extends Model
{
    use HasUuid;

    public const UPDATED_AT = null;

    protected $fillable = [
        'ride_offer_id',
        'driver_id',
        'driver_name',
        'amount_cents',
        'kind',
        'status',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount_cents' => 'integer',
            'kind' => BidKind::class,
            'status' => BidStatus::class,
        ];
    }

    /**
     * @return BelongsTo<RideOffer, $this>
     */
    public function offer(): BelongsTo
    {
        return $this->belongsTo(RideOffer::class, 'ride_offer_id');
    }
}
