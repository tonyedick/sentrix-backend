<?php

declare(strict_types=1);

namespace Tests\Feature\Organization;

use App\Domains\Authorization\Database\Seeders\PermissionCatalogueSeeder;
use App\Domains\Authorization\Support\Enums\OrganizationRole;
use App\Domains\Organization\Models\Organization;
use App\Domains\Organization\Services\MembershipService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class OrganizationLifecycleTest extends TestCase
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
    private function ownerWithOrganization(string $name = 'Acme Inc'): array
    {
        $owner = User::factory()->create();
        $organizationId = $this->actingAs($owner, 'sanctum')
            ->postJson('/api/v1/organizations', ['name' => $name])
            ->json('data.id');

        return [$owner, $organizationId];
    }

    private function addMember(string $organizationId, User $user, string $role): void
    {
        app(MembershipService::class)->addMember(Organization::find($organizationId), $user, $role);
    }

    public function test_owner_can_transfer_ownership_to_a_member(): void
    {
        [$owner, $organizationId] = $this->ownerWithOrganization();
        $member = User::factory()->create();
        $this->addMember($organizationId, $member, OrganizationRole::User->value);

        $this->actingAs($owner, 'sanctum')
            ->postJson("/api/v1/organizations/{$organizationId}/transfer-ownership", ['user_id' => $member->id])
            ->assertOk()
            ->assertJsonPath('data.owner_id', $member->id);

        $this->assertSame($member->id, Organization::find($organizationId)->owner_id);
    }

    public function test_a_non_owner_cannot_transfer_ownership(): void
    {
        [, $organizationId] = $this->ownerWithOrganization();
        $admin = User::factory()->create();
        $target = User::factory()->create();
        $this->addMember($organizationId, $admin, OrganizationRole::OrganizationAdmin->value);
        $this->addMember($organizationId, $target, OrganizationRole::User->value);

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/organizations/{$organizationId}/transfer-ownership", ['user_id' => $target->id])
            ->assertForbidden();
    }

    public function test_ownership_cannot_be_transferred_to_a_non_member(): void
    {
        [$owner, $organizationId] = $this->ownerWithOrganization();
        $outsider = User::factory()->create();

        $this->actingAs($owner, 'sanctum')
            ->postJson("/api/v1/organizations/{$organizationId}/transfer-ownership", ['user_id' => $outsider->id])
            ->assertStatus(422);
    }

    public function test_owner_cannot_be_removed_but_a_former_owner_can(): void
    {
        [$owner, $organizationId] = $this->ownerWithOrganization();
        $member = User::factory()->create();
        $this->addMember($organizationId, $member, OrganizationRole::User->value);

        // Owner is immovable.
        $this->actingAs($owner, 'sanctum')
            ->deleteJson("/api/v1/organizations/{$organizationId}/members/{$owner->id}")
            ->assertStatus(422);

        // After transfer, the former owner is just a member and can be removed.
        $this->actingAs($owner, 'sanctum')
            ->postJson("/api/v1/organizations/{$organizationId}/transfer-ownership", ['user_id' => $member->id])
            ->assertOk();

        $this->actingAs($member, 'sanctum')
            ->deleteJson("/api/v1/organizations/{$organizationId}/members/{$owner->id}")
            ->assertNoContent();
    }

    public function test_deleting_an_organization_clears_members_active_context(): void
    {
        [$owner, $organizationId] = $this->ownerWithOrganization();
        $this->assertSame($organizationId, $owner->refresh()->current_organization_id);

        $this->actingAs($owner, 'sanctum')
            ->deleteJson("/api/v1/organizations/{$organizationId}")
            ->assertNoContent();

        $this->assertNull($owner->refresh()->current_organization_id);
        $this->assertSoftDeleted('organizations', ['id' => $organizationId]);
    }

    public function test_cannot_switch_into_a_soft_deleted_organization(): void
    {
        [$owner] = $this->ownerWithOrganization('First Inc');
        $secondId = $this->actingAs($owner, 'sanctum')
            ->postJson('/api/v1/organizations', ['name' => 'Second Inc'])
            ->json('data.id');

        $this->actingAs($owner, 'sanctum')
            ->deleteJson("/api/v1/organizations/{$secondId}")
            ->assertNoContent();

        $this->actingAs($owner, 'sanctum')
            ->postJson("/api/v1/organizations/{$secondId}/switch")
            ->assertNotFound();
    }

    public function test_slugs_are_deduplicated(): void
    {
        $owner = User::factory()->create();

        $first = $this->actingAs($owner, 'sanctum')
            ->postJson('/api/v1/organizations', ['name' => 'Acme'])
            ->json('data.slug');
        $second = $this->actingAs($owner, 'sanctum')
            ->postJson('/api/v1/organizations', ['name' => 'Acme'])
            ->json('data.slug');

        $this->assertNotSame($first, $second);
    }
}
