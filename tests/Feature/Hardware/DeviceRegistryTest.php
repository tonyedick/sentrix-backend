<?php

declare(strict_types=1);

namespace Tests\Feature\Hardware;

use App\Domains\Authorization\Database\Seeders\PermissionCatalogueSeeder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class DeviceRegistryTest extends TestCase
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
            ->postJson('/api/v1/organizations', ['name' => 'Acme Security'])
            ->json('data.id');

        return [$owner, $organizationId];
    }

    private function registerDevice(User $owner, string $organizationId, string $serial = 'GS-0001'): string
    {
        return $this->actingAs($owner, 'sanctum')
            ->postJson("/api/v1/organizations/{$organizationId}/hardware", [
                'kind' => 'gate_scanner',
                'serial' => $serial,
                'name' => 'Main Gate Scanner',
                'site' => 'HQ',
                'zone' => 'Entrance',
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.kind', 'gate_scanner')
            ->json('data.id');
    }

    public function test_owner_can_register_and_list_a_device(): void
    {
        [$owner, $organizationId] = $this->ownerWithOrganization();

        $deviceId = $this->registerDevice($owner, $organizationId);

        $this->assertDatabaseHas('hardware_devices', [
            'id' => $deviceId,
            'organization_id' => $organizationId,
            'serial' => 'GS-0001',
            'status' => 'active',
        ]);

        $this->actingAs($owner, 'sanctum')
            ->getJson("/api/v1/organizations/{$organizationId}/hardware")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $deviceId);
    }

    public function test_resync_marks_device_active_and_stamps_last_seen(): void
    {
        [$owner, $organizationId] = $this->ownerWithOrganization();
        $deviceId = $this->registerDevice($owner, $organizationId);

        // Force the device into a non-active state with no recent heartbeat.
        \App\Domains\Hardware\Models\Device::query()
            ->whereKey($deviceId)
            ->update(['status' => 'offline', 'last_seen_at' => now()->subDay()]);

        $this->actingAs($owner, 'sanctum')
            ->postJson("/api/v1/organizations/{$organizationId}/hardware/{$deviceId}/resync")
            ->assertOk()
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.id', $deviceId);

        $device = \App\Domains\Hardware\Models\Device::query()->findOrFail($deviceId);
        $this->assertSame('active', $device->status->value);
        $this->assertNotNull($device->last_seen_at);
        $this->assertTrue($device->last_seen_at->greaterThan(now()->subMinute()));
    }

    public function test_diagnose_returns_a_health_field_without_changing_state(): void
    {
        [$owner, $organizationId] = $this->ownerWithOrganization();
        $deviceId = $this->registerDevice($owner, $organizationId);

        $this->actingAs($owner, 'sanctum')
            ->postJson("/api/v1/organizations/{$organizationId}/hardware/{$deviceId}/diagnose")
            ->assertOk()
            ->assertJsonPath('data.health', 'online')
            ->assertJsonPath('data.status', 'active')
            ->assertJsonStructure(['data' => ['status', 'last_seen_at', 'health']]);
    }

    public function test_outsider_is_forbidden(): void
    {
        [$owner, $organizationId] = $this->ownerWithOrganization();
        $this->registerDevice($owner, $organizationId);

        $outsider = User::factory()->create();

        $this->actingAs($outsider, 'sanctum')
            ->postJson("/api/v1/organizations/{$organizationId}/hardware", [
                'kind' => 'sensor',
                'serial' => 'SN-9',
                'name' => 'Intruder',
            ])
            ->assertForbidden();
    }

    public function test_device_from_another_organization_is_not_found(): void
    {
        [$ownerA, $organizationA] = $this->ownerWithOrganization();
        $deviceA = $this->registerDevice($ownerA, $organizationA);

        [$ownerB, $organizationB] = $this->ownerWithOrganization();

        // Owner B is a legitimate admin of org B, but the device lives in org A.
        $this->actingAs($ownerB, 'sanctum')
            ->getJson("/api/v1/organizations/{$organizationB}/hardware/{$deviceA}")
            ->assertNotFound();
    }
}
