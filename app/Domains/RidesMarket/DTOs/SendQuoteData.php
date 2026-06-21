<?php

declare(strict_types=1);

namespace App\Domains\RidesMarket\DTOs;

use App\Domains\RidesMarket\Support\Enums\ParcelSize;
use App\Domains\Shared\Data\DataTransferObject;
use Illuminate\Http\Request;

/**
 * A Sentrix Send quote request: the leg + the parcel size (no persistence).
 */
final class SendQuoteData extends DataTransferObject
{
    public function __construct(
        public readonly float $pickupLat,
        public readonly float $pickupLng,
        public readonly float $dropoffLat,
        public readonly float $dropoffLng,
        public readonly ParcelSize $parcelSize,
    ) {}

    public static function fromRequest(Request $request): self
    {
        return new self(
            pickupLat: (float) $request->float('pickup_lat'),
            pickupLng: (float) $request->float('pickup_lng'),
            dropoffLat: (float) $request->float('dropoff_lat'),
            dropoffLng: (float) $request->float('dropoff_lng'),
            parcelSize: ParcelSize::from($request->string('parcel_size')->value()),
        );
    }
}
