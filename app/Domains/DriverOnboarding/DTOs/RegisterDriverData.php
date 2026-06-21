<?php

declare(strict_types=1);

namespace App\Domains\DriverOnboarding\DTOs;

use App\Domains\Shared\Data\DataTransferObject;
use Illuminate\Http\Request;

/**
 * Vehicle details captured when a user registers as a driver.
 */
final class RegisterDriverData extends DataTransferObject
{
    public function __construct(
        public readonly ?string $vehicleMake,
        public readonly ?string $vehicleModel,
        public readonly ?string $vehiclePlate,
        public readonly ?string $vehicleColor,
    ) {}

    public static function fromRequest(Request $request): self
    {
        return new self(
            vehicleMake: $request->filled('vehicle_make') ? $request->string('vehicle_make')->trim()->value() : null,
            vehicleModel: $request->filled('vehicle_model') ? $request->string('vehicle_model')->trim()->value() : null,
            vehiclePlate: $request->filled('vehicle_plate') ? $request->string('vehicle_plate')->trim()->upper()->value() : null,
            vehicleColor: $request->filled('vehicle_color') ? $request->string('vehicle_color')->trim()->value() : null,
        );
    }
}
