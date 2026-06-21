<?php

declare(strict_types=1);

namespace App\Domains\Cad\Support\Enums;

use App\Domains\Command\Support\Enums\IncidentCategory;

/**
 * The kind of field unit. Mirrors UNIT_KINDS in Omni's lib/cad.js.
 */
enum UnitKind: string
{
    case Patrol = 'patrol';
    case ArmedResponse = 'armed_response';
    case K9 = 'k9';
    case Traffic = 'traffic';
    case Ambulance = 'ambulance';
    case FireEngine = 'fire_engine';
    case Rescue = 'rescue';
    case Marine = 'marine';
    case Air = 'air';
    case Bomb = 'bomb';
    case Command = 'command';

    /**
     * Which unit kinds answer which emergency category (the closest-unit
     * kind-suitability filter). Mirrors KIND_FOR_CATEGORY in lib/cad.js.
     *
     * @return list<string>
     */
    public static function forCategory(IncidentCategory $category): array
    {
        return match ($category) {
            IncidentCategory::Crime => ['patrol', 'armed_response', 'k9', 'traffic'],
            IncidentCategory::Fire => ['fire_engine', 'rescue'],
            IncidentCategory::Traffic => ['traffic', 'patrol'],
            IncidentCategory::Medical => ['ambulance'],
            IncidentCategory::Disaster => ['rescue', 'fire_engine', 'ambulance', 'marine', 'air'],
            IncidentCategory::Civil => ['patrol', 'rescue'],
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}
