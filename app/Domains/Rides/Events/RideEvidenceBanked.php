<?php

declare(strict_types=1);

namespace App\Domains\Rides\Events;

use App\Domains\Rides\Models\RideEvidence;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * An evidence clip was banked for a ride. User-scoped — NOT an
 * OrganizationRecordEvent. (Later a listener forwards this to the Evidence vault.)
 */
final class RideEvidenceBanked
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly RideEvidence $evidence,
    ) {}
}
