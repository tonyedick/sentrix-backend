<?php

declare(strict_types=1);

namespace App\Domains\RidesMarket\DTOs;

use App\Domains\Shared\Data\DataTransferObject;
use Illuminate\Http\Request;

/**
 * A name-your-price ride offer: the leg + the rider's proposed fare in cents.
 */
final class CreateOfferData extends DataTransferObject
{
    public function __construct(
        public readonly float $originLat,
        public readonly float $originLng,
        public readonly float $destLat,
        public readonly float $destLng,
        public readonly int $proposedFareCents,
        public readonly ?string $originLabel,
        public readonly ?string $destLabel,
    ) {}

    public static function fromRequest(Request $request): self
    {
        return new self(
            originLat: (float) $request->float('origin_lat'),
            originLng: (float) $request->float('origin_lng'),
            destLat: (float) $request->float('dest_lat'),
            destLng: (float) $request->float('dest_lng'),
            proposedFareCents: $request->integer('proposed_fare_cents'),
            originLabel: $request->filled('origin_label') ? $request->string('origin_label')->trim()->value() : null,
            destLabel: $request->filled('dest_label') ? $request->string('dest_label')->trim()->value() : null,
        );
    }
}
