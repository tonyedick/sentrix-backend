<?php

declare(strict_types=1);

namespace App\Domains\Access\Support\Enums;

/**
 * The kind of visitor pass.
 *
 *  - single:    consumed on first successful entry (one-shot guest code).
 *  - recurring: reusable within its validity window (e.g. a regular contractor).
 *  - domestic:  reusable, for domestic staff tied to a host/unit.
 */
enum PassType: string
{
    case Single = 'single';
    case Recurring = 'recurring';
    case Domestic = 'domestic';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }

    /**
     * Whether a pass of this type is consumed (spent) on first granted entry.
     */
    public function isSingleUse(): bool
    {
        return $this === self::Single;
    }
}
