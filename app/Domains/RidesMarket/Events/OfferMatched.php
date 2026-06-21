<?php

declare(strict_types=1);

namespace App\Domains\RidesMarket\Events;

use App\Domains\RidesMarket\Models\RideOffer;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * A rider accepted a bid; a real Ride was materialised. User-scoped — NOT an
 * OrganizationRecordEvent. Carries the materialised ride id for downstream
 * listeners (tracking, ops board).
 */
final class OfferMatched
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly RideOffer $offer,
        public readonly string $rideId,
    ) {}
}
