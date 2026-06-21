<?php

declare(strict_types=1);

namespace App\Domains\Organization\Services;

use App\Domains\Organization\Models\Organization;
use RuntimeException;

/**
 * Resolves the organization that *serves* a consumer's operational event
 * (emergency / overdue trip). Consumers are served by — but not members of —
 * a Sentrix monitoring organization (see docs/adr-0001-consumer-tenancy.md).
 *
 * v1 is region-agnostic: every consumer event resolves to the configured
 * default monitoring organization. v2 will select by coverage area from the
 * event coordinates, falling back to the default.
 */
final class ServingOrganizationResolver
{
    private ?Organization $cached = null;

    /**
     * The serving organization for an event at the given coordinates.
     * Coordinates are accepted now so callers and signatures are stable when
     * region-based routing lands; they are unused in v1.
     */
    public function resolve(?float $lat = null, ?float $lng = null): Organization
    {
        return $this->defaultMonitoringOrganization();
    }

    public function defaultMonitoringOrganization(): Organization
    {
        if ($this->cached instanceof Organization) {
            return $this->cached;
        }

        /** @var string|null $id */
        $id = config('sentrix.monitoring.default_organization_id');
        /** @var string $slug */
        $slug = (string) config('sentrix.monitoring.slug', 'sentrix-monitoring');

        $organization = $id !== null
            ? Organization::query()->whereKey($id)->first()
            : Organization::query()->where('slug', $slug)->first();

        if (! $organization instanceof Organization) {
            throw new RuntimeException(
                'Sentrix monitoring organization is not provisioned. Seed it with: '
                .'php artisan db:seed --class="App\\Domains\\Organization\\Database\\Seeders\\MonitoringOrganizationSeeder"'
            );
        }

        return $this->cached = $organization;
    }
}
