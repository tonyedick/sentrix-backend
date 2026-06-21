<?php

declare(strict_types=1);

namespace App\Domains\RidesMarket\Events;

use App\Domains\RidesMarket\Models\Delivery;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * A Sentrix Send parcel delivery was booked. User-scoped — NOT an
 * OrganizationRecordEvent.
 */
final class DeliveryBooked
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly Delivery $delivery,
    ) {}
}
