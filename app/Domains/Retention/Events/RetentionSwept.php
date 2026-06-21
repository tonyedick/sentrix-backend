<?php

declare(strict_types=1);

namespace App\Domains\Retention\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * The lifecycle sweep re-tiered an organization's observations. Kept light: a
 * plain Dispatchable carrying the org id, resolved plan, and per-tier move
 * counts — NOT an OrganizationRecordEvent (the sweep mutates a set, not a single
 * record, and must stay cheap when run across every org on a schedule).
 *
 * @phpstan-type TierCounts array{hot: int, warm: int, cold: int}
 */
final class RetentionSwept
{
    use Dispatchable;

    /**
     * @param  array{hot: int, warm: int, cold: int}  $moved
     */
    public function __construct(
        public readonly string $organizationId,
        public readonly string $plan,
        public readonly array $moved,
    ) {}
}
