<?php

declare(strict_types=1);

namespace App\Domains\Command\Support\Enums;

/**
 * Emergency taxonomy for the Command domain. Every routed incident maps to ONE
 * category, which selects the lead agency. This domain owns its own copy (it is
 * platform/national-scoped and decoupled from the org Incident domain).
 */
enum IncidentCategory: string
{
    case Crime = 'crime';
    case Fire = 'fire';
    case Traffic = 'traffic';
    case Medical = 'medical';
    case Disaster = 'disaster';
    case Civil = 'civil';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}
