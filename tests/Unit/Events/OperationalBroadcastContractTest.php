<?php

declare(strict_types=1);

namespace Tests\Unit\Events;

use App\Domains\Emergency\Events\EmergencyTriggered;
use App\Domains\Emergency\Models\Emergency;
use App\Domains\Incident\Events\IncidentEscalated;
use App\Domains\Incident\Events\IncidentOpened;
use App\Domains\Incident\Models\Incident;
use App\Domains\Trip\Events\TripStarted;
use App\Domains\Trip\Models\Trip;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Tests\TestCase;

/**
 * Locks in the realtime contract shared by every operational event: the private
 * organization channel, the stable broadcast name, after-commit delivery, and
 * critical-queue routing for life-safety events.
 */
final class OperationalBroadcastContractTest extends TestCase
{
    private const ORG = '11111111-1111-1111-1111-111111111111';

    public function test_trip_started_broadcasts_on_the_org_channel_on_the_default_queue(): void
    {
        $trip = new Trip();
        $trip->id = '22222222-2222-2222-2222-222222222222';
        $trip->organization_id = self::ORG;

        $event = new TripStarted($trip, 'actor-id', ['status' => 'active']);

        $this->assertInstanceOf(ShouldBroadcast::class, $event);
        $this->assertSame('private-organizations.'.self::ORG, (string) $event->broadcastOn()[0]);
        $this->assertSame('trip.started', $event->broadcastAs());
        $this->assertTrue($event->afterCommit);
        $this->assertNull($event->broadcastQueue);
        $this->assertSame('active', $event->broadcastWith()['status']);
        $this->assertSame($trip->id, $event->broadcastWith()['id']);
    }

    public function test_emergency_triggered_is_routed_to_the_critical_queue(): void
    {
        $emergency = new Emergency();
        $emergency->id = '33333333-3333-3333-3333-333333333333';
        $emergency->organization_id = self::ORG;

        $event = new EmergencyTriggered($emergency, 'actor-id', ['severity' => 'critical']);

        $this->assertSame('emergency.triggered', $event->broadcastAs());
        $this->assertSame('critical', $event->broadcastQueue);
    }

    public function test_incident_escalation_is_critical_but_routine_incident_events_are_not(): void
    {
        $incident = new Incident();
        $incident->id = '44444444-4444-4444-4444-444444444444';
        $incident->organization_id = self::ORG;

        $this->assertSame('critical', (new IncidentEscalated($incident, null))->broadcastQueue);
        $this->assertNull((new IncidentOpened($incident, null))->broadcastQueue);
    }
}
