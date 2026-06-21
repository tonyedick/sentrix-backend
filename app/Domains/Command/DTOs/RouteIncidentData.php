<?php

declare(strict_types=1);

namespace App\Domains\Command\DTOs;

use App\Domains\Command\Support\Enums\CommandIncidentSource;
use App\Domains\Command\Support\Enums\IncidentSeverity;
use App\Domains\Shared\Data\DataTransferObject;
use Illuminate\Http\Request;

/**
 * Inbound payload for routing an incident. `category` may be supplied directly
 * or derived (by the routing service) from the free-text summary. `country`
 * defaults to NG when not given.
 */
final class RouteIncidentData extends DataTransferObject
{
    public function __construct(
        public readonly IncidentSeverity $severity,
        public readonly string $summary,
        public readonly ?string $category = null,
        public readonly string $country = 'NG',
        public readonly ?float $lat = null,
        public readonly ?float $lng = null,
        public readonly CommandIncidentSource $sourceType = CommandIncidentSource::Manual,
        public readonly ?string $sourceRef = null,
    ) {}

    public static function fromRequest(Request $request): self
    {
        return new self(
            severity: IncidentSeverity::from($request->string('severity')->value()),
            summary: $request->string('summary')->trim()->value(),
            category: $request->filled('category') ? $request->string('category')->value() : null,
            country: strtoupper($request->string('country', 'NG')->trim()->value()),
            lat: $request->filled('lat') ? (float) $request->input('lat') : null,
            lng: $request->filled('lng') ? (float) $request->input('lng') : null,
            sourceType: CommandIncidentSource::from(
                $request->string('source_type', CommandIncidentSource::Manual->value)->value()
            ),
            sourceRef: $request->filled('source_ref') ? $request->string('source_ref')->value() : null,
        );
    }
}
