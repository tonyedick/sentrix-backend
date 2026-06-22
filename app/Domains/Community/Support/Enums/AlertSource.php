<?php

declare(strict_types=1);

namespace App\Domains\Community\Support\Enums;

/**
 * Provenance of a community alert. `community` reports arrive unverified and
 * earn trust through crowd verification; `official` (Sentrix Staff) and `ai`
 * (Sentrix Core news/internet intelligence) alerts are published verified.
 */
enum AlertSource: string
{
    case Community = 'community';
    case Official = 'official';
    case Ai = 'ai';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }

    /**
     * Staff/Core sources are trusted on arrival (published verified).
     */
    public function isTrusted(): bool
    {
        return $this === self::Official || $this === self::Ai;
    }
}
