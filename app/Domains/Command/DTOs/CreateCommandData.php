<?php

declare(strict_types=1);

namespace App\Domains\Command\DTOs;

use App\Domains\Command\Support\Enums\CommandTier;
use App\Domains\Shared\Data\DataTransferObject;
use Illuminate\Http\Request;

final class CreateCommandData extends DataTransferObject
{
    public function __construct(
        public readonly string $agencyId,
        public readonly CommandTier $tier,
        public readonly string $name,
        public readonly ?string $parentId = null,
        public readonly ?string $area = null,
        public readonly ?float $lat = null,
        public readonly ?float $lng = null,
    ) {}

    public static function fromRequest(Request $request): self
    {
        return new self(
            agencyId: $request->string('agency_id')->value(),
            tier: CommandTier::from($request->string('tier')->value()),
            name: $request->string('name')->trim()->value(),
            parentId: $request->filled('parent_id') ? $request->string('parent_id')->value() : null,
            area: $request->filled('area') ? $request->string('area')->trim()->value() : null,
            lat: $request->filled('lat') ? (float) $request->input('lat') : null,
            lng: $request->filled('lng') ? (float) $request->input('lng') : null,
        );
    }
}
