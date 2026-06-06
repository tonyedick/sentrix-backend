<?php

declare(strict_types=1);

namespace App\Domains\Incident\DTOs;

use App\Domains\Incident\Http\Requests\UpdateIncidentRequest;
use App\Domains\Shared\Data\DataTransferObject;

/**
 * Mutable incident detail fields (not status — transitions go through dedicated
 * service methods). Null keys are dropped so a PATCH only touches sent fields.
 */
final class UpdateIncidentData extends DataTransferObject
{
    public function __construct(
        public readonly ?string $title = null,
        public readonly ?string $summary = null,
        public readonly ?string $severity = null,
        public readonly ?string $assignedTo = null,
    ) {}

    public static function fromRequest(UpdateIncidentRequest $request): self
    {
        return new self(
            title: $request->input('title'),
            summary: $request->input('summary'),
            severity: $request->input('severity'),
            assignedTo: $request->input('assigned_to'),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toAttributes(): array
    {
        return array_filter([
            'title' => $this->title,
            'summary' => $this->summary,
            'severity' => $this->severity,
            'assigned_to' => $this->assignedTo,
        ], static fn (mixed $v): bool => $v !== null);
    }
}
