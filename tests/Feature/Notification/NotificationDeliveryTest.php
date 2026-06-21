<?php

declare(strict_types=1);

namespace Tests\Feature\Notification;

use App\Domains\Assignment\Notifications\AssignmentOfferedNotification;
use App\Domains\Authorization\Database\Seeders\PermissionCatalogueSeeder;
use App\Domains\Authorization\Support\Enums\OrganizationRole;
use App\Domains\Notification\Contracts\PushProvider;
use App\Domains\Notification\Contracts\SmsProvider;
use App\Domains\Notification\Channels\SmsChannel;
use App\Domains\Notification\Models\NotificationDelivery;
use App\Domains\Notification\Models\NotificationPolicy;
use App\Domains\Notification\Providers\Push\LogPushProvider;
use App\Domains\Notification\Providers\Sms\LogSmsProvider;
use App\Domains\Organization\Models\Organization;
use App\Domains\Organization\Services\MembershipService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\Events\NotificationFailed;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

final class NotificationDeliveryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionCatalogueSeeder::class);
        config(['sentrix.responders.assignment_acceptance_timeout_seconds' => 0]);
    }

    /** @return array{0: User, 1: string} */
    private function org(): array
    {
        $owner = User::factory()->create();
        $orgId = $this->actingAs($owner, 'sanctum')
            ->postJson('/api/v1/organizations', ['name' => 'Acme Inc'])->json('data.id');

        return [$owner, $orgId];
    }

    private function policy(string $orgId, array $channels): void
    {
        NotificationPolicy::create(['organization_id' => $orgId, 'channels' => $channels]);
    }

    // ---- Provider abstraction ------------------------------------------------

    public function test_default_providers_resolve_to_log_drivers(): void
    {
        $this->assertInstanceOf(LogSmsProvider::class, app(SmsProvider::class));
        $this->assertInstanceOf(LogPushProvider::class, app(PushProvider::class));
        $this->assertSame('log', app(SmsProvider::class)->name());
    }

    // ---- Delivery recording across channels ----------------------------------

    public function test_delivery_is_recorded_for_every_enabled_channel(): void
    {
        [, $orgId] = $this->org();
        $this->policy($orgId, ['database', 'mail', 'sms', 'push']);

        $user = User::factory()->create(['phone' => '+15551230000', 'push_tokens' => ['tok-1']]);

        Notification::send($user, new AssignmentOfferedNotification('line-1', 'primary', 'inc-1', $orgId));

        foreach (['database', 'mail', 'sms', 'push'] as $channel) {
            $this->assertDatabaseHas('notification_deliveries', [
                'channel' => $channel,
                'status' => NotificationDelivery::STATUS_SENT,
                'organization_id' => $orgId,
                'notifiable_id' => $user->getKey(),
            ]);
        }

        // Every recorded attempt counts at least once.
        $this->assertGreaterThanOrEqual(1, NotificationDelivery::where('channel', 'sms')->first()->attempts);
    }

    public function test_organization_policy_limits_channels(): void
    {
        [, $orgId] = $this->org();
        $this->policy($orgId, ['database']); // in-app only

        $user = User::factory()->create(['phone' => '+15551230000', 'push_tokens' => ['tok-1']]);

        Notification::send($user, new AssignmentOfferedNotification('line-1', 'primary', 'inc-1', $orgId));

        $this->assertDatabaseHas('notification_deliveries', ['channel' => 'database', 'notifiable_id' => $user->getKey()]);
        $this->assertDatabaseMissing('notification_deliveries', ['channel' => 'sms', 'notifiable_id' => $user->getKey()]);
        $this->assertDatabaseMissing('notification_deliveries', ['channel' => 'mail', 'notifiable_id' => $user->getKey()]);
    }

    public function test_failed_delivery_is_recorded_with_error(): void
    {
        [, $orgId] = $this->org();
        $user = User::factory()->create();

        $notification = new AssignmentOfferedNotification('line-1', 'primary', 'inc-1', $orgId);
        $notification->id = (string) Str::uuid();

        event(new NotificationFailed($user, $notification, SmsChannel::class, ['exception' => new RuntimeException('gateway down')]));

        $this->assertDatabaseHas('notification_deliveries', [
            'notification_id' => $notification->id,
            'channel' => 'sms',
            'status' => NotificationDelivery::STATUS_FAILED,
        ]);
        $this->assertStringContainsString('gateway down', (string) NotificationDelivery::where('channel', 'sms')->first()->error);
    }

    // ---- End-to-end: Assignment event → external channels --------------------

    public function test_assignment_offer_delivers_to_external_channels(): void
    {
        [$owner, $orgId] = $this->org();
        $this->policy($orgId, ['database', 'sms', 'push']);
        $org = Organization::findOrFail($orgId);

        $bob = User::factory()->create(['phone' => '+15559990000', 'push_tokens' => ['device-xyz']]);
        app(MembershipService::class)->addMember($org, $bob, OrganizationRole::Responder->value);
        $responderId = $this->actingAs($owner, 'sanctum')
            ->postJson("/api/v1/organizations/{$orgId}/responders", ['user_id' => $bob->getKey()])->json('data.id');
        $this->actingAs($bob, 'sanctum')
            ->postJson("/api/v1/organizations/{$orgId}/responders/{$responderId}/status", ['status' => 'available'])->assertOk();

        $incidentId = $this->actingAs($owner, 'sanctum')
            ->postJson("/api/v1/organizations/{$orgId}/incidents", ['title' => 'Fire'])->json('data.id');
        $assignmentId = $this->actingAs($owner, 'sanctum')
            ->postJson("/api/v1/organizations/{$orgId}/assignments", ['incident_id' => $incidentId])->json('data.id');

        // Offering the responder triggers ResponderOffered → NotifyResponderOfAssignment.
        $this->actingAs($owner, 'sanctum')
            ->postJson("/api/v1/organizations/{$orgId}/assignments/{$assignmentId}/responders", ['responder_id' => $responderId, 'role' => 'primary'])
            ->assertCreated();

        $this->assertDatabaseHas('notification_deliveries', [
            'channel' => 'sms', 'status' => NotificationDelivery::STATUS_SENT, 'notifiable_id' => $bob->getKey(),
        ]);
        $this->assertDatabaseHas('notification_deliveries', [
            'channel' => 'push', 'status' => NotificationDelivery::STATUS_SENT, 'notifiable_id' => $bob->getKey(),
        ]);
    }
}
