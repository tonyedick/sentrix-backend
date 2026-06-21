<?php

declare(strict_types=1);

namespace App\Domains\DriverOnboarding\DTOs;

use App\Domains\Shared\Data\DataTransferObject;
use Illuminate\Http\Request;

/**
 * A driver booking an in-person inspection slot at a vetting center.
 */
final class BookInspectionData extends DataTransferObject
{
    public function __construct(
        public readonly string $vettingCenterId,
        public readonly string $slot,
    ) {}

    public static function fromRequest(Request $request): self
    {
        return new self(
            vettingCenterId: $request->string('vetting_center_id')->value(),
            slot: $request->string('slot')->trim()->value(),
        );
    }
}
