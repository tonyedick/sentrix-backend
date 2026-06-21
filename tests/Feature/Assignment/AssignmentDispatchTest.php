<?php

declare(strict_types=1);

namespace Tests\Feature\Assignment;

use App\Domains\Assignment\Jobs\ExpireAssignmentOffer;
use App\Domains\Assignment\Models\Assignment;
use App\Domains\Authorization\Database\Seeders\PermissionCatalogueSeeder;
use App\Domains\Authorization\Support\Enums\OrganizationRole;
use App\Domains\Organization\Models\Organization;
use App\Domains\Organization\Services\MembershipService;
use App\Domains\Responder\Models\Responder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

final class AssignmentDispatchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionCatalogueSeeder::class);
        // Stop the delayed acceptance-timeout job running synchronously in tests.
        Queue::fake();
    }

    private function availableResponder(Organization $organization, string $orgId, User $owner): array
    {
        $user = User::factory()->create();
        app(MembershipService::class)->addMember($organization, $user, OrganizationRole::Responder->value);
        $responderId = $this->actingAs($owner, 'sanctum')
            ->postJson("/api/v1/organizations/{$orgId}/responders", ['user_id' => $user->getKey()])
            ->json('data.id');
        $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/organizations/{$orgId}/responders/{$responderId}/status", ['status' => 'available'])
            ->assertOk();

        return [$user, $responderId];
    }

    /**
     * @return array{owner: User, orgId: string, org: Organization, incidentId: string}
     */
    private function scenario(): array
    {
        $owner = User::factory()->create();
        $orgId = $this->actingAs($owner, 'sanctum')
            ->postJson('/api/v1/organizations', ['name' => 'Acme Inc'])
            ->json('data.id');
        $org = Organization::findOrFail($orgId);

        $incidentId = $this->actingAs($owner, 'sanctum')
            ->postJson("/api/v1/organizations/{$orgId}/incidents", ['title' => 'Structure fire', 'severity' => 'high'])
            ->json('data.id');

        return ['owner' => $owner, 'orgId' => $orgId, 'org' => $org, 'incidentId' => $incidentId];
    }

    public function test_full_primary_dispatch_lifecycle(): void
    {
        ['owner' => $owner, 'orgId' => $orgId, 'org' => $org, 'incidentId' => $incidentId] = $this->scenario();
        [$bob, $responderId] = $this->availableResponder($org, $orgId, $owner);

        $assignmentId = $this->actingAs($owner, 'sanctum')
            ->postJson("/api/v1/organizations/{$orgId}/assignments", ['incident_id' => $incidentId])
            ->assertCreated()
            ->assertJsonPath('data.status', 'pending')
            ->json('data.id');

        $lineId = $this->actingAs($owner, 'sanctum')
            ->postJson("/api/v1/organizations/{$orgId}/assignments/{$assignmentId}/responders", [
                'responder_id' => $responderId,
                'role' => 'primary',
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'offered')
            ->assertJsonPath('data.role', 'primary')
            ->json('data.id');

        Queue::assertPushed(ExpireAssignmentOffer::class);

        $this->actingAs($bob, 'sanctum')
            ->postJson("/api/v1/organizations/{$orgId}/assignments/{$assignmentId}/responders/{$lineId}/accept")
            ->assertOk()
            ->assertJsonPath('data.status', 'accepted');

        $responder = Responder::findOrFail($responderId);
        $this->assertSame('engaged', $responder->status->value);
        $this->assertSame($lineId, $responder->current_assignment_id);
        $this->assertDatabaseHas('incidents', ['id' => $incidentId, 'assigned_to' => $bob->getKey()]);
        $this->assertSame('filled', Assignment::findOrFail($assignmentId)->status->value);

        $this->actingAs($bob, 'sanctum')->postJson("/api/v1/organizations/{$orgId}/assignments/{$assignmentId}/responders/{$lineId}/en-route")->assertOk();
        $this->assertSame('active', Assignment::findOrFail($assignmentId)->status->value);
        $this->actingAs($bob, 'sanctum')->postJson("/api/v1/organizations/{$orgId}/assignments/{$assignmentId}/responders/{$lineId}/on-scene")->assertOk();

        $this->actingAs($owner, 'sanctum')
            ->postJson("/api/v1/organizations/{$orgId}/assignments/{$assignmentId}/complete")
            ->assertOk()
            ->assertJsonPath('data.status', 'completed');

        $responder->refresh();
        $this->assertSame('available', $responder->status->value);
        $this->assertNull($responder->current_assignment_id);
        $this->assertDatabaseHas('incidents', ['id' => $incidentId, 'assigned_to' => null]);
    }

    public function test_primary_and_supporting_fill_then_double_active_is_rejected(): void
    {
        ['owner' => $owner, 'orgId' => $orgId, 'org' => $org, 'incidentId' => $incidentId] = $this->scenario();
        [$bob, $bobResponderId] = $this->availableResponder($org, $orgId, $owner);
        [$carol, $carolResponderId] = $this->availableResponder($org, $orgId, $owner);

        $assignmentId = $this->actingAs($owner, 'sanctum')
            ->postJson("/api/v1/organizations/{$orgId}/assignments", ['incident_id' => $incidentId, 'required_supporting' => 1])
            ->json('data.id');

        $primaryLine = $this->actingAs($owner, 'sanctum')
            ->postJson("/api/v1/organizations/{$orgId}/assignments/{$assignmentId}/responders", ['responder_id' => $bobResponderId, 'role' => 'primary'])
            ->json('data.id');
        $this->actingAs($bob, 'sanctum')->postJson("/api/v1/organizations/{$orgId}/assignments/{$assignmentId}/responders/{$primaryLine}/accept")->assertOk();

        // Only primary accepted; required_supporting=1 not yet met.
        $this->assertSame('partially_filled', Assignment::findOrFail($assignmentId)->status->value);

        $supportLine = $this->actingAs($owner, 'sanctum')
            ->postJson("/api/v1/organizations/{$orgId}/assignments/{$assignmentId}/responders", ['responder_id' => $carolResponderId, 'role' => 'supporting'])
            ->json('data.id');
        $this->actingAs($carol, 'sanctum')->postJson("/api/v1/organizations/{$orgId}/assignments/{$assignmentId}/responders/{$supportLine}/accept")->assertOk();

        $this->assertSame('filled', Assignment::findOrFail($assignmentId)->status->value);

        // Bob is engaged → a second offer to him is rejected.
        $this->actingAs($owner, 'sanctum')
            ->postJson("/api/v1/organizations/{$orgId}/assignments/{$assignmentId}/responders", ['responder_id' => $bobResponderId, 'role' => 'supporting'])
            ->assertStatus(422);
    }

    public function test_decline_returns_assignment_to_pending(): void
    {
        ['owner' => $owner, 'orgId' => $orgId, 'org' => $org, 'incidentId' => $incidentId] = $this->scenario();
        [$bob, $responderId] = $this->availableResponder($org, $orgId, $owner);

        $assignmentId = $this->actingAs($owner, 'sanctum')
            ->postJson("/api/v1/organizations/{$orgId}/assignments", ['incident_id' => $incidentId])
            ->json('data.id');
        $lineId = $this->actingAs($owner, 'sanctum')
            ->postJson("/api/v1/organizations/{$orgId}/assignments/{$assignmentId}/responders", ['responder_id' => $responderId, 'role' => 'primary'])
            ->json('data.id');

        $this->actingAs($bob, 'sanctum')
            ->postJson("/api/v1/organizations/{$orgId}/assignments/{$assignmentId}/responders/{$lineId}/decline", ['reason' => 'unavailable'])
            ->assertOk()
            ->assertJsonPath('data.status', 'declined');

        $this->assertSame('pending', Assignment::findOrFail($assignmentId)->status->value);
    }

    public function test_member_without_dispatch_permission_cannot_open_or_offer(): void
    {
        ['orgId' => $orgId, 'org' => $org, 'incidentId' => $incidentId] = $this->scenario();

        $fieldUser = User::factory()->create();
        app(MembershipService::class)->addMember($org, $fieldUser, OrganizationRole::User->value);

        $this->actingAs($fieldUser, 'sanctum')
            ->postJson("/api/v1/organizations/{$orgId}/assignments", ['incident_id' => $incidentId])
            ->assertForbidden();
    }
}
