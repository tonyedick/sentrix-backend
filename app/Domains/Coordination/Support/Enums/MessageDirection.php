<?php

declare(strict_types=1);

namespace App\Domains\Coordination\Support\Enums;

/**
 * Direction of a unit-comms message (CAD-to-radio / MDT). dispatch_to_unit is an
 * outbound order from the desk; unit_to_dispatch is the field posting back.
 * Mirrors the dir "out"/"in" of Omni's unitcomms.js.
 */
enum MessageDirection: string
{
    case DispatchToUnit = 'dispatch_to_unit';
    case UnitToDispatch = 'unit_to_dispatch';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}
