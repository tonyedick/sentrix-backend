<?php

declare(strict_types=1);

namespace App\Domains\Coordination\DTOs;

use App\Domains\Coordination\Support\Enums\TaskingKind;
use App\Domains\Shared\Data\DataTransferObject;
use Illuminate\Http\Request;

final class RouteTaskingData extends DataTransferObject
{
    public function __construct(
        public readonly TaskingKind $kind,
        public readonly string $title,
        public readonly ?string $ref = null,
        public readonly ?string $assignee = null,
    ) {}

    public static function fromRequest(Request $request): self
    {
        $ref = $request->filled('ref') ? $request->string('ref')->trim()->value() : null;
        $assignee = $request->filled('assignee') ? $request->string('assignee')->value() : null;

        return new self(
            kind: TaskingKind::from($request->string('kind', TaskingKind::General->value)->value()),
            title: $request->string('title')->trim()->value(),
            ref: $ref,
            assignee: $assignee,
        );
    }
}
