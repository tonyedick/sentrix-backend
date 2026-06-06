<?php

declare(strict_types=1);

namespace Tests\Feature\Notification;

use App\Domains\Authorization\Database\Seeders\PermissionCatalogueSeeder;
use App\Domains\Authorization\Support\Enums\OrganizationRole;
use App\Domains\Organization\Models\Organization;
use App\Domains\Organization\Services\MembershipService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class NotificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionCatalogueSeeder::class);
    }

    /**
     * An admin owner plus a field user who triggers an emergency. Returns the
     * owner (a responder) and the field user (the subject).
     *
     * @return array{0: User, 1: User, 2: string}
     */
    private function emergencyRaisedByAFieldUser(): array
    {
        $owner = User::factory()->create();
        $organizationId = $this->actingAs($owner, 'sanctum')
            ->postJson('/api/v1/organizations', ['name' => 'Acme Inc'])
            ->json('data.id');

        $traveler = User::factory()->create();
        app(MembershipService::class)->addMember(
            Organization::find($organizationId),
            $traveler,
            OrganizationRole::User->value,
        );

        $this->actingAs($traveler, 'sanctum')
            ->postJson("/api/v1/organizations/{$organizationId}/emergencies", ['severity' => 'high'])
            ->assertCreated();

        return [$owner, $traveler, $organizationId];
    }

    public function test_responders_are_notified_and_the_subject_is_excluded(): void
    {
        [$owner, $traveler] = $this->emergencyRaisedByAFieldUser();

        // The owner (can acknowledge) is notified; the field user who raised it
        // (and who cannot acknowledge) is not.
        $this->assertSame(1, $owner->fresh()->notifications()->count());
        $this->assertSame(0, $traveler->fresh()->notifications()->count());
    }

    public function test_a_user_can_list_count_and_read_their_notifications(): void
    {
        [$owner] = $this->emergencyRaisedByAFieldUser();

        $id = $this->actingAs($owner, 'sanctum')
            ->getJson('/api/v1/notifications')
            ->assertOk()
            ->assertJsonPath('data.0.type', 'emergency.triggered')
            ->json('data.0.id');

        $this->actingAs($owner, 'sanctum')
            ->getJson('/api/v1/notifications/unread-count')
            ->assertOk()
            ->assertJsonPath('data.count', 1);

        $this->actingAs($owner, 'sanctum')
            ->postJson("/api/v1/notifications/{$id}/read")
            ->assertOk()
            ->assertJsonPath('data.read', true);

        $this->actingAs($owner, 'sanctum')
            ->getJson('/api/v1/notifications/unread-count')
            ->assertJsonPath('data.count', 0);
    }

    public function test_a_user_cannot_touch_another_users_notification(): void
    {
        [$owner] = $this->emergencyRaisedByAFieldUser();
        $outsider = User::factory()->create();

        $id = $owner->fresh()->notifications()->firstOrFail()->id;

        $this->actingAs($outsider, 'sanctum')
            ->postJson("/api/v1/notifications/{$id}/read")
            ->assertNotFound();
    }
}
