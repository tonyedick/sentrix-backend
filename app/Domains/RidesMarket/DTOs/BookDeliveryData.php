<?php

declare(strict_types=1);

namespace App\Domains\RidesMarket\DTOs;

use App\Domains\RidesMarket\Support\Enums\DeliveryPaymentMethod;
use App\Domains\RidesMarket\Support\Enums\ParcelSize;
use App\Domains\Shared\Data\DataTransferObject;
use Illuminate\Http\Request;

/**
 * A Sentrix Send booking: the leg, parcel size, payment method, optional COD
 * amount (cents) and recipient details.
 */
final class BookDeliveryData extends DataTransferObject
{
    public function __construct(
        public readonly float $pickupLat,
        public readonly float $pickupLng,
        public readonly float $dropoffLat,
        public readonly float $dropoffLng,
        public readonly ParcelSize $parcelSize,
        public readonly DeliveryPaymentMethod $paymentMethod,
        public readonly int $codAmountCents,
        public readonly ?string $pickupLabel,
        public readonly ?string $dropoffLabel,
        public readonly ?string $recipientName,
        public readonly ?string $recipientPhone,
    ) {}

    public static function fromRequest(Request $request): self
    {
        return new self(
            pickupLat: (float) $request->float('pickup_lat'),
            pickupLng: (float) $request->float('pickup_lng'),
            dropoffLat: (float) $request->float('dropoff_lat'),
            dropoffLng: (float) $request->float('dropoff_lng'),
            parcelSize: ParcelSize::from($request->string('parcel_size')->value()),
            paymentMethod: DeliveryPaymentMethod::from($request->string('payment_method')->value()),
            codAmountCents: $request->integer('cod_amount_cents', 0),
            pickupLabel: $request->filled('pickup_label') ? $request->string('pickup_label')->trim()->value() : null,
            dropoffLabel: $request->filled('dropoff_label') ? $request->string('dropoff_label')->trim()->value() : null,
            recipientName: $request->filled('recipient_name') ? $request->string('recipient_name')->trim()->value() : null,
            recipientPhone: $request->filled('recipient_phone') ? $request->string('recipient_phone')->trim()->value() : null,
        );
    }
}
