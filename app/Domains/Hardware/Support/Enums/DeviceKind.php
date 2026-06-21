<?php

declare(strict_types=1);

namespace App\Domains\Hardware\Support\Enums;

/**
 * The kind of physical security hardware in the registry.
 *
 *  - gate_scanner: reads visitor pass codes at an entry point.
 *  - panic_button: fixed duress trigger.
 *  - sensor:       environmental / intrusion sensor.
 *  - controller:   gate / barrier / door controller.
 *  - beacon:       location beacon.
 *  - other:        anything not covered above.
 */
enum DeviceKind: string
{
    case GateScanner = 'gate_scanner';
    case PanicButton = 'panic_button';
    case Sensor = 'sensor';
    case Controller = 'controller';
    case Beacon = 'beacon';
    case Other = 'other';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}
