<?php

declare(strict_types=1);

namespace App\Domains\Hardware\DTOs;

use App\Domains\Hardware\Http\Requests\RegisterDeviceRequest;
use App\Domains\Hardware\Support\Enums\DeviceKind;
use App\Domains\Shared\Data\DataTransferObject;

final class RegisterDeviceData extends DataTransferObject
{
    /**
     * @param  array<string, mixed>|null  $metadata
     */
    public function __construct(
        public readonly DeviceKind $kind,
        public readonly string $serial,
        public readonly string $name,
        public readonly ?string $site = null,
        public readonly ?string $zone = null,
        public readonly ?array $metadata = null,
    ) {}

    public static function fromRequest(RegisterDeviceRequest $request): self
    {
        return new self(
            kind: DeviceKind::from($request->string('kind', DeviceKind::Other->value)->value()),
            serial: $request->string('serial')->trim()->value(),
            name: $request->string('name')->trim()->value(),
            site: $request->input('site'),
            zone: $request->input('zone'),
            metadata: $request->input('metadata'),
        );
    }
}
