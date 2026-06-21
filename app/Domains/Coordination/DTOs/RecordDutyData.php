<?php

declare(strict_types=1);

namespace App\Domains\Coordination\DTOs;

use App\Domains\Coordination\Support\Enums\DutyAction;
use App\Domains\Coordination\Support\Enums\DutyScopeType;
use App\Domains\Shared\Data\DataTransferObject;
use Illuminate\Http\Request;

final class RecordDutyData extends DataTransferObject
{
    public function __construct(
        public readonly DutyAction $action,
        public readonly DutyScopeType $scopeType,
        public readonly string $scopeId,
        public readonly string $personName,
        public readonly ?string $role = null,
    ) {}

    public static function fromRequest(Request $request): self
    {
        $role = $request->filled('role') ? $request->string('role')->trim()->value() : null;

        return new self(
            action: DutyAction::from($request->string('action')->value()),
            scopeType: DutyScopeType::from($request->string('scope_type')->value()),
            scopeId: $request->string('scope_id')->trim()->value(),
            personName: $request->string('person_name')->trim()->value(),
            role: $role,
        );
    }
}
