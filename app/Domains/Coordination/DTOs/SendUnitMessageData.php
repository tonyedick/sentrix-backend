<?php

declare(strict_types=1);

namespace App\Domains\Coordination\DTOs;

use App\Domains\Coordination\Support\Enums\MessageDirection;
use App\Domains\Shared\Data\DataTransferObject;
use Illuminate\Http\Request;

final class SendUnitMessageData extends DataTransferObject
{
    public function __construct(
        public readonly string $body,
        public readonly MessageDirection $direction,
        public readonly ?string $commandIncidentId = null,
    ) {}

    public static function fromRequest(Request $request): self
    {
        $incidentId = $request->filled('command_incident_id')
            ? $request->string('command_incident_id')->value()
            : null;

        return new self(
            body: $request->string('body')->trim()->value(),
            direction: MessageDirection::from(
                $request->string('direction', MessageDirection::DispatchToUnit->value)->value()
            ),
            commandIncidentId: $incidentId,
        );
    }
}
