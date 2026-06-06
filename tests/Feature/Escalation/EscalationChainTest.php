<?php

declare(strict_types=1);

namespace Tests\Feature\Escalation;

use App\Domains\Authorization\Database\Seeders\PermissionCatalogueSeeder;
use App\Domains\Emergency\Models\Emergency;
use App\Domains\Incident\Models\Incident;
use App\Domains\Trip\Models\Trip;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class EscalationChainTest extends TestCase
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

    public function test_overdue_sweep_flags_trips_and_raises_one_emergency(): void
    {
        [$owner, $organizationId] = $this->ownerWithOrganization();

        // A trip already past its expected arrival (bypassing the after:now rule).
        $trip = Trip::create([
            'organization_id' => $organizationId,
            'user_id' => $owner->id,
            'status' => 'active',
            'started_at' => now()->subHour(),
            'expected_arrival_at' => now()->subMinutes(10),
        ]);

        $this->artisan('trips:flag-overdue')->assertSuccessful();

        $this->assertDatabaseHas('trips', ['id' => $trip->id, 'status' => 'overdue']);
        $this->assertDatabaseHas('emergencies', ['trip_id' => $trip->id, 'status' => 'triggered']);
        $this->assertSame(1, Emergency::where('trip_id', $trip->id)->count());

        // Idempotent: a second sweep neither re-flags nor duplicates the emergency.
        $this->artisan('trips:flag-overdue')->assertSuccessful();
        $this->assertSame(1, Emergency::where('trip_id', $trip->id)->count());
    }

    public function test_a_critical_emergency_auto_opens_a_linked_incident(): void
    {
        [$owner, $organizationId] = $this->ownerWithOrganization();

        $emergencyId = $this->actingAs($owner, 'sanctum')
            ->postJson("/api/v1/organizations/{$organizationId}/emergencies", ['severity' => 'critical'])
            ->assertCreated()
            ->json('data.id');

        $this->assertDatabaseHas('incidents', [
            'emergency_id' => $emergencyId,
            'severity' => 'critical',
        ]);
        $this->assertSame(1, Incident::where('emergency_id', $emergencyId)->count());
    }

    public function test_a_non_critical_emergency_does_not_open_an_incident(): void
    {
        [$owner, $organizationId] = $this->ownerWithOrganization();

        $emergencyId = $this->actingAs($owner, 'sanctum')
            ->postJson("/api/v1/organizations/{$organizationId}/emergencies", ['severity' => 'high'])
            ->assertCreated()
            ->json('data.id');

        $this->assertSame(0, Incident::where('emergency_id', $emergencyId)->count());
    }
}
