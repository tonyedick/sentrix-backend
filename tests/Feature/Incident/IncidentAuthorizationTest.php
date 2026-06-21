<?php

declare(strict_types=1);

namespace Tests\Feature\Incident;

use App\Domains\Authorization\Database\Seeders\PermissionCatalogueSeeder;
use App\Domains\Authorization\Support\Enums\OrganizationRole;
use App\Domains\Organization\Models\Organization;
use App\Domains\Organization\Services\MembershipService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Guards the create/update permission gates on IncidentController. These two
 * write actions previously had no permission check (unlike every transition
 * action), so any org member could open or edit incidents.
 */
final class IncidentAuthorizationTest extends TestCase
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

    public function test_member_without_create_permission_cannot_open_incident(): void
    {
        [, $organizationId, $organization] = $this->ownerWithOrganization();

        // Responder can view + update incidents but is NOT granted incidents.create.
        $responder = $this->member($organization, OrganizationRole::Responder);

        $this->actingAs($responder, 'sanctum')
            ->postJson("/api/v1/organizations/{$organizationId}/incidents", [
                'title' => 'Unauthorized open attempt',
                'severity' => 'high',
            ])
            ->assertForbidden();

        $this->assertDatabaseCount('incidents', 0);
    }

    public function test_member_with_create_permission_can_open_incident(): void
    {
        [, $organizationId, $organization] = $this->ownerWithOrganization();

        // Dispatcher holds incidents.create.
        $dispatcher = $this->member($organization, OrganizationRole::Dispatcher);

        $this->actingAs($dispatcher, 'sanctum')
            ->postJson("/api/v1/organizations/{$organizationId}/incidents", [
                'title' => 'Authorized open',
                'severity' => 'medium',
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'open');
    }

    public function test_member_without_update_permission_cannot_update_incident(): void
    {
        [$owner, $organizationId, $organization] = $this->ownerWithOrganization();

        $incidentId = $this->actingAs($owner, 'sanctum')
            ->postJson("/api/v1/organizations/{$organizationId}/incidents", ['title' => 'Original'])
            ->assertCreated()
            ->json('data.id');

        // The User role can run trips/emergencies but holds no incidents.update.
        $fieldUser = $this->member($organization, OrganizationRole::User);

        $this->actingAs($fieldUser, 'sanctum')
            ->patchJson("/api/v1/organizations/{$organizationId}/incidents/{$incidentId}", [
                'title' => 'Tampered title',
            ])
            ->assertForbidden();

        $this->assertDatabaseHas('incidents', ['id' => $incidentId, 'title' => 'Original']);
    }

    public function test_member_with_update_permission_can_update_incident(): void
    {
        [$owner, $organizationId, $organization] = $this->ownerWithOrganization();

        $incidentId = $this->actingAs($owner, 'sanctum')
            ->postJson("/api/v1/organizations/{$organizationId}/incidents", ['title' => 'Original'])
            ->assertCreated()
            ->json('data.id');

        // Responder holds incidents.update.
        $responder = $this->member($organization, OrganizationRole::Responder);

        $this->actingAs($responder, 'sanctum')
            ->patchJson("/api/v1/organizations/{$organizationId}/incidents/{$incidentId}", [
                'title' => 'Updated title',
            ])
            ->assertOk()
            ->assertJsonPath('data.title', 'Updated title');
    }
}
