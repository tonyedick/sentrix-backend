<?php

declare(strict_types=1);

namespace Tests\Feature\Rides;

use App\Domains\Rides\Events\RideSosTriggered;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/**
 * Safe Rides — ride lifecycle + in-ride safety (rider-scoped).
 */
final class RideLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private const ORIGIN = ['origin_lat' => 6.5244, 'origin_lng' => 3.3792];
    private const DEST = ['dest_lat' => 6.6018, 'dest_lng' => 3.3515];

    /**
     * Book a ride for the given rider and return its id.
     */
    private function bookRide(User $rider): string
    {
        return $this->actingAs($rider, 'sanctum')
            ->postJson('/api/v1/me/rides/request', array_merge(
                ['ride_class' => 'go_safe'],
                self::ORIGIN,
                self::DEST,
            ))
            ->assertCreated()
            ->assertJsonPath('data.status', 'matched')
            ->json('data.id');
    }

    public function test_quote_returns_class_options_with_fares(): void
    {
        $rider = User::factory()->create();

        $this->actingAs($rider, 'sanctum')
            ->postJson('/api/v1/me/rides/quote', array_merge(self::ORIGIN, self::DEST))
            ->assertOk()
            ->assertJsonStructure([
                'data' => ['distance_km', 'surge_multiplier', 'currency', 'options' => [['ride_class', 'fare_cents', 'surge']]],
            ])
            ->assertJsonCount(3, 'data.options');
    }

    public function test_request_creates_matched_ride_with_code_and_safety_row(): void
    {
        $rider = User::factory()->create();
        $rideId = $this->bookRide($rider);

        // A 4-char match code is issued, and a driver is matched (simulated).
        $ride = $this->actingAs($rider, 'sanctum')->getJson("/api/v1/me/rides/{$rideId}")
            ->assertOk()
            ->assertJsonPath('data.id', $rideId)
            ->json('data');

        $this->assertNotEmpty($ride['match_code']);
        $this->assertNotNull($ride['driver']['name']);

        // The 1:1 safety row exists.
        $this->actingAs($rider, 'sanctum')->getJson("/api/v1/me/rides/{$rideId}/safety")
            ->assertOk()
            ->assertJsonPath('data.armed', false);

        $this->actingAs($rider, 'sanctum')->getJson('/api/v1/me/rides')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_track_returns_driver_position(): void
    {
        $rider = User::factory()->create();
        $rideId = $this->bookRide($rider);

        $this->actingAs($rider, 'sanctum')->getJson("/api/v1/me/rides/{$rideId}/track")
            ->assertOk()
            ->assertJsonStructure(['data' => ['driver_lat', 'driver_lng', 'driver_eta_minutes', 'driver_speed_kph']]);
    }

    public function test_arm_sets_recording_and_sos_emits_event(): void
    {
        $rider = User::factory()->create();
        $rideId = $this->bookRide($rider);

        $this->actingAs($rider, 'sanctum')->postJson("/api/v1/me/rides/{$rideId}/safety/arm")
            ->assertOk()
            ->assertJsonPath('data.armed', true)
            ->assertJsonPath('data.recording', true);

        Event::fake([RideSosTriggered::class]);

        $this->actingAs($rider, 'sanctum')->postJson("/api/v1/me/rides/{$rideId}/sos")
            ->assertOk();

        Event::assertDispatched(RideSosTriggered::class);
    }

    public function test_evidence_increments_count(): void
    {
        $rider = User::factory()->create();
        $rideId = $this->bookRide($rider);

        $this->actingAs($rider, 'sanctum')
            ->postJson("/api/v1/me/rides/{$rideId}/evidence", ['kind' => 'video', 'url' => 'https://evidence.test/clip1.mp4'])
            ->assertSuccessful();

        $this->actingAs($rider, 'sanctum')->getJson("/api/v1/me/rides/{$rideId}/safety")
            ->assertOk()
            ->assertJsonPath('data.evidence_count', 1);
    }

    public function test_complete_sets_status_and_final_fare(): void
    {
        $rider = User::factory()->create();
        $rideId = $this->bookRide($rider);

        $this->actingAs($rider, 'sanctum')
            ->postJson("/api/v1/me/rides/{$rideId}/complete", ['rating' => 5, 'tip_cents' => 500])
            ->assertOk()
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonPath('data.tip_cents', 500);
    }

    public function test_cancel_from_matched_then_not_after_completed(): void
    {
        $rider = User::factory()->create();

        $cancelId = $this->bookRide($rider);
        $this->actingAs($rider, 'sanctum')
            ->postJson("/api/v1/me/rides/{$cancelId}/cancel", ['reason' => 'Changed my mind'])
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled');

        $completedId = $this->bookRide($rider);
        $this->actingAs($rider, 'sanctum')
            ->postJson("/api/v1/me/rides/{$completedId}/complete", ['tip_cents' => 0])
            ->assertOk();
        $this->actingAs($rider, 'sanctum')
            ->postJson("/api/v1/me/rides/{$completedId}/cancel", ['reason' => 'too late'])
            ->assertStatus(422);
    }

    public function test_rider_cannot_see_another_riders_ride(): void
    {
        $rider = User::factory()->create();
        $rideId = $this->bookRide($rider);

        $other = User::factory()->create();
        $this->actingAs($other, 'sanctum')
            ->getJson("/api/v1/me/rides/{$rideId}")
            ->assertNotFound();
    }
}
