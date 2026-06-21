<?php

declare(strict_types=1);

namespace Tests\Feature\Core;

use App\Domains\Authorization\Database\Seeders\PermissionCatalogueSeeder;
use App\Domains\Authorization\Services\RoleService;
use App\Domains\Core\Events\CoreEventReceived;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class CoreBridgeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionCatalogueSeeder::class);
    }

    private function superAdmin(): User
    {
        $admin = User::factory()->create();
        app(RoleService::class)->assignSuperAdmin($admin);

        return $admin;
    }

    // ----- /act ---------------------------------------------------------------

    public function test_act_forwards_auth_headers_and_returns_core_payload(): void
    {
        config(['sentrix.core.endpoint' => 'http://core.test', 'sentrix.core.api_key' => 'svc-secret']);

        Http::fake([
            'core.test/api/core/act' => Http::response([
                'ok' => true,
                'message' => 'SOS dispatched',
                'result' => ['ref' => 'em_1'],
            ], 200),
        ]);

        // SuperAdmin so the forwarded X-Sentrix-Scopes header resolves to a
        // non-empty permission list.
        $user = $this->superAdmin();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/core/act', ['tool' => 'sos', 'args' => [], 'confirmed' => true])
            ->assertOk()
            ->assertJsonPath('data.ok', true)
            ->assertJsonPath('data.message', 'SOS dispatched')
            ->assertJsonPath('data.result.ref', 'em_1');

        Http::assertSent(function ($request) use ($user): bool {
            return $request->url() === 'http://core.test/api/core/act'
                && $request->hasHeader('X-Service-Token', 'svc-secret')
                && $request->hasHeader('X-Sentrix-User', (string) $user->getKey())
                && $request->hasHeader('X-Sentrix-Scopes')
                && $request['tool'] === 'sos';
        });
    }

    public function test_act_fails_safe_when_endpoint_unset(): void
    {
        config(['sentrix.core.endpoint' => null]);

        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/core/act', ['tool' => 'sos', 'args' => []])
            ->assertOk()
            ->assertJsonPath('data.ok', false)
            ->assertJsonPath('data.message', 'Core is offline')
            ->assertJsonPath('data._simulated', true);
    }

    // ----- /chat --------------------------------------------------------------

    public function test_chat_fails_safe_with_offline_sse_when_endpoint_unset(): void
    {
        config(['sentrix.core.endpoint' => null]);

        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/core/chat', ['messages' => [['role' => 'user', 'content' => 'help']]]);

        $response->assertOk();
        $this->assertStringContainsString('text/event-stream', (string) $response->headers->get('Content-Type'));
        $this->assertStringContainsString('no-cache', (string) $response->headers->get('Cache-Control'));
        $response->assertHeader('X-Accel-Buffering', 'no');

        $body = $response->streamedContent();

        $this->assertStringContainsString('The assistant is offline right now.', $body);
        $this->assertStringContainsString('data: [DONE]', $body);
    }

    // ----- /events ------------------------------------------------------------

    public function test_events_broadcasts_and_returns_accepted(): void
    {
        config(['sentrix.core.api_key' => 'svc-secret']);

        Event::fake([CoreEventReceived::class]);

        $this->withHeaders(['X-Service-Token' => 'svc-secret'])
            ->postJson('/api/v1/core/events', [
                'type' => 'omni.weapon_detected',
                'source' => 'cam_07',
                'severity' => 'critical',
                'summary' => 'Weapon at rear gate',
                'org' => 'acme',
                'site' => 'HQ',
                'zone' => 'B',
                'subjects' => ['user_1'],
                'location' => ['lat' => 6.5, 'lng' => 3.3],
                'payload' => ['confidence' => 0.97],
            ])
            ->assertStatus(202)
            ->assertJsonPath('data.accepted', true);

        Event::assertDispatched(
            CoreEventReceived::class,
            static fn (CoreEventReceived $event): bool => $event->event->type === 'omni.weapon_detected'
                && $event->event->org === 'acme'
                && $event->broadcastAs() === 'omni.weapon_detected',
        );
    }

    public function test_events_rejected_without_service_token(): void
    {
        config(['sentrix.core.api_key' => 'svc-secret']);

        $this->postJson('/api/v1/core/events', [
            'type' => 'omni.intrusion',
            'source' => 'cam_01',
            'severity' => 'high',
            'summary' => 'Intrusion',
            'org' => 'acme',
        ])->assertUnauthorized();
    }

    // ----- /command-center ----------------------------------------------------

    public function test_command_center_returns_aggregate_for_super_admin(): void
    {
        $admin = $this->superAdmin();

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/v1/core/command-center')
            ->assertOk()
            ->assertJsonStructure(['data' => [
                'open_incidents_by_severity',
                'open_incidents_total',
                'active_emergencies',
                'on_duty_responders',
            ]]);
    }

    public function test_command_center_is_forbidden_for_a_normal_user(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/core/command-center')
            ->assertForbidden();
    }
}
