<?php

declare(strict_types=1);

namespace App\Domains\Cad\DTOs;

use App\Domains\Cad\Support\Enums\UnitKind;
use App\Domains\Cad\Support\Enums\UnitStatus;
use App\Domains\Shared\Data\DataTransferObject;
use Illuminate\Http\Request;

final class CreateUnitData extends DataTransferObject
{
    /**
     * @param  list<string>  $capabilities
     */
    public function __construct(
        public readonly string $commandId,
        public readonly string $callSign,
        public readonly UnitKind $kind,
        public readonly array $capabilities,
        public readonly int $crew,
        public readonly UnitStatus $status,
        public readonly ?float $lat = null,
        public readonly ?float $lng = null,
        public readonly ?string $area = null,
    ) {}

    public static function fromRequest(Request $request): self
    {
        /** @var list<string> $capabilities */
        $capabilities = array_values($request->input('capabilities', []));

        return new self(
            commandId: $request->string('command_id')->value(),
            callSign: $request->string('call_sign')->trim()->value(),
            kind: UnitKind::from($request->string('kind', UnitKind::Patrol->value)->value()),
            capabilities: $capabilities,
            crew: $request->filled('crew') ? max(1, $request->integer('crew')) : 1,
            status: UnitStatus::from($request->string('status', UnitStatus::Available->value)->value()),
            lat: $request->filled('lat') ? (float) $request->input('lat') : null,
            lng: $request->filled('lng') ? (float) $request->input('lng') : null,
            area: $request->filled('area') ? $request->string('area')->trim()->value() : null,
        );
    }
}
