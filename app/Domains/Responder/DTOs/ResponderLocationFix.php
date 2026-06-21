<?php

declare(strict_types=1);

namespace App\Domains\Responder\DTOs;

use App\Domains\Shared\Data\DataTransferObject;
use Carbon\CarbonImmutable;

final class ResponderLocationFix extends DataTransferObject
{
    public function __construct(
        public readonly string $clientFixId,
        public readonly float $lat,
        public readonly float $lng,
        public readonly CarbonImmutable $recordedAt,
        public readonly ?float $accuracy = null,
        public readonly ?float $speed = null,
        public readonly ?float $heading = null,
    ) {}

    /**
     * @param  array<string, mixed>  $fix
     */
    public static function fromArray(array $fix): self
    {
        return new self(
            clientFixId: (string) $fix['client_fix_id'],
            lat: (float) $fix['lat'],
            lng: (float) $fix['lng'],
            recordedAt: CarbonImmutable::parse($fix['recorded_at']),
            accuracy: isset($fix['accuracy']) ? (float) $fix['accuracy'] : null,
            speed: isset($fix['speed']) ? (float) $fix['speed'] : null,
            heading: isset($fix['heading']) ? (float) $fix['heading'] : null,
        );
    }
}
