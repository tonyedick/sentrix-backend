<?php

declare(strict_types=1);

namespace Tests\Feature\Organization;

use App\Domains\Authorization\Database\Seeders\PermissionCatalogueSeeder;
use App\Domains\Organization\Database\Seeders\MonitoringOrganizationSeeder;
use App\Domains\Organization\DTOs\CreateOrganizationData;
use App\Domains\Organization\Models\Organization;
use App\Domains\Organization\Services\OrganizationService;
use App\Domains\Organization\Services\ServingOrganizationResolver;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

final class ServingOrganizationResolverTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionCatalogueSeeder::class);
    }

    public function test_resolves_default_monitoring_org_by_slug(): void
    {
        $this->seed(MonitoringOrganizationSeeder::class);

        $org = app(ServingOrganizationResolver::class)->resolve(6.5244, 3.3792);

        $this->assertSame('sentrix-monitoring', $org->slug);
    }

    public function test_explicit_config_id_takes_precedence_over_slug(): void
    {
        $this->seed(MonitoringOrganizationSeeder::class); // slug org exists

        // A different org pointed to by explicit config id.
        $owner = User::factory()->create();
        $custom = app(OrganizationService::class)->create(new CreateOrganizationData(
            name: 'Regional Center',
            owner: $owner,
        ));
        config(['sentrix.monitoring.default_organization_id' => $custom->getKey()]);

        $resolved = app(ServingOrganizationResolver::class)->resolve();

        $this->assertSame($custom->getKey(), $resolved->getKey());
    }

    public function test_throws_when_monitoring_org_not_provisioned(): void
    {
        $this->expectException(RuntimeException::class);

        app(ServingOrganizationResolver::class)->resolve();
    }

    public function test_seeder_is_idempotent(): void
    {
        $this->seed(MonitoringOrganizationSeeder::class);
        $this->seed(MonitoringOrganizationSeeder::class);

        $this->assertSame(1, Organization::query()->where('slug', 'sentrix-monitoring')->count());
    }
}
