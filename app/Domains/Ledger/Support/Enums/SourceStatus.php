<?php

declare(strict_types=1);

namespace App\Domains\Ledger\Support\Enums;

/**
 * Onboarding lifecycle of a Ledger data source.
 *
 *  - pending:   onboarded, not yet permitted to ingest.
 *  - active:    permitted to ingest writes.
 *  - suspended: temporarily blocked (reversible to active).
 *  - revoked:   permanently terminated (terminal).
 */
enum SourceStatus: string
{
    case Pending = 'pending';
    case Active = 'active';
    case Suspended = 'suspended';
    case Revoked = 'revoked';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}
