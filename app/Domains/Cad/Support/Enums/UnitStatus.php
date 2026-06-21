<?php

declare(strict_types=1);

namespace App\Domains\Cad\Support\Enums;

/**
 * Field-unit status lifecycle (CAD "10-codes" in plain words). Forward through a
 * call then cleared back to available; unavailable/out_of_service are off-call.
 * Mirrors UNIT_STATUSES in Omni's lib/cad.js.
 */
enum UnitStatus: string
{
    case Available = 'available';
    case Assigned = 'assigned';
    case EnRoute = 'en_route';
    case OnScene = 'on_scene';
    case Unavailable = 'unavailable';
    case OutOfService = 'out_of_service';

    /**
     * Whether the unit is committed to a call (its incident link is live).
     */
    public function isOnCall(): bool
    {
        return $this === self::Assigned || $this === self::EnRoute || $this === self::OnScene;
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}
