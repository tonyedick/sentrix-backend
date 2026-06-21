<?php

declare(strict_types=1);

namespace App\Domains\Coordination\DTOs;

use App\Domains\Shared\Data\DataTransferObject;
use Illuminate\Http\Request;

final class RequestMutualAidData extends DataTransferObject
{
    public function __construct(
        public readonly string $commandIncidentId,
        public readonly string $requestingCommandId,
        public readonly string $respondingCommandId,
        public readonly ?string $message = null,
    ) {}

    public static function fromRequest(Request $request): self
    {
        $message = $request->filled('message')
            ? $request->string('message')->trim()->value()
            : null;

        return new self(
            commandIncidentId: $request->string('command_incident_id')->value(),
            requestingCommandId: $request->string('requesting_command_id')->value(),
            respondingCommandId: $request->string('responding_command_id')->value(),
            message: $message,
        );
    }
}
