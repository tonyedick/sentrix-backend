<?php

declare(strict_types=1);

namespace Tests\Feature\Webhooks;

use App\Domains\Authorization\Database\Seeders\PermissionCatalogueSeeder;
use App\Domains\Webhooks\Jobs\DeliverWebhook;
use App\Domains\Webhooks\Models\Webhook;
use App\Domains\Webhooks\Models\WebhookDelivery;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class WebhookDeliveryTest extends TestCase
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

    public function test_owner_can_register_a_webhook_and_secret_and_events_are_stored(): void
    {
        [$owner, $organizationId] = $this->ownerWithOrganization();

        $response = $this->actingAs($owner, 'sanctum')
            ->postJson("/api/v1/organizations/{$organizationId}/webhooks", [
                'url' => 'https://partner.example.com/hooks/sentrix',
                'events' => ['incident.opened', 'emergency.triggered'],
                'description' => 'Partner integration',
            ])
            ->assertCreated()
            ->assertJsonPath('data.active', true)
            ->assertJsonPath('data.events', ['incident.opened', 'emergency.triggered']);

        $secret = $response->json('data.secret');
        $this->assertIsString($secret);
        $this->assertSame(40, strlen((string) $secret));

        $this->assertDatabaseHas('webhooks', [
            'organization_id' => $organizationId,
            'url' => 'https://partner.example.com/hooks/sentrix',
        ]);
    }

    public function test_owner_can_list_show_and_delete_a_webhook(): void
    {
        [$owner, $organizationId] = $this->ownerWithOrganization();

        $webhookId = $this->actingAs($owner, 'sanctum')
            ->postJson("/api/v1/organizations/{$organizationId}/webhooks", [
                'url' => 'https://partner.example.com/hooks',
                'events' => ['incident.opened'],
            ])
            ->json('data.id');

        $this->actingAs($owner, 'sanctum')
            ->getJson("/api/v1/organizations/{$organizationId}/webhooks")
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->actingAs($owner, 'sanctum')
            ->getJson("/api/v1/organizations/{$organizationId}/webhooks/{$webhookId}")
            ->assertOk()
            ->assertJsonPath('data.id', $webhookId);

        $this->actingAs($owner, 'sanctum')
            ->deleteJson("/api/v1/organizations/{$organizationId}/webhooks/{$webhookId}")
            ->assertOk();

        $this->assertDatabaseMissing('webhooks', ['id' => $webhookId]);
    }

    public function test_a_cross_org_webhook_is_not_found(): void
    {
        [$owner, $organizationId] = $this->ownerWithOrganization();
        [$otherOwner, $otherOrgId] = $this->ownerWithOrganization();

        $foreignWebhookId = $this->actingAs($otherOwner, 'sanctum')
            ->postJson("/api/v1/organizations/{$otherOrgId}/webhooks", [
                'url' => 'https://other.example.com/hooks',
                'events' => ['incident.opened'],
            ])
            ->json('data.id');

        $this->actingAs($owner, 'sanctum')
            ->getJson("/api/v1/organizations/{$organizationId}/webhooks/{$foreignWebhookId}")
            ->assertNotFound();
    }

    public function test_a_non_member_is_forbidden_on_the_registry(): void
    {
        [, $organizationId] = $this->ownerWithOrganization();
        $outsider = User::factory()->create();

        $this->actingAs($outsider, 'sanctum')
            ->getJson("/api/v1/organizations/{$organizationId}/webhooks")
            ->assertForbidden();
    }

    public function test_opening_an_incident_dispatches_delivery_to_a_matching_active_webhook(): void
    {
        [$owner, $organizationId] = $this->ownerWithOrganization();

        $matchingId = $this->actingAs($owner, 'sanctum')
            ->postJson("/api/v1/organizations/{$organizationId}/webhooks", [
                'url' => 'https://partner.example.com/match',
                'events' => ['incident.opened'],
            ])
            ->json('data.id');

        // Non-matching event subscription: should NOT be delivered.
        $this->actingAs($owner, 'sanctum')
            ->postJson("/api/v1/organizations/{$organizationId}/webhooks", [
                'url' => 'https://partner.example.com/other',
                'events' => ['emergency.triggered'],
            ]);

        // Inactive webhook for the matching event: should NOT be delivered.
        $inactive = Webhook::query()->create([
            'organization_id' => $organizationId,
            'url' => 'https://partner.example.com/inactive',
            'events' => ['incident.opened'],
            'secret' => str_repeat('a', 40),
            'active' => false,
        ]);

        Bus::fake();
        // Belt-and-suspenders: even though the job is faked, never allow a real
        // outbound call if the queued listener runs inline under the sync queue.
        Http::fake(['*' => Http::response(['ok' => true], 200)]);

        $this->actingAs($owner, 'sanctum')
            ->postJson("/api/v1/organizations/{$organizationId}/incidents", [
                'title' => 'Perimeter breach',
                'severity' => 'high',
            ])
            ->assertCreated();

        Bus::assertDispatched(
            DeliverWebhook::class,
            static fn (DeliverWebhook $job): bool => $job->webhookId === $matchingId
                && $job->event === 'incident.opened',
        );

        // Exactly one delivery: only the matching active webhook.
        Bus::assertDispatchedTimes(DeliverWebhook::class, 1);

        $this->assertNotSame($inactive->getKey(), $matchingId);
    }

    public function test_deliver_webhook_signs_the_body_and_records_a_successful_delivery(): void
    {
        $organization = $this->ownerWithOrganization();
        [, $organizationId] = $organization;

        $secret = str_repeat('s', 40);
        $webhook = Webhook::query()->create([
            'organization_id' => $organizationId,
            'url' => 'https://partner.example.com/sign',
            'events' => ['incident.opened'],
            'secret' => $secret,
            'active' => true,
        ]);

        Http::fake([
            'partner.example.com/*' => Http::response(['ok' => true], 200),
        ]);

        $payload = [
            'event' => 'incident.opened',
            'organization_id' => $organizationId,
            'occurred_at' => now()->toIso8601String(),
            'data' => ['id' => 'incident-1', 'type' => 'Incident'],
        ];

        (new DeliverWebhook((string) $webhook->getKey(), 'incident.opened', $payload))->handle();

        $expectedSignature = hash_hmac('sha256', (string) json_encode($payload), $secret);

        Http::assertSent(static function ($request) use ($expectedSignature): bool {
            return $request->hasHeader('X-Sentrix-Signature', $expectedSignature);
        });

        $this->assertDatabaseHas('webhook_deliveries', [
            'webhook_id' => $webhook->getKey(),
            'event' => 'incident.opened',
            'signature' => $expectedSignature,
            'status_code' => 200,
            'success' => true,
        ]);

        $delivery = WebhookDelivery::query()->where('webhook_id', $webhook->getKey())->firstOrFail();
        $this->assertTrue($delivery->success);
        $this->assertNotNull($delivery->delivered_at);
    }
}
