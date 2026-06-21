<?php

declare(strict_types=1);

namespace App\Domains\DriverOnboarding\Support\Enums;

/**
 * Required driver documents uploaded for staff review.
 */
enum DocumentType: string
{
    case License = 'license';
    case Insurance = 'insurance';
    case VehicleRegistration = 'vehicle_registration';
    case Roadworthiness = 'roadworthiness';
    case Other = 'other';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}
