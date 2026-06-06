<?php

declare(strict_types=1);

namespace App\Domains\Emergency\DTOs;

use App\Domains\Emergency\Http\Requests\TriggerEmergencyRequest;
use App\Domains\Emergency\Support\Enums\EmergencySeverity;
use App\Domains\Shared\Data\DataTransferObject;

final class TriggerEmergencyData extends DataTransferObject
{
    public function __construct(
        public readonly EmergencySeverity $severity,
        public readonly ?string $message = null,
        public readonly ?float $lat = null,
        public readonly ?float $lng = null,
        public readonly ?string $tripId = null,
    ) {}

    public static function fromRequest(TriggerEmergencyRequest $request): self
    {
        return new self(
            severity: EmergencySeverity::from($request->string('severity', EmergencySeverity::High->value)->value()),
            message: $request->input('message'),
            lat: $request->has('lat') ? (float) $request->input('lat') : null,
            lng: $request->has('lng') ? (float) $request->input('lng') : null,
            tripId: $request->input('trip_id'),
        );
    }
}
