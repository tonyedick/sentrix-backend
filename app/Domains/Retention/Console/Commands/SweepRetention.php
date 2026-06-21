<?php

declare(strict_types=1);

namespace App\Domains\Retention\Console\Commands;

use App\Domains\Organization\Models\Organization;
use App\Domains\Retention\Services\RetentionService;
use App\Domains\Retention\Support\RetentionPolicy;
use Illuminate\Console\Command;

/**
 * Re-tiers Evidence observations across EVERY organization by the age of
 * observed_at against each org's resolved plan windows. Mirrors the per-org
 * POST retention/sweep, looped over all orgs. The sweep is idempotent and
 * row-atomic (one UPDATE per tier), so it is safe to run on a schedule with
 * withoutOverlapping().
 */
final class SweepRetention extends Command
{
    protected $signature = 'retention:sweep';

    protected $description = 'Re-tier Evidence observations across all organizations by plan retention windows.';

    public function handle(RetentionService $retention): int
    {
        $organizations = 0;
        $moved = 0;

        Organization::query()->chunkById(100, function ($orgs) use ($retention, &$organizations, &$moved): void {
            foreach ($orgs as $organization) {
                $policy = RetentionPolicy::forOrganization($organization);
                $result = $retention->sweep($organization, $policy);

                $organizations++;
                $moved += $result['hot'] + $result['warm'] + $result['cold'];
            }
        });

        $this->info("Swept {$organizations} organization(s); re-tiered {$moved} observation(s).");

        return self::SUCCESS;
    }
}
