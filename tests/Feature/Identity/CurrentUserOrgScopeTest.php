<?php

declare(strict_types=1);

namespace Tests\Feature\Identity;

use App\Domains\Authorization\Database\Seeders\PermissionCatalogueSeeder;
use App\Domains\Authorization\Support\Enums\OrganizationRole;
use App\Domains\Organization\Models\Organization;
use App\Domains\Organization\Services\MembershipService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * GET /auth/me is org-team-scoped (via the `organization.team` middleware), so a
 * member's roles/permissions are resolved for the active organization rather than
 * the empty global scope.
 */
final class CurrentUserOrgScopeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionCatalogueSeeder::class);
    }

    /**
     * @return array{0: User, 1: string, 2: Organization}
     */
    private function ownerWithOrganization(): array
    {
        $owner = User::factory()->create();
        $organizationId = $this->actingAs($owner, 'sanctum')
            ->postJson('/api/v1/organizations', ['name' => 'Acme Inc'])
            ->json('data.id');

        return [$owner, $organizationId, Organization::findOrFail($organizationId)];
    }

    public function test_me_returns_org_scoped_roles_and_permissions_for_the_active_org(): void
    {
        [$owner, $organizationId] = $this->ownerWithOrganization();

        $response = $this->actingAs($owner, 'sanctum')
            ->withHeader('X-Organization', $organizationId)
            ->getJson('/api/v1/auth/me?with_permissions=1')
            ->assertOk()
            ->assertJsonPath('data.roles_organization_id', $organizationId);

        $this->assertContains('OrganizationAdmin', $response->json('data.roles'));
        $this->assertContains('incidents.escalate', $response->json('data.permissions'));
    }

    public function test_me_reflects_the_members_role_permissions_not_more(): void
    {
        [, $organizationId, $organization] = $this->ownerWithOrganization();

        $responder = User::factory()->create();
        app(MembershipService::class)->addMember($organization, $responder, OrganizationRole::Responder->value);

        $permissions = $this->actingAs($responder, 'sanctum')
            ->withHeader('X-Organization', $organizationId)
            ->getJson('/api/v1/auth/me?with_permissions=1')
            ->assertOk()
            ->json('data.permissions');

        // Responder can view incidents…
        $this->assertContains('incidents.view', $permissions);
        // …but not escalate them.
        $this->assertNotContains('incidents.escalate', $permissions);
    }
}
