<?php

declare(strict_types=1);

namespace App\Domains\DriverOnboarding\Events;

use App\Domains\DriverOnboarding\Models\Driver;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * A driver passed inspection, had Sentrix hardware installed, and went live.
 * The seam a later dispatch domain listens on to add the driver to the pool.
 * User-scoped — NOT an OrganizationRecordEvent.
 */
final class DriverActivated
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly Driver $driver,
        public readonly ?string $actorId = null,
    ) {}
}
