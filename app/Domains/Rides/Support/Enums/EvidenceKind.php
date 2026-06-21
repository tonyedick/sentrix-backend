<?php

declare(strict_types=1);

namespace App\Domains\Rides\Support\Enums;

enum EvidenceKind: string
{
    case Video = 'video';
    case Audio = 'audio';
    case Photo = 'photo';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}
