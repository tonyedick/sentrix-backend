<?php

declare(strict_types=1);

namespace App\Domains\Rides\DTOs;

use App\Domains\Rides\Support\Enums\PaymentMethod;
use App\Domains\Rides\Support\Enums\RideClass;
use App\Domains\Shared\Data\DataTransferObject;
use Illuminate\Http\Request;

/**
 * A ride request: chosen class, the leg, optional payment method + labels.
 */
final class RequestRideData extends DataTransferObject
{
    public function __construct(
        public readonly RideClass $rideClass,
        public readonly float $originLat,
        public readonly float $originLng,
        public readonly float $destLat,
        public readonly float $destLng,
        public readonly PaymentMethod $paymentMethod,
        public readonly ?string $originLabel,
        public readonly ?string $destLabel,
    ) {}

    public static function fromRequest(Request $request): self
    {
        return new self(
            rideClass: RideClass::from($request->string('ride_class')->value()),
            originLat: (float) $request->float('origin_lat'),
            originLng: (float) $request->float('origin_lng'),
            destLat: (float) $request->float('dest_lat'),
            destLng: (float) $request->float('dest_lng'),
            paymentMethod: PaymentMethod::from(
                $request->filled('payment_method')
                    ? $request->string('payment_method')->value()
                    : PaymentMethod::Cash->value,
            ),
            originLabel: $request->filled('origin_label') ? $request->string('origin_label')->trim()->value() : null,
            destLabel: $request->filled('dest_label') ? $request->string('dest_label')->trim()->value() : null,
        );
    }
}
