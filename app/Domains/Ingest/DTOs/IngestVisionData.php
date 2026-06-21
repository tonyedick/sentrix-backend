<?php

declare(strict_types=1);

namespace App\Domains\Ingest\DTOs;

use App\Domains\Shared\Data\DataTransferObject;
use Illuminate\Http\Request;

/**
 * A vision-provider payload posted to /api/v1/ingest/vision.
 *
 *   { organization_id, provider, camera_source_id?, site?, zone?, lat?, lng?,
 *     payload: { detections: [ { label, confidence } ], behavior? } }
 *
 * The IngestService normalizes detections[] to a single (type, confidence) by
 * picking the highest-severity detection.
 */
final class IngestVisionData extends DataTransferObject
{
    /**
     * @param  list<array{label?: string|null, confidence?: float|int|null}>  $detections
     */
    public function __construct(
        public readonly string $organizationId,
        public readonly string $provider,
        public readonly ?string $cameraSourceId,
        public readonly ?string $site,
        public readonly ?string $zone,
        public readonly ?float $lat,
        public readonly ?float $lng,
        public readonly array $detections,
        public readonly ?string $behavior,
    ) {}

    public static function fromRequest(Request $request): self
    {
        /** @var array<string, mixed> $payload */
        $payload = (array) $request->input('payload', []);

        /** @var list<array{label?: string|null, confidence?: float|int|null}> $detections */
        $detections = array_values(array_filter(
            (array) ($payload['detections'] ?? []),
            static fn (mixed $d): bool => is_array($d),
        ));

        $behavior = $payload['behavior'] ?? null;

        return new self(
            organizationId: $request->string('organization_id')->trim()->value(),
            provider: $request->string('provider')->trim()->value(),
            cameraSourceId: self::nullableString($request->input('camera_source_id')),
            site: self::nullableString($request->input('site')),
            zone: self::nullableString($request->input('zone')),
            lat: $request->has('lat') ? (float) $request->input('lat') : null,
            lng: $request->has('lng') ? (float) $request->input('lng') : null,
            detections: $detections,
            behavior: is_string($behavior) && $behavior !== '' ? $behavior : null,
        );
    }

    private static function nullableString(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }
}
