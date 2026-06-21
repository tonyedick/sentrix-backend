<?php

declare(strict_types=1);

namespace App\Domains\DriverOnboarding\Support\Enums;

/**
 * The driver onboarding pipeline stages. The progression is:
 * documents_review -> documents_approved -> inspection_booked -> active.
 * (`vetting` is an in-person transient state; `rejected`/`suspended` are
 * terminal-ish off-ramps.) Mirrors the SentrixGo rides router driver stages.
 */
enum DriverStage: string
{
    case DocumentsReview = 'documents_review';
    case DocumentsApproved = 'documents_approved';
    case InspectionBooked = 'inspection_booked';
    case Vetting = 'vetting';
    case Active = 'active';
    case Rejected = 'rejected';
    case Suspended = 'suspended';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}
