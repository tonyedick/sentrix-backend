<?php

declare(strict_types=1);

namespace Tests\Feature\Responder;

use App\Domains\Authorization\Database\Seeders\PermissionCatalogueSeeder;
use App\Domains\Authorization\Support\Enums\OrganizationRole;
use App\Domains\Organization\Models\Organization;
use App\Domains\Organization\Services\MembershipService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ResponderProfileTest extends TestCase
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

    private function member(Organization $organization, OrganizationRole $role): User
    {
        $user = User::factory()->create();
        app(MembershipService::class)->addMember($organization, $user, $role->value);

        return $user;
    }

    private function registerResponder(User $actor, string $organizationId, User $user): string
    {
        return $this->actingAs($actor, 'sanctum')
            ->postJson("/api/v1/organizations/{$organizationId}/responders", ['user_id' => $user->getKey()])
            ->assertCreated()
            ->assertJsonPath('data.status', 'off_duty')
            ->json('data.id');
    }

    public function test_admin_can_register_a_responder(): void
    {
        [$owner, $organizationId, $organization] = $this->ownerWithOrganization();
        $bob = $this->member($organization, OrganizationRole::Responder);

        $responderId = $this->registerResponder($owner, $organizationId, $bob);

        $this->assertDatabaseHas('responders', [
            'id' => $responderId,
            'organization_id' => $organizationId,
            'user_id' => $bob->getKey(),
            'status' => 'off_duty',
            'on_duty' => false,
        ]);
    }

    public function test_registering_a_non_member_is_rejected(): void
    {
        [$owner, $organizationId] = $this->ownerWithOrganization();
        $stranger = User::factory()->create();

        $this->actingAs($owner, 'sanctum')
            ->postJson("/api/v1/organizations/{$organizationId}/responders", ['user_id' => $stranger->getKey()])
            ->assertStatus(422);
    }

    public function test_member_without_manage_permission_cannot_register_responder(): void
    {
        [, $organizationId, $organization] = $this->ownerWithOrganization();
        $fieldUser = $this->member($organization, OrganizationRole::User);
        $bob = $this->member($organization, OrganizationRole::Responder);

        $this->actingAs($fieldUser, 'sanctum')
            ->postJson("/api/v1/organizations/{$organizationId}/responders", ['user_id' => $bob->getKey()])
            ->assertForbidden();
    }

    public function test_responder_can_self_serve_on_and_off_duty(): void
    {
        [$owner, $organizationId, $organization] = $this->ownerWithOrganization();
        $bob = $this->member($organization, OrganizationRole::Responder);
        $responderId = $this->registerResponder($owner, $organizationId, $bob);

        $this->actingAs($bob, 'sanctum')
            ->postJson("/api/v1/organizations/{$organizationId}/responders/{$responderId}/status", ['status' => 'available'])
            ->assertOk()
            ->assertJsonPath('data.status', 'available')
            ->assertJsonPath('data.on_duty', true)
            ->assertJsonPath('data.assignable', true);

        $this->actingAs($bob, 'sanctum')
            ->postJson("/api/v1/organizations/{$organizationId}/responders/{$responderId}/status", ['status' => 'off_duty'])
            ->assertOk()
            ->assertJsonPath('data.on_duty', false);
    }

    public function test_illegal_status_transition_is_rejected(): void
    {
        [$owner, $organizationId, $organization] = $this->ownerWithOrganization();
        $bob = $this->member($organization, OrganizationRole::Responder);
        $responderId = $this->registerResponder($owner, $organizationId, $bob);

        // off_duty cannot jump straight to engaged.
        $this->actingAs($bob, 'sanctum')
            ->postJson("/api/v1/organizations/{$organizationId}/responders/{$responderId}/status", ['status' => 'engaged'])
            ->assertStatus(422);
    }

    public function test_responder_cannot_suspend_themselves(): void
    {
        [$owner, $organizationId, $organization] = $this->ownerWithOrganization();
        $bob = $this->member($organization, OrganizationRole::Responder);
        $responderId = $this->registerResponder($owner, $organizationId, $bob);

        // Suspension requires responders.manage, which the Responder role lacks.
        $this->actingAs($bob, 'sanctum')
            ->postJson("/api/v1/organizations/{$organizationId}/responders/{$responderId}/status", ['status' => 'suspended'])
            ->assertForbidden();

        // An admin can.
        $this->actingAs($owner, 'sanctum')
            ->postJson("/api/v1/organizations/{$organizationId}/responders/{$responderId}/status", ['status' => 'suspended'])
            ->assertOk()
            ->assertJsonPath('data.status', 'suspended');
    }
}
