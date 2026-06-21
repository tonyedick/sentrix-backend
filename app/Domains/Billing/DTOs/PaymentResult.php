<?php

declare(strict_types=1);

namespace App\Domains\Billing\DTOs;

final readonly class PaymentResult
{
    public function __construct(
        public bool $successful,
        public string $reference,
        public ?string $paymentMethodLabel = null,
        public ?string $failureReason = null,
    ) {}
}
