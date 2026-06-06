<?php

declare(strict_types=1);

namespace Tests\Feature\Authorization;

use App\Domains\Authorization\Database\Seeders\PermissionCatalogueSeeder;
use App\Domains\Authorization\Support\Enums\DefaultPermission;
use App\Domains\Authorization\Support\Enums\OrganizationRole;
use App\Domains\Organization\Models\Organization;
use App\Domains\Organization\Services\MembershipService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class RbacSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionCatalogueSeeder::class);
    }

    /**
     * @return array{0: User, 1: string}
     */
    private function ownerWithOrganization(): array
    {
        $owner = User::factory()->create();
        $organizationId = $this->actingAs($owner, 'sanctum')
            ->postJson('/api/v1/organizations', ['name' => 'Acme Inc'])
            ->json('data.id');

        return [$owner, $organizationId];
    }

    /**
     * Create a custom role in the org (as the owner) and return its id.
     *
     * @param  list<string>  $permissions
     */
    private function createRole(User $owner, string $organizationId, string $name, array $permissions): string
    {
        return $this->actingAs($owner, 'sanctum')
            ->postJson("/api/v1/organizations/{$organizationId}/roles", [
                'name' => $name,
                'permissions' => $permissions,
            ])
            ->assertCreated()
            ->json('data.id');
    }

    public function test_a_limited_admin_cannot_assign_a_more_privileged_role(): void
    {
        [$owner, $organizationId] = $this->ownerWithOrganization();
        $organization = Organization::find($organizationId);

        // A delegated admin who can manage members but holds nothing else.
        $this->createRole($owner, $organizationId, 'MemberManager', [
            DefaultPermission::MembersView->value,
            DefaultPermission::MembersUpdate->value,
        ]);

        $manager = User::factory()->create();
        $victim = User::factory()->create();
        app(MembershipService::class)->addMember($organization, $manager, 'MemberManager');
        app(MembershipService::class)->addMember($organization, $victim, OrganizationRole::User->value);

        // Dispatcher carries permissions the manager does not hold → escalation.
        $this->actingAs($manager, 'sanctum')
            ->patchJson("/api/v1/organizations/{$organizationId}/members/{$victim->id}", [
                'role' => OrganizationRole::Dispatcher->value,
            ])
            ->assertForbidden();
    }

    public function test_role_creation_cannot_grant_permissions_the_actor_lacks(): void
    {
        [$owner, $organizationId] = $this->ownerWithOrganization();
        $organization = Organization::find($organizationId);

        $this->createRole($owner, $organizationId, 'LimitedRoleManager', [
            DefaultPermission::RolesManage->value,
            DefaultPermission::MembersView->value,
        ]);

        $rmanager = User::factory()->create();
        app(MembershipService::class)->addMember($organization, $rmanager, 'LimitedRoleManager');

        // Cannot mint a role granting a permission the actor does not hold.
        $this->actingAs($rmanager, 'sanctum')
            ->postJson("/api/v1/organizations/{$organizationId}/roles", [
                'name' => 'Sneaky',
                'permissions' => [DefaultPermission::OrganizationDelete->value],
            ])
            ->assertForbidden();

        // Can mint a role within their own privilege.
        $this->actingAs($rmanager, 'sanctum')
            ->postJson("/api/v1/organizations/{$organizationId}/roles", [
                'name' => 'Viewer',
                'permissions' => [DefaultPermission::MembersView->value],
            ])
            ->assertCreated();
    }

    public function test_reserved_role_names_are_rejected(): void
    {
        [$owner, $organizationId] = $this->ownerWithOrganization();

        foreach ([OrganizationRole::OrganizationAdmin->value, 'SuperAdmin'] as $reserved) {
            $this->actingAs($owner, 'sanctum')
                ->postJson("/api/v1/organizations/{$organizationId}/roles", ['name' => $reserved])
                ->assertJsonValidationErrors('name');
        }
    }

    public function test_default_roles_cannot_be_deleted_or_renamed(): void
    {
        [$owner, $organizationId] = $this->ownerWithOrganization();

        $roles = $this->actingAs($owner, 'sanctum')
            ->getJson("/api/v1/organizations/{$organizationId}/roles?per_page=100")
            ->json('data');
        $dispatcherId = collect($roles)->firstWhere('name', OrganizationRole::Dispatcher->value)['id'];

        $this->actingAs($owner, 'sanctum')
            ->deleteJson("/api/v1/organizations/{$organizationId}/roles/{$dispatcherId}")
            ->assertStatus(422);

        $this->actingAs($owner, 'sanctum')
            ->patchJson("/api/v1/organizations/{$organizationId}/roles/{$dispatcherId}", ['name' => 'Dispatch2'])
            ->assertStatus(422);

        // Permission tuning of a default role is still allowed.
        $this->actingAs($owner, 'sanctum')
            ->patchJson("/api/v1/organizations/{$organizationId}/roles/{$dispatcherId}", [
                'permissions' => [DefaultPermission::OrganizationView->value],
            ])
            ->assertOk();
    }

    public function test_a_role_from_another_organization_is_not_reachable(): void
    {
        [$owner1, $org1] = $this->ownerWithOrganization();

        $owner2 = User::factory()->create();
        $org2 = $this->actingAs($owner2, 'sanctum')
            ->postJson('/api/v1/organizations', ['name' => 'Other Inc'])
            ->json('data.id');
        $org2Roles = $this->actingAs($owner2, 'sanctum')
            ->getJson("/api/v1/organizations/{$org2}/roles?per_page=100")
            ->json('data');
        $foreignRoleId = collect($org2Roles)->firstWhere('name', OrganizationRole::Dispatcher->value)['id'];

        // owner1 cannot read another organization's role by id.
        $this->actingAs($owner1, 'sanctum')
            ->getJson("/api/v1/organizations/{$org1}/roles/{$foreignRoleId}")
            ->assertNotFound();
    }
}
