<?php

declare(strict_types=1);

namespace App\Domains\Cad\DTOs;

use App\Domains\Cad\Support\Enums\UnitKind;
use App\Domains\Cad\Support\Enums\UnitStatus;
use App\Domains\Shared\Data\DataTransferObject;
use Illuminate\Http\Request;

/**
 * Partial unit patch (AVL / status / capabilities / area). Every field is
 * optional; only fields PRESENT in the request are applied (a `has*` flag tracks
 * presence so a nullable field can be explicitly cleared).
 */
final class UpdateUnitData extends DataTransferObject
{
    /**
     * @param  list<string>|null  $capabilities
     */
    public function __construct(
        public readonly ?string $callSign,
        public readonly ?UnitKind $kind,
        public readonly ?array $capabilities,
        public readonly bool $hasCapabilities,
        public readonly ?int $crew,
        public readonly ?UnitStatus $status,
        public readonly ?float $lat,
        public readonly bool $hasLat,
        public readonly ?float $lng,
        public readonly bool $hasLng,
        public readonly ?string $area,
        public readonly bool $hasArea,
    ) {}

    public static function fromRequest(Request $request): self
    {
        /** @var list<string>|null $capabilities */
        $capabilities = $request->has('capabilities')
            ? array_values($request->input('capabilities', []))
            : null;

        return new self(
            callSign: $request->filled('call_sign') ? $request->string('call_sign')->trim()->value() : null,
            kind: $request->filled('kind') ? UnitKind::from($request->string('kind')->value()) : null,
            capabilities: $capabilities,
            hasCapabilities: $request->has('capabilities'),
            crew: $request->filled('crew') ? max(1, $request->integer('crew')) : null,
            status: $request->filled('status') ? UnitStatus::from($request->string('status')->value()) : null,
            lat: $request->filled('lat') ? (float) $request->input('lat') : null,
            hasLat: $request->has('lat'),
            lng: $request->filled('lng') ? (float) $request->input('lng') : null,
            hasLng: $request->has('lng'),
            area: $request->filled('area') ? $request->string('area')->trim()->value() : null,
            hasArea: $request->has('area'),
        );
    }
}
