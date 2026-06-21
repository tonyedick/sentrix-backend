<?php

declare(strict_types=1);

namespace App\Domains\Ledger\DTOs;

use App\Domains\Ledger\Http\Requests\IngestWriteRequest;
use App\Domains\Shared\Data\DataTransferObject;

final class IngestWriteData extends DataTransferObject
{
    public function __construct(
        public readonly string $type,
        public readonly ?string $summary = null,
        public readonly ?string $ref = null,
        public readonly ?string $organizationId = null,
    ) {}

    public static function fromRequest(IngestWriteRequest $request): self
    {
        return new self(
            type: $request->string('type')->trim()->value(),
            summary: $request->input('summary'),
            ref: $request->input('ref'),
            organizationId: $request->input('organization_id'),
        );
    }
}
