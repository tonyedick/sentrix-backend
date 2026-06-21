<?php

declare(strict_types=1);

namespace App\Domains\Command\Support\Enums;

/**
 * Lifecycle of a command incident envelope. resolved/stood_down are terminal.
 */
enum CommandIncidentStatus: string
{
    case New = 'new';
    case Acknowledged = 'acknowledged';
    case EnRoute = 'en_route';
    case OnScene = 'on_scene';
    case Resolved = 'resolved';
    case StoodDown = 'stood_down';

    public function isTerminal(): bool
    {
        return $this === self::Resolved || $this === self::StoodDown;
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}
