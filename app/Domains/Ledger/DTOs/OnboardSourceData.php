<?php

declare(strict_types=1);

namespace App\Domains\Ledger\DTOs;

use App\Domains\Ledger\Http\Requests\OnboardSourceRequest;
use App\Domains\Ledger\Support\Enums\SourceKind;
use App\Domains\Shared\Data\DataTransferObject;

final class OnboardSourceData extends DataTransferObject
{
    /**
     * @param  array<string, mixed>|null  $metadata
     */
    public function __construct(
        public readonly string $name,
        public readonly SourceKind $kind,
        public readonly ?string $product = null,
        public readonly ?string $slug = null,
        public readonly ?string $organizationId = null,
        public readonly ?array $metadata = null,
    ) {}

    public static function fromRequest(OnboardSourceRequest $request): self
    {
        $slug = $request->filled('slug')
            ? $request->string('slug')->trim()->value()
            : null;

        return new self(
            name: $request->string('name')->trim()->value(),
            kind: SourceKind::from($request->string('kind', SourceKind::Service->value)->value()),
            product: $request->input('product'),
            slug: $slug,
            organizationId: $request->input('organization_id'),
            metadata: $request->input('metadata'),
        );
    }
}
