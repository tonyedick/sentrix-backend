<?php

declare(strict_types=1);

namespace App\Domains\Ledger\Support\Enums;

/**
 * The category of a Ledger data source.
 *
 *  - product:     a whole Sentrix product/app (Fleet, Go, ...).
 *  - service:     a backend service/pipeline (Omni pipeline, dispatch, ...).
 *  - device:      a hardware feed (access gates, sensors, ...).
 *  - integration: an external/partner feed.
 */
enum SourceKind: string
{
    case Product = 'product';
    case Service = 'service';
    case Device = 'device';
    case Integration = 'integration';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}
