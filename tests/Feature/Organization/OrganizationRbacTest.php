<?php

declare(strict_types=1);

namespace Tests\Feature\Organization;

use App\Domains\Authorization\Database\Seeders\PermissionCatalogueSeeder;
use App\Domains\Authorization\Support\Enums\DefaultPermission;
use App\Domains\Authorization\Support\Enums\OrganizationRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

final class OrganizationRbacTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionCatalogueSeeder::class);
    }

    public function test_creating_an_organization_provisions_default_roles_and_owner(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/organizations', ['name' => 'Acme Inc']);

        $response->assertCreated();
        $organizationId = $response->json('data.id');

        // All default roles exist, scoped to this organization.
        foreach (OrganizationRole::values() as $role) {
            $this->assertDatabaseHas('roles', [
                'name' => $role,
                'organization_id' => $organizationId,
            ]);
        }

        // The creator is the owner and their active org is set.
        $this->assertDatabaseHas('organization_user', [
            'organization_id' => $organizationId,
            'user_id' => $user->id,
        ]);
        $this->assertSame($organizationId, $user->refresh()->current_organization_id);
    }

    public function test_owner_can_invite_a_member(): void
    {
        Queue::fake();
        $owner = User::factory()->create();

        $organizationId = $this->actingAs($owner, 'sanctum')
            ->postJson('/api/v1/organizations', ['name' => 'Acme Inc'])
            ->json('data.id');

        $this->actingAs($owner, 'sanctum')
            ->postJson("/api/v1/organizations/{$organizationId}/invitations", [
                'email' => 'new@example.com',
                'role' => OrganizationRole::User->value,
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'pending');

        $this->assertDatabaseHas('organization_invitations', [
            'organization_id' => $organizationId,
            'email' => 'new@example.com',
        ]);
    }

    public function test_non_member_cannot_access_organization_scope(): void
    {
        $owner = User::factory()->create();
        $outsider = User::factory()->create();

        $organizationId = $this->actingAs($owner, 'sanctum')
            ->postJson('/api/v1/organizations', ['name' => 'Acme Inc'])
            ->json('data.id');

        $this->actingAs($outsider, 'sanctum')
            ->getJson("/api/v1/organizations/{$organizationId}/members")
            ->assertForbidden();
    }

    public function test_member_without_permission_cannot_invite(): void
    {
        Queue::fake();
        $owner = User::factory()->create();
        $member = User::factory()->create();

        $organizationId = $this->actingAs($owner, 'sanctum')
            ->postJson('/api/v1/organizations', ['name' => 'Acme Inc'])
            ->json('data.id');

        // Add the member with the low-privilege "User" role.
        app(\App\Domains\Organization\Services\MembershipService::class)
            ->addMember(\App\Domains\Organization\Models\Organization::find($organizationId), $member, OrganizationRole::User->value);

        $this->actingAs($member, 'sanctum')
            ->postJson("/api/v1/organizations/{$organizationId}/invitations", [
                'email' => 'x@example.com',
                'role' => OrganizationRole::User->value,
            ])
            ->assertForbidden();

        $this->assertFalse($member->can(DefaultPermission::MembersInvite->value));
    }
}
