<?php

declare(strict_types=1);

namespace App\Domains\RidesMarket\DTOs;

use App\Domains\RidesMarket\Support\Enums\BidKind;
use App\Domains\Shared\Data\DataTransferObject;
use Illuminate\Http\Request;

/**
 * A driver's bid on an open offer: amount in cents, accept or counter, and an
 * optional driver display name (the caller is assumed driver-side — see service).
 */
final class PlaceBidData extends DataTransferObject
{
    public function __construct(
        public readonly int $amountCents,
        public readonly BidKind $kind,
        public readonly ?string $driverName,
    ) {}

    public static function fromRequest(Request $request): self
    {
        return new self(
            amountCents: $request->integer('amount_cents'),
            kind: BidKind::from($request->string('kind')->value()),
            driverName: $request->filled('driver_name') ? $request->string('driver_name')->trim()->value() : null,
        );
    }
}
