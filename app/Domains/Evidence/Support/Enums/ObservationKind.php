<?php

declare(strict_types=1);

namespace App\Domains\Evidence\Support\Enums;

/**
 * The drawers of the forensic vault. Every observation lands in exactly one
 * kind, mirroring the Omni evidence vault's OBS_KINDS (faces, vehicles, plates,
 * objects, scenes, audio, behaviour, thermal, sensor).
 */
enum ObservationKind: string
{
    case Face = 'face';
    case Vehicle = 'vehicle';
    case Plate = 'plate';
    case Object = 'object';
    case Scene = 'scene';
    case Audio = 'audio';
    case Behavior = 'behavior';
    case Thermal = 'thermal';
    case Sensor = 'sensor';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}
