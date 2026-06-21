<?php

declare(strict_types=1);

namespace App\Domains\Rides\DTOs;

use App\Domains\Shared\Data\DataTransferObject;
use Illuminate\Http\Request;

/**
 * The pickup/drop-off coordinates for a fare quote (no persistence).
 */
final class QuoteData extends DataTransferObject
{
    public function __construct(
        public readonly float $originLat,
        public readonly float $originLng,
        public readonly float $destLat,
        public readonly float $destLng,
    ) {}

    public static function fromRequest(Request $request): self
    {
        return new self(
            originLat: (float) $request->float('origin_lat'),
            originLng: (float) $request->float('origin_lng'),
            destLat: (float) $request->float('dest_lat'),
            destLng: (float) $request->float('dest_lng'),
        );
    }
}
