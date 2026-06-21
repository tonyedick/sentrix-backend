<?php

declare(strict_types=1);

namespace App\Domains\Coordination\Support\Enums;

/**
 * What a tasking references. Mirrors the SentrixGoBackend command router's
 * ref_kind (incident|sos|detection) plus a `general` catch-all for ad-hoc work.
 */
enum TaskingKind: string
{
    case Incident = 'incident';
    case Sos = 'sos';
    case Detection = 'detection';
    case General = 'general';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}
