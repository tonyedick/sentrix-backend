<?php

declare(strict_types=1);

namespace Tests\Feature\DriverOnboarding;

use App\Domains\Authorization\Database\Seeders\PermissionCatalogueSeeder;
use App\Domains\Authorization\Services\RoleService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Driver onboarding pipeline: register -> upload doc -> staff approves doc ->
 * staff decision approve -> driver books inspection -> staff inspection pass ->
 * driver active -> driver can go online. Plus the negative paths.
 */
final class DriverOnboardingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionCatalogueSeeder::class);
    }

    private function driverUser(): User
    {
        // A plain consumer user — no organization.
        return User::factory()->create();
    }

    private function superAdmin(): User
    {
        $admin = User::factory()->create();
        app(RoleService::class)->assignSuperAdmin($admin);

        return $admin;
    }

    public function test_full_pipeline_register_to_active_then_online(): void
    {
        $driver = $this->driverUser();
        $staff = $this->superAdmin();

        // 1) Register.
        $driverId = $this->actingAs($driver, 'sanctum')
            ->postJson('/api/v1/me/rides/driver/register', [
                'vehicle_make' => 'Toyota',
                'vehicle_model' => 'Corolla',
                'vehicle_plate' => 'kja-482-ab',
                'vehicle_color' => 'Silver',
            ])
            ->assertCreated()
            ->assertJsonPath('data.stage', 'documents_review')
            ->assertJsonPath('data.availability', 'offline')
            ->assertJsonPath('data.vehicle.plate', 'KJA-482-AB')
            ->json('data.id');

        // 2) Upload a document.
        $documentId = $this->actingAs($driver, 'sanctum')
            ->postJson('/api/v1/me/rides/driver/documents', [
                'type' => 'license',
                'url' => 'sentrix-doc://license/abc',
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'pending')
            ->json('data.id');

        // Driver appears in the staff document-review queue.
        $this->actingAs($staff, 'sanctum')
            ->getJson('/api/v1/rides/staff/driver-queue')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $driverId);

        // 3) Staff approves the document.
        $this->actingAs($staff, 'sanctum')
            ->postJson("/api/v1/rides/staff/drivers/{$driverId}/document", [
                'document_id' => $documentId,
                'decision' => 'approve',
                'note' => 'Looks valid.',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'approved');

        // 4) Staff overall decision: approve -> documents_approved.
        $this->actingAs($staff, 'sanctum')
            ->postJson("/api/v1/rides/staff/drivers/{$driverId}/decision", [
                'decision' => 'approve',
            ])
            ->assertOk()
            ->assertJsonPath('data.stage', 'documents_approved');

        // 5) Driver lists vetting centers and books an inspection.
        $centerId = $this->actingAs($driver, 'sanctum')
            ->getJson('/api/v1/me/rides/driver/vetting-centers')
            ->assertOk()
            ->json('data.0.id');

        $this->actingAs($driver, 'sanctum')
            ->postJson('/api/v1/me/rides/driver/inspection/book', [
                'vetting_center_id' => $centerId,
                'slot' => 'Mon 09:00',
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'booked');

        // Driver now shows inspection_booked.
        $this->actingAs($driver, 'sanctum')
            ->getJson('/api/v1/me/rides/driver/me')
            ->assertOk()
            ->assertJsonPath('data.stage', 'inspection_booked')
            ->assertJsonPath('data.latest_inspection.status', 'booked');

        // Inspection appears in the staff inspection queue.
        $this->actingAs($staff, 'sanctum')
            ->getJson('/api/v1/rides/staff/inspection-queue')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        // 6) Staff records an inspection pass -> active + hardware installed.
        $this->actingAs($staff, 'sanctum')
            ->postJson("/api/v1/rides/staff/drivers/{$driverId}/inspection", [
                'decision' => 'pass',
                'checklist' => ['brakes' => true, 'tyres' => true],
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'passed');

        $this->actingAs($driver, 'sanctum')
            ->getJson('/api/v1/me/rides/driver/me')
            ->assertOk()
            ->assertJsonPath('data.stage', 'active')
            ->assertJsonPath('data.installed_hardware', ['gps', 'dashcam', 'panic_button', 'immobilizer']);

        // 7) Driver can now go online.
        $this->actingAs($driver, 'sanctum')
            ->postJson('/api/v1/me/rides/driver/online', ['online' => true])
            ->assertOk()
            ->assertJsonPath('data.availability', 'online');
    }

    public function test_going_online_before_active_is_422(): void
    {
        $driver = $this->driverUser();

        $this->actingAs($driver, 'sanctum')
            ->postJson('/api/v1/me/rides/driver/register')
            ->assertCreated();

        $this->actingAs($driver, 'sanctum')
            ->postJson('/api/v1/me/rides/driver/online', ['online' => true])
            ->assertStatus(422)
            ->assertJsonValidationErrors('driver_not_active');
    }

    public function test_non_superadmin_hitting_a_staff_endpoint_is_403(): void
    {
        $driver = $this->driverUser();
        $outsider = $this->driverUser();

        $driverId = $this->actingAs($driver, 'sanctum')
            ->postJson('/api/v1/me/rides/driver/register')
            ->assertCreated()
            ->json('data.id');

        // Read queue.
        $this->actingAs($outsider, 'sanctum')
            ->getJson('/api/v1/rides/staff/driver-queue')
            ->assertForbidden();

        // Write decision.
        $this->actingAs($outsider, 'sanctum')
            ->postJson("/api/v1/rides/staff/drivers/{$driverId}/decision", ['decision' => 'approve'])
            ->assertForbidden();
    }

    public function test_registering_twice_is_409(): void
    {
        $driver = $this->driverUser();

        $this->actingAs($driver, 'sanctum')
            ->postJson('/api/v1/me/rides/driver/register')
            ->assertCreated();

        $this->actingAs($driver, 'sanctum')
            ->postJson('/api/v1/me/rides/driver/register')
            ->assertStatus(409);
    }
}
