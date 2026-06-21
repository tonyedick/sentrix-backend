<?php

declare(strict_types=1);

namespace App\Domains\Insurance\DTOs;

use App\Domains\Insurance\Http\Requests\CreatePolicyRequest;
use App\Domains\Shared\Data\DataTransferObject;

final class CreatePolicyData extends DataTransferObject
{
    /**
     * @param  array<string, mixed>|null  $coverage
     */
    public function __construct(
        public readonly string $title,
        public readonly int $premiumCents,
        public readonly string $currency,
        public readonly ?array $coverage = null,
        public readonly ?string $periodStart = null,
        public readonly ?string $periodEnd = null,
    ) {}

    public static function fromRequest(CreatePolicyRequest $request): self
    {
        return new self(
            title: $request->string('title')->trim()->value(),
            premiumCents: $request->integer('premium_cents'),
            currency: $request->string('currency', 'USD')->upper()->value(),
            coverage: $request->input('coverage'),
            periodStart: $request->input('period_start'),
            periodEnd: $request->input('period_end'),
        );
    }
}
