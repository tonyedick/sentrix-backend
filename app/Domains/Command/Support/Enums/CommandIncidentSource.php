<?php

declare(strict_types=1);

namespace App\Domains\Command\Support\Enums;

/**
 * Where a command incident originated. An alert from a product feed, a raised
 * emergency, a personal SOS, or a manually opened envelope.
 */
enum CommandIncidentSource: string
{
    case Alert = 'alert';
    case Emergency = 'emergency';
    case Sos = 'sos';
    case Manual = 'manual';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}
