<?php

declare(strict_types=1);

namespace App\Domains\Wallet\Events;

use App\Domains\Wallet\Models\ReferralClaim;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * A referral code was claimed and both sides credited. User-scoped — NOT an
 * OrganizationRecordEvent.
 */
final class ReferralClaimed
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly ReferralClaim $claim,
    ) {}
}
