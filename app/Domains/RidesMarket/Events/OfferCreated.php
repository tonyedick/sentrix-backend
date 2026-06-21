<?php

declare(strict_types=1);

namespace App\Domains\RidesMarket\Events;

use App\Domains\RidesMarket\Models\RideOffer;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * A rider posted a name-your-price offer (with seeded driver bids). User-scoped —
 * NOT an OrganizationRecordEvent.
 */
final class OfferCreated
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly RideOffer $offer,
    ) {}
}
