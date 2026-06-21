<?php

declare(strict_types=1);

namespace App\Domains\Responder\Support\Enums;

/**
 * Lifecycle of a responder credential.
 *
 *   pending  → recorded, not yet verified
 *   verified → confirmed valid
 *   expired  → past its expiry date (set by the expiry sweep)
 *   revoked  → withdrawn by an administrator
 *
 * Only `verified` certifications count toward a responder's active capabilities.
 */
enum CertificationStatus: string
{
    case Pending = 'pending';
    case Verified = 'verified';
    case Expired = 'expired';
    case Revoked = 'revoked';

    public function isActive(): bool
    {
        return $this === self::Verified;
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}
