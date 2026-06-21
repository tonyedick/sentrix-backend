<?php

declare(strict_types=1);

namespace App\Domains\RidesOps\DTOs;

use App\Domains\Shared\Data\DataTransferObject;
use Illuminate\Http\Request;

/**
 * Manual dispatch override payload. The operator names the driver to dispatch;
 * the ride's denormalised driver snapshot is overwritten (the real dispatch loop
 * is simulated — the canonical driver pool lands with the Driver domain).
 */
final class ReassignData extends DataTransferObject
{
    public function __construct(
        public readonly ?string $driverName,
    ) {}

    public static function fromRequest(Request $request): self
    {
        return new self(
            driverName: $request->filled('driver_name')
                ? $request->string('driver_name')->trim()->value()
                : null,
        );
    }
}
