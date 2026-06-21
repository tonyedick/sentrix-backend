<?php

declare(strict_types=1);

namespace App\Domains\Organization\Database\Seeders;

use App\Domains\Organization\DTOs\CreateOrganizationData;
use App\Domains\Organization\Models\Organization;
use App\Domains\Organization\Services\OrganizationService;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Provisions the canonical Sentrix monitoring organization that *serves*
 * consumer emergencies / overdue trips (see docs/adr-0001-consumer-tenancy.md).
 * Idempotent: re-running is a no-op once the org exists.
 *
 * Depends on the permission catalogue (org role provisioning), so it must run
 * after PermissionCatalogueSeeder.
 */
final class MonitoringOrganizationSeeder extends Seeder
{
    public function run(OrganizationService $organizations): void
    {
        $slug = (string) config('sentrix.monitoring.slug', 'sentrix-monitoring');

        if (Organization::query()->where('slug', $slug)->exists()) {
            return;
        }

        $owner = User::query()->firstOrCreate(
            ['email' => (string) config('sentrix.monitoring.owner_email', 'monitoring@sentrix.test')],
            [
                'name' => (string) config('sentrix.monitoring.name', 'Sentrix Monitoring'),
                'password' => Hash::make(Str::random(40)),
            ],
        );

        $organizations->create(new CreateOrganizationData(
            name: (string) config('sentrix.monitoring.name', 'Sentrix Monitoring'),
            owner: $owner,
            slug: $slug,
        ));
    }
}
