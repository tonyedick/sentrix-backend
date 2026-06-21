<?php

declare(strict_types=1);

namespace Tests\Feature\RidesOps;

use App\Domains\Authorization\Database\Seeders\PermissionCatalogueSeeder;
use App\Domains\Authorization\Services\RoleService;
use App\Domains\Command\Models\CommandIncident;
use App\Domains\DriverOnboarding\Models\Driver;
use App\Domains\Rides\Models\Ride;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Rides Ops — platform/staff (SuperAdmin-gated) operations console.
 */
final class RidesOpsTest extends TestCase
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

    private function makeRide(array $overrides = []): Ride
    {
        $rider = User::factory()->create();

        return Ride::create(array_merge([
            'user_id' => $rider->getKey(),
            'ride_class' => 'go_safe',
            'status' => 'matched',
            'origin_label' => 'Victoria Island',
            'origin_lat' => 6.4281,
            'origin_lng' => 3.4219,
            'dest_label' => 'Ikeja',
            'dest_lat' => 6.6018,
            'dest_lng' => 3.3515,
            'distance_km' => 12.5,
            'fare_estimate_cents' => 250000,
            'final_fare_cents' => null,
            'tip_cents' => 0,
            'currency' => 'NGN',
            'surge_multiplier' => 1.00,
            'payment_method' => 'cash',
            'match_code' => '4821',
            'driver_id' => 'sd-4f2a9c',
            'driver_name' => 'Emeka U.',
            'driver_plate' => 'KJA-482-AB',
            'requested_at' => now(),
        ], $overrides));
    }

    private function makeDriver(string $stage = 'active', string $availability = 'online'): Driver
    {
        $user = User::factory()->create();

        return Driver::create([
            'user_id' => $user->getKey(),
            'stage' => $stage,
            'availability' => $availability,
            'vehicle_make' => 'Toyota',
            'vehicle_model' => 'Corolla',
            'vehicle_plate' => 'KJA-482-AB',
            'vehicle_color' => 'Silver',
        ]);
    }

    /**
     * Seed an NPF agency + national HQ + GPS divisional command so the escalate
     * path (CommandRoutingService::route) can open an incident.
     */
    private function seedCommandStructure(User $admin): void
    {
        $agencyId = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/v1/command/agencies', [
                'code' => 'NPF',
                'name' => 'Nigeria Police Force',
                'country' => 'NG',
                'categories' => ['crime'],
                'hotline' => '112',
            ])
            ->assertCreated()
            ->json('data.id');

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/v1/command/commands', [
                'agency_id' => $agencyId,
                'tier' => 'national',
                'name' => 'Force Headquarters',
                'area' => 'Abuja',
            ])
            ->assertCreated();

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/v1/command/commands', [
                'agency_id' => $agencyId,
                'tier' => 'divisional',
                'name' => 'VI Division',
                'area' => 'Victoria Island',
                'lat' => 6.4281,
                'lng' => 3.4219,
            ])
            ->assertCreated();
    }

    public function test_overview_returns_kpi_keys(): void
    {
        $admin = $this->superAdmin();
        $this->makeRide(['status' => 'completed', 'final_fare_cents' => 250000]);
        $this->makeRide(['status' => 'matched']);
        $this->makeDriver();

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/v1/rides/admin/overview')
            ->assertOk()
            ->assertJsonPath('data.rides.active', 1)
            ->assertJsonPath('data.rides.completed', 1)
            ->assertJsonPath('data.revenue_cents', 250000)
            ->assertJsonPath('data.fleet.total', 1)
            ->assertJsonPath('data.fleet.active', 1)
            ->assertJsonPath('data.drivers.online', 1)
            // Whole-number surge serializes to JSON as 1 (no .0).
            ->assertJsonPath('data.surge', 1);
    }

    public function test_rides_list_returns_the_rides(): void
    {
        $admin = $this->superAdmin();
        $this->makeRide();
        $this->makeRide(['status' => 'completed', 'final_fare_cents' => 300000]);

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/v1/rides/admin/rides')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_onboarding_funnel_counts_by_stage(): void
    {
        $admin = $this->superAdmin();
        $this->makeDriver('documents_review', 'offline');
        $this->makeDriver('active', 'online');
        $this->makeDriver('active', 'on_trip');

        $response = $this->actingAs($admin, 'sanctum')
            ->getJson('/api/v1/rides/admin/onboarding')
            ->assertOk()
            ->assertJsonPath('data.total', 3);

        $funnel = collect($response->json('data.funnel'))->keyBy('stage');
        $this->assertSame(2, $funnel['active']['count']);
        $this->assertSame(1, $funnel['documents_review']['count']);
    }

    public function test_surge_pin_then_overview_reflects_it_then_release(): void
    {
        $admin = $this->superAdmin();

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/v1/rides/admin/surge', ['multiplier' => 1.5, 'note' => 'Peak demand'])
            ->assertOk()
            ->assertJsonPath('data.pinned', true);

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/v1/rides/admin/overview')
            ->assertOk()
            ->assertJsonPath('data.surge', 1.5);

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/v1/rides/admin/surge', ['release' => true])
            ->assertOk()
            ->assertJsonPath('data.pinned', false)
            // A whole-number multiplier serializes to JSON as 1 (no .0).
            ->assertJsonPath('data.multiplier', 1);

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/v1/rides/admin/overview')
            ->assertOk()
            ->assertJsonPath('data.surge', 1);
    }

    public function test_force_cancel_sets_ride_cancelled(): void
    {
        $admin = $this->superAdmin();
        $ride = $this->makeRide(['status' => 'matched']);

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/rides/admin/rides/{$ride->getKey()}/cancel", ['reason' => 'Safety'])
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled');

        $this->assertSame('cancelled', $ride->fresh()->status->value);
    }

    public function test_suspend_then_reinstate_flips_driver_stage(): void
    {
        $admin = $this->superAdmin();
        $driver = $this->makeDriver('active', 'online');

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/rides/admin/drivers/{$driver->getKey()}/suspend", ['reason' => 'Abuse'])
            ->assertOk()
            ->assertJsonPath('data.stage', 'suspended')
            ->assertJsonPath('data.availability', 'offline');

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/rides/admin/drivers/{$driver->getKey()}/reinstate")
            ->assertOk()
            ->assertJsonPath('data.stage', 'active');
    }

    public function test_escalate_a_ride_creates_a_command_incident(): void
    {
        $admin = $this->superAdmin();
        $this->seedCommandStructure($admin);
        $ride = $this->makeRide(['status' => 'in_progress']);

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/rides/admin/rides/{$ride->getKey()}/escalate")
            ->assertOk()
            ->assertJsonPath('data.ride_id', $ride->getKey())
            ->assertJsonPath('data.severity', 'high');

        $this->assertSame(1, CommandIncident::query()->count());
    }

    public function test_non_superadmin_is_forbidden_on_overview_and_write(): void
    {
        $user = User::factory()->create();
        $ride = $this->makeRide();

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/rides/admin/overview')
            ->assertForbidden();

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/rides/admin/rides/{$ride->getKey()}/cancel", ['reason' => 'x'])
            ->assertForbidden();
    }
}
