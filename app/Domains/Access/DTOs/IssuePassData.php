<?php

declare(strict_types=1);

namespace App\Domains\Access\DTOs;

use App\Domains\Access\Support\Enums\PassType;
use App\Domains\Shared\Data\DataTransferObject;
use Illuminate\Http\Request;

/**
 * Input for minting a visitor pass. The host is the authenticated issuer
 * (resolved in the service), not part of the request body.
 */
final class IssuePassData extends DataTransferObject
{
    /**
     * @param  array<string, mixed>|null  $metadata
     */
    public function __construct(
        public readonly string $visitorName,
        public readonly PassType $type,
        public readonly ?string $validFrom,
        public readonly ?string $validUntil,
        public readonly ?array $metadata,
    ) {}

    public static function fromRequest(Request $request): self
    {
        return new self(
            visitorName: $request->string('visitor_name')->trim()->value(),
            type: PassType::from($request->string('type', PassType::Single->value)->value()),
            validFrom: $request->input('valid_from'),
            validUntil: $request->input('valid_until'),
            metadata: $request->input('metadata'),
        );
    }
}
