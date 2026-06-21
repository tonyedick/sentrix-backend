<?php

declare(strict_types=1);

namespace App\Domains\Wallet\Events;

use App\Domains\Wallet\Models\WalletTransaction;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * A wallet top-up was confirmed and the balance credited. User-scoped — NOT an
 * OrganizationRecordEvent.
 */
final class WalletToppedUp
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly WalletTransaction $transaction,
    ) {}
}
