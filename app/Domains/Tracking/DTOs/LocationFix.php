<?php

declare(strict_types=1);

namespace App\Domains\Tracking\DTOs;

use Carbon\CarbonImmutable;

/**
 * One device-captured position. `clientFixId` is device-assigned and immutable
 * per fix, which is what makes ingestion idempotent across retries.
 */
final readonly class LocationFix
{
    public function __construct(
        public string $clientFixId,
        public float $lat,
        public float $lng,
        public CarbonImmutable $recordedAt,
        public ?float $accuracy = null,
        public ?float $speed = null,
        public ?float $heading = null,
    ) {}

    /**
     * @param  array<string, mixed>  $fix
     */
    public static function fromArray(array $fix): self
    {
        return new self(
            clientFixId: (string) $fix['id'],
            lat: (float) $fix['lat'],
            lng: (float) $fix['lng'],
            recordedAt: CarbonImmutable::parse((string) $fix['recorded_at']),
            accuracy: isset($fix['accuracy']) ? (float) $fix['accuracy'] : null,
            speed: isset($fix['speed']) ? (float) $fix['speed'] : null,
            heading: isset($fix['heading']) ? (float) $fix['heading'] : null,
        );
    }
}
