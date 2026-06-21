<?php

declare(strict_types=1);

namespace App\Domains\Community\Support\Enums;

enum AlertCategory: string
{
    case Traffic = 'traffic';
    case Security = 'security';
    case Hazard = 'hazard';
    case Accident = 'accident';
    case Roadwork = 'roadwork';
    case CommunityWatch = 'community_watch';
    case Other = 'other';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}
