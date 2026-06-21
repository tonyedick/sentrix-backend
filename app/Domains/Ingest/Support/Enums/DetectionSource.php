<?php

declare(strict_types=1);

namespace App\Domains\Ingest\Support\Enums;

/**
 * Where a detection_events row originated.
 *
 *   detection → a native Sentrix detection event (e.g. an on-device model).
 *   vision    → a third-party / vision-provider payload normalized by Ingest.
 *   signal    → a SafeSignal cross-product, life-safety report.
 */
enum DetectionSource: string
{
    case Detection = 'detection';
    case Vision = 'vision';
    case Signal = 'signal';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}
