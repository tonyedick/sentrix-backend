<?php

declare(strict_types=1);

namespace App\Domains\Evidence\DTOs;

use App\Domains\Evidence\Support\Enums\ObservationKind;
use App\Domains\Evidence\Support\Enums\ObservationSeverity;
use App\Domains\Shared\Data\DataTransferObject;

/**
 * One observation in a batch-ingest call. Plain value object; validated upstream
 * by the Form Request and assembled by ObservationData::fromArray().
 */
final class ObservationData extends DataTransferObject
{
    /**
     * @param  array<string, mixed>|null  $attributes
     */
    public function __construct(
        public readonly ObservationKind $kind,
        public readonly ?string $label = null,
        public readonly ?array $attributes = null,
        public readonly ?string $plate = null,
        public readonly ?float $confidence = null,
        public readonly ?ObservationSeverity $severity = null,
        public readonly ?string $snapshotUrl = null,
        public readonly ?string $clipUrl = null,
        public readonly ?float $lat = null,
        public readonly ?float $lng = null,
        public readonly ?string $observedAt = null,
        public readonly ?string $cameraSourceId = null,
    ) {}

    /**
     * @param  array<string, mixed>  $row
     */
    public static function fromArray(array $row): self
    {
        $plate = isset($row['plate']) ? strtoupper(trim((string) $row['plate'])) : null;

        // Backfill the denormalized plate column from the attribute bag when the
        // caller did not pass it explicitly (mirrors the Omni vault, which reads
        // attrs.plate for vehicle lookup).
        if ($plate === null && isset($row['attributes']['plate'])) {
            $plate = strtoupper(trim((string) $row['attributes']['plate']));
        }

        return new self(
            kind: ObservationKind::from((string) $row['kind']),
            label: isset($row['label']) ? (string) $row['label'] : null,
            attributes: $row['attributes'] ?? null,
            plate: ($plate === null || $plate === '') ? null : $plate,
            confidence: isset($row['confidence']) ? (float) $row['confidence'] : null,
            severity: isset($row['severity']) ? ObservationSeverity::from((string) $row['severity']) : null,
            snapshotUrl: isset($row['snapshot_url']) ? (string) $row['snapshot_url'] : null,
            clipUrl: isset($row['clip_url']) ? (string) $row['clip_url'] : null,
            lat: isset($row['lat']) ? (float) $row['lat'] : null,
            lng: isset($row['lng']) ? (float) $row['lng'] : null,
            observedAt: isset($row['observed_at']) ? (string) $row['observed_at'] : null,
            cameraSourceId: isset($row['camera_source_id']) ? (string) $row['camera_source_id'] : null,
        );
    }
}
