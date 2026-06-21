<?php

declare(strict_types=1);

namespace App\Domains\Cad\DTOs;

use App\Domains\Cad\Support\Enums\BoloKind;
use App\Domains\Shared\Data\DataTransferObject;
use Illuminate\Http\Request;

final class CreateBoloData extends DataTransferObject
{
    /**
     * @param  array<string, mixed>|null  $details
     */
    public function __construct(
        public readonly string $commandId,
        public readonly BoloKind $kind,
        public readonly string $subject,
        public readonly ?array $details = null,
    ) {}

    public static function fromRequest(Request $request): self
    {
        /** @var array<string, mixed>|null $details */
        $details = $request->input('details');

        return new self(
            commandId: $request->string('command_id')->value(),
            kind: BoloKind::from($request->string('kind', BoloKind::General->value)->value()),
            subject: $request->string('subject')->trim()->value(),
            details: $details,
        );
    }
}
