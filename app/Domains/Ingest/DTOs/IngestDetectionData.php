<?php

declare(strict_types=1);

namespace App\Domains\Ingest\DTOs;

use App\Domains\Shared\Data\DataTransferObject;
use Illuminate\Http\Request;

/**
 * A native detection event posted to /api/v1/ingest/detections.
 *
 *   { organization_id, camera_source_id?, type, confidence?, product?,
 *     site?, zone?, lat?, lng?, payload?{} }
 */
final class IngestDetectionData extends DataTransferObject
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public readonly string $organizationId,
        public readonly ?string $cameraSourceId,
        public readonly ?string $product,
        public readonly string $type,
        public readonly ?float $confidence,
        public readonly ?string $site,
        public readonly ?string $zone,
        public readonly ?float $lat,
        public readonly ?float $lng,
        public readonly array $payload,
    ) {}

    public static function fromRequest(Request $request): self
    {
        /** @var array<string, mixed> $payload */
        $payload = (array) $request->input('payload', []);

        return new self(
            organizationId: $request->string('organization_id')->trim()->value(),
            cameraSourceId: self::nullableString($request->input('camera_source_id')),
            product: self::nullableString($request->input('product')),
            type: $request->string('type')->trim()->value(),
            confidence: $request->has('confidence') ? (float) $request->input('confidence') : null,
            site: self::nullableString($request->input('site')),
            zone: self::nullableString($request->input('zone')),
            lat: $request->has('lat') ? (float) $request->input('lat') : null,
            lng: $request->has('lng') ? (float) $request->input('lng') : null,
            payload: $payload,
        );
    }

    private static function nullableString(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }
}
