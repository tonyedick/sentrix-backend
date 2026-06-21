<?php

declare(strict_types=1);

namespace Tests\Feature\Responder;

use App\Domains\Authorization\Database\Seeders\PermissionCatalogueSeeder;
use App\Domains\Authorization\Support\Enums\OrganizationRole;
use App\Domains\Organization\Models\Organization;
use App\Domains\Organization\Services\MembershipService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

final class ResponderLocationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionCatalogueSeeder::class);
    }

    public function test_responder_can_ingest_own_location_and_position_advances(): void
    {
        $owner = User::factory()->create();
        $organizationId = $this->actingAs($owner, 'sanctum')
            ->postJson('/api/v1/organizations', ['name' => 'Acme Inc'])
            ->json('data.id');
        $organization = Organization::findOrFail($organizationId);

        $bob = User::factory()->create();
        app(MembershipService::class)->addMember($organization, $bob, OrganizationRole::Responder->value);

        $responderId = $this->actingAs($owner, 'sanctum')
            ->postJson("/api/v1/organizations/{$organizationId}/responders", ['user_id' => $bob->getKey()])
            ->json('data.id');

        $this->actingAs($bob, 'sanctum')
            ->postJson("/api/v1/organizations/{$organizationId}/responders/{$responderId}/locations", [
                'fixes' => [
                    ['client_fix_id' => 'fix-1', 'lat' => 6.5244, 'lng' => 3.3792, 'recorded_at' => Carbon::now()->toIso8601String()],
                ],
            ])
            ->assertStatus(202)
            ->assertJsonPath('data.stored', 1);

        $this->assertDatabaseHas('responder_locations', [
            'responder_id' => $responderId,
            'client_fix_id' => 'fix-1',
        ]);

        $responder = \App\Domains\Responder\Models\Responder::findOrFail($responderId);
        $this->assertSame(6.5244, (float) $responder->last_lat);
        $this->assertNotNull($responder->last_seen_at);
    }

    public function test_duplicate_fix_is_idempotent(): void
    {
        $owner = User::factory()->create();
        $organizationId = $this->actingAs($owner, 'sanctum')
            ->postJson('/api/v1/organizations', ['name' => 'Acme Inc'])
            ->json('data.id');
        $organization = Organization::findOrFail($organizationId);

        $bob = User::factory()->create();
        app(MembershipService::class)->addMember($organization, $bob, OrganizationRole::Responder->value);
        $responderId = $this->actingAs($owner, 'sanctum')
            ->postJson("/api/v1/organizations/{$organizationId}/responders", ['user_id' => $bob->getKey()])
            ->json('data.id');

        $payload = [
            'fixes' => [
                ['client_fix_id' => 'dup', 'lat' => 6.5, 'lng' => 3.3, 'recorded_at' => Carbon::now()->toIso8601String()],
            ],
        ];

        $this->actingAs($bob, 'sanctum')->postJson("/api/v1/organizations/{$organizationId}/responders/{$responderId}/locations", $payload)->assertStatus(202);
        $this->actingAs($bob, 'sanctum')->postJson("/api/v1/organizations/{$organizationId}/responders/{$responderId}/locations", $payload)->assertStatus(202);

        $this->assertSame(1, \App\Domains\Responder\Models\ResponderLocation::where('responder_id', $responderId)->count());
    }
}
