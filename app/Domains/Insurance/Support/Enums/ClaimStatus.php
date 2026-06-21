<?php

declare(strict_types=1);

namespace App\Domains\Insurance\Support\Enums;

/**
 * Lifecycle state of an insurance claim.
 *
 *   filed    → submitted, awaiting a decision.
 *   approved → accepted by the insurer (decision applied).
 *   rejected → declined by the insurer (decision applied, terminal).
 *   paid     → approved claim that has been settled (terminal).
 *
 * A claim may only be decided while still in the `filed` state; deciding an
 * already-decided claim is an illegal transition.
 */
enum ClaimStatus: string
{
    case Filed = 'filed';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Paid = 'paid';

    /**
     * Whether a decision (approve/reject) may still be applied to this claim.
     */
    public function isDecidable(): bool
    {
        return $this === self::Filed;
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}
