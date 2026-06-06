<?php

declare(strict_types=1);

namespace App\Domains\Incident\DTOs;

use App\Domains\Incident\Http\Requests\OpenIncidentRequest;
use App\Domains\Incident\Support\Enums\IncidentSeverity;
use App\Domains\Shared\Data\DataTransferObject;

final class OpenIncidentData extends DataTransferObject
{
    public function __construct(
        public readonly string $title,
        public readonly IncidentSeverity $severity,
        public readonly ?string $summary = null,
        public readonly ?string $emergencyId = null,
        public readonly ?string $assignedTo = null,
    ) {}

    public static function fromRequest(OpenIncidentRequest $request): self
    {
        return new self(
            title: $request->string('title')->value(),
            severity: IncidentSeverity::from($request->string('severity', IncidentSeverity::Medium->value)->value()),
            summary: $request->input('summary'),
            emergencyId: $request->input('emergency_id'),
            assignedTo: $request->input('assigned_to'),
        );
    }
}
