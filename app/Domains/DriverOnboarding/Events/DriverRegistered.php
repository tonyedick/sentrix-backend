<?php

declare(strict_types=1);

namespace App\Domains\DriverOnboarding\Events;

use App\Domains\DriverOnboarding\Models\Driver;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * A user registered as a driver and entered document review. User-scoped —
 * NOT an OrganizationRecordEvent.
 */
final class DriverRegistered
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly Driver $driver,
    ) {}
}
