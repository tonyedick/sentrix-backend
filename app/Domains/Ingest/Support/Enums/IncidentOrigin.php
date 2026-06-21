<?php

declare(strict_types=1);

namespace App\Domains\Ingest\Support\Enums;

/**
 * Who/what opened an incident — mirrors the incidents.origin column the Ingest
 * migration adds. 'human' is the default (existing, user-opened incidents);
 * the Ingest pipeline opens incidents with 'detection' or 'signal'.
 */
enum IncidentOrigin: string
{
    case Human = 'human';
    case Detection = 'detection';
    case Signal = 'signal';
    case Manual = 'manual';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}
