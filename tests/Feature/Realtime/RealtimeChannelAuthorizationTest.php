<?php

declare(strict_types=1);

namespace Tests\Feature\Realtime;

use App\Domains\Authorization\Database\Seeders\PermissionCatalogueSeeder;
use App\Domains\Authorization\Services\RoleService;
use App\Domains\Authorization\Support\Enums\OrganizationRole;
use App\Domains\Organization\Models\Organization;
use App\Domains\Organization\Services\MembershipService;
use App\Domains\Realtime\Support\RealtimeChannelPolicy;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Demonstrates that realtime channel authorization (the logic every callback in
 * routes/channels.php delegates to) prevents cross-organization leakage and
 * unauthorized subscriptions, while honouring role scope and SuperAdmin.
 */
final class RealtimeChannelAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private RealtimeChannelPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionCatalogueSeeder::class);
        $this->policy = app(RealtimeChannelPolicy::class);
    }

    private function org(string $name): array
    {
        $owner = User::factory()->create();
        $orgId = $this->actingAs($owner, 'sanctum')
            ->postJson('/api/v1/organizations', ['name' => $name])
            ->json('data.id');

        return [$owner, $orgId, Organization::findOrFail($orgId)];
    }

    private function member(Organization $org, OrganizationRole $role): User
    {
        $user = User::factory()->create();
        app(MembershipService::class)->addMember($org, $user, $role->value);

        return $user;
    }

    public function test_role_scope_within_an_organization(): void
    {
        [$ownerA, $orgA, $organizationA] = $this->org('Acme');
        $dispatcher = $this->member($organizationA, OrganizationRole::Dispatcher);
        $responderUser = $this->member($organizationA, OrganizationRole::Responder);
        $fieldUser = $this->member($organizationA, OrganizationRole::User);

        // Dashboard + assignments require assignments.view (admin/dispatcher), not responders/users.
        $this->assertTrue($this->policy->dashboard($ownerA, $orgA));
        $this->assertTrue($this->policy->dashboard($dispatcher, $orgA));
        $this->assertFalse($this->policy->dashboard($responderUser, $orgA));
        $this->assertFalse($this->policy->dashboard($fieldUser, $orgA));

        $this->assertTrue($this->policy->assignments($dispatcher, $orgA));
        $this->assertFalse($this->policy->assignments($responderUser, $orgA));

        // Incident monitoring requires incidents.view (responders have it; field users don't).
        $this->assertTrue($this->policy->incidents($responderUser, $orgA));
        $this->assertFalse($this->policy->incidents($fieldUser, $orgA));
    }

    public function test_no_cross_organization_access(): void
    {
        [, $orgA, $organizationA] = $this->org('Acme');
        [$ownerB, $orgB] = $this->org('Beta');

        // Org B's owner (full admin in B) has NO access to any of Org A's channels.
        $this->assertFalse($this->policy->dashboard($ownerB, $orgA));
        $this->assertFalse($this->policy->incidents($ownerB, $orgA));
        $this->assertFalse($this->policy->assignments($ownerB, $orgA));
        $this->assertFalse($this->policy->responderPresence($ownerB, $orgA));

        // And an Org A dispatcher cannot reach Org B.
        $dispatcherA = $this->member($organizationA, OrganizationRole::Dispatcher);
        $this->assertFalse($this->policy->dashboard($dispatcherA, $orgB));
    }

    public function test_presence_channel_distinguishes_responders_from_observers(): void
    {
        [$ownerA, $orgA, $organizationA] = $this->org('Acme');
        $dispatcher = $this->member($organizationA, OrganizationRole::Dispatcher);
        $fieldUser = $this->member($organizationA, OrganizationRole::User);

        // An on-duty responder joins as `responder`.
        $bob = $this->member($organizationA, OrganizationRole::Responder);
        $responderId = $this->actingAs($ownerA, 'sanctum')
            ->postJson("/api/v1/organizations/{$orgA}/responders", ['user_id' => $bob->getKey()])
            ->json('data.id');
        $this->actingAs($bob, 'sanctum')
            ->postJson("/api/v1/organizations/{$orgA}/responders/{$responderId}/status", ['status' => 'available'])
            ->assertOk();

        $bobPresence = $this->policy->responderPresence($bob, $orgA);
        $this->assertIsArray($bobPresence);
        $this->assertSame('responder', $bobPresence['type']);
        $this->assertSame('available', $bobPresence['status']);

        // A dispatcher (responders.view) joins as `observer`.
        $observer = $this->policy->responderPresence($dispatcher, $orgA);
        $this->assertIsArray($observer);
        $this->assertSame('observer', $observer['type']);

        // A field user (no responders.view, not a responder) is denied.
        $this->assertFalse($this->policy->responderPresence($fieldUser, $orgA));
    }

    public function test_super_admin_bypasses_membership(): void
    {
        [, $orgA] = $this->org('Acme');

        $superAdmin = User::factory()->create();
        app(RoleService::class)->assignSuperAdmin($superAdmin);

        $this->assertTrue($this->policy->dashboard($superAdmin, $orgA));
        $this->assertTrue($this->policy->incidents($superAdmin, $orgA));
        $this->assertTrue($this->policy->assignments($superAdmin, $orgA));
        $this->assertIsArray($this->policy->responderPresence($superAdmin, $orgA)); // observer
    }

    public function test_multi_organization_user_is_scoped_per_organization(): void
    {
        [, $orgA, $organizationA] = $this->org('Acme');
        [, $orgB, $organizationB] = $this->org('Beta');

        // Same user: dispatcher in A, plain user in B.
        $user = User::factory()->create();
        app(MembershipService::class)->addMember($organizationA, $user, OrganizationRole::Dispatcher->value);
        app(MembershipService::class)->addMember($organizationB, $user, OrganizationRole::User->value);

        // Authorized for A's dashboard, denied for B's — scope is evaluated per org.
        $this->assertTrue($this->policy->assignments($user, $orgA));
        $this->assertFalse($this->policy->assignments($user, $orgB));
    }
}
