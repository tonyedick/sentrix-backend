<?php

declare(strict_types=1);

namespace Tests\Feature\Authorization;

use App\Domains\Authorization\Database\Seeders\PermissionCatalogueSeeder;
use App\Domains\Authorization\Services\RoleService;
use App\Domains\Organization\DTOs\CreateOrganizationData;
use App\Domains\Organization\Models\Organization;
use App\Domains\Organization\Services\OrganizationService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

final class SuperAdminAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake(); // workspace provisioning side effects are queued
        $this->seed(PermissionCatalogueSeeder::class);
    }

    public function test_super_admin_is_recognised_globally_without_org_context(): void
    {
        $admin = $this->superAdmin();

        // Gate::before grants every ability, even ones with no permission row,
        // and with no active organization context.
        $this->assertTrue($admin->isSuperAdmin());
        $this->assertTrue($admin->can('anything.at.all'));
    }

    public function test_super_admin_can_read_an_organization_they_do_not_belong_to(): void
    {
        $organization = $this->organization();
        $admin = $this->superAdmin();

        $this->assertFalse($admin->belongsToOrganization($organization));

        $this->actingAs($admin, 'sanctum')
            ->getJson("/api/v1/organizations/{$organization->id}/members")
            ->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_super_admin_can_update_an_organization_without_membership(): void
    {
        $organization = $this->organization();
        $admin = $this->superAdmin();

        $this->actingAs($admin, 'sanctum')
            ->patchJson("/api/v1/organizations/{$organization->id}", ['name' => 'Renamed by Ops'])
            ->assertOk()
            ->assertJsonPath('data.name', 'Renamed by Ops');
    }

    public function test_non_member_without_super_admin_is_forbidden(): void
    {
        $organization = $this->organization();
        $outsider = User::factory()->create();

        $this->actingAs($outsider, 'sanctum')
            ->getJson("/api/v1/organizations/{$organization->id}/members")
            ->assertForbidden();
    }

    private function organization(): Organization
    {
        return app(OrganizationService::class)->create(new CreateOrganizationData(
            name: 'Acme Inc',
            owner: User::factory()->create(),
        ));
    }

    private function superAdmin(): User
    {
        $admin = User::factory()->create();
        app(RoleService::class)->assignSuperAdmin($admin);

        return $admin;
    }
}
