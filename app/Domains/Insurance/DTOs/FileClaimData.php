<?php

declare(strict_types=1);

namespace App\Domains\Insurance\DTOs;

use App\Domains\Insurance\Http\Requests\FileClaimRequest;
use App\Domains\Shared\Data\DataTransferObject;

final class FileClaimData extends DataTransferObject
{
    /**
     * @param  array<string, mixed>|null  $metadata
     */
    public function __construct(
        public readonly string $policyId,
        public readonly int $amountCents,
        public readonly string $currency,
        public readonly ?string $description = null,
        public readonly ?array $metadata = null,
    ) {}

    public static function fromRequest(FileClaimRequest $request): self
    {
        return new self(
            policyId: $request->string('policy_id')->value(),
            amountCents: $request->integer('amount_cents'),
            currency: $request->string('currency', 'USD')->upper()->value(),
            description: $request->input('description'),
            metadata: $request->input('metadata'),
        );
    }
}
