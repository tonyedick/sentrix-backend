<?php

declare(strict_types=1);

namespace App\Domains\VisionGuard\Support\Enums;

enum SourceType: string
{
    case Phone = 'phone';
    case SmartGlasses = 'smart_glasses';
    case Dashcam = 'dashcam';
    case VehicleCamera = 'vehicle_camera';
    case Cctv = 'cctv';
    case Upload = 'upload';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}
