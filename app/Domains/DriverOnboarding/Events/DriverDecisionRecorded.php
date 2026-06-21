<?php

declare(strict_types=1);

namespace App\Domains\DriverOnboarding\Events;

use App\Domains\DriverOnboarding\Models\Driver;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Staff recorded a document-review decision on a driver (approve unlocks
 * inspection booking; reject sends the application back). User-scoped —
 * NOT an OrganizationRecordEvent.
 */
final class DriverDecisionRecorded
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly Driver $driver,
        public readonly string $decision,
        public readonly ?string $actorId = null,
    ) {}
}
