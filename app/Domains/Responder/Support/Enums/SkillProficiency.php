<?php

declare(strict_types=1);

namespace App\Domains\Responder\Support\Enums;

enum SkillProficiency: string
{
    case Trainee = 'trainee';
    case Trained = 'trained';
    case Expert = 'expert';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}
