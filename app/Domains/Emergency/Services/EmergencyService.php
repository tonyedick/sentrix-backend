<?php

declare(strict_types=1);

namespace App\Domains\Emergency\Services;

use App\Domains\Emergency\DTOs\TriggerEmergencyData;
use App\Domains\Emergency\Events\EmergencyAcknowledged;
use App\Domains\Emergency\Events\EmergencyCancelled;
use App\Domains\Emergency\Events\EmergencyResolved;
use App\Domains\Emergency\Events\EmergencyTriggered;
use App\Domains\Emergency\Models\Emergency;
use App\Domains\Emergency\Support\Enums\EmergencySeverity;
use App\Domains\Emergency\Support\Enums\EmergencyStatus;
use App\Domains\Organization\Models\Organization;
use App\Domains\Trip\Models\Trip;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Owns the emergency lifecycle. Each transition runs inside a transaction that
 * locks the row (`lockForUpdate`) and re-reads its state before mutating, so two
 * concurrent responders cannot both acknowledge/resolve the same emergency — the
 * loser sees the committed new state and is rejected.
 */
final readonly class EmergencyService
{
    /**
     * Raise an emergency on behalf of the acting user.
     */
    public function trigger(Organization $organization, TriggerEmergencyData $data, User $actor): Emergency
    {
        return DB::transaction(function () use ($organization, $data, $actor): Emergency {
            $emergency = Emergency::create([
                'organization_id' => $organization->getKey(),
                'user_id' => $actor->getKey(),
                'trip_id' => $data->tripId,
                'status' => EmergencyStatus::Triggered,
                'severity' => $data->severity,
                'message' => $data->message,
                'lat' => $data->lat,
                'lng' => $data->lng,
                'triggered_at' => now(),
            ]);

            event(new EmergencyTriggered($emergency, $actor->getKey(), [
                'status' => $emergency->status->value,
                'severity' => $emergency->severity->value,
                'trip_id' => $emergency->trip_id,
            ]));

            return $emergency;
        });
    }

    /**
     * Auto-raise an emergency for a trip that has gone overdue.
     */
    public function raiseForOverdueTrip(Trip $trip): ?Emergency
    {
        return $this->raiseForTrip($trip, 'trip.overdue', 'Trip overdue — automatic escalation.');
    }

    /**
     * Auto-raise an emergency for a trip from an automated source (overdue, lost
     * contact, …). Idempotent: a no-op if the trip already has a live (non-terminal)
     * emergency, so repeated sweeps or retried listeners never create duplicates —
     * and a trip that is both overdue and dark yields a single emergency. Attributed
     * to the system (no acting user).
     */
    public function raiseForTrip(Trip $trip, string $source, string $message): ?Emergency
    {
        return DB::transaction(function () use ($trip, $source, $message): ?Emergency {
            $hasLiveEmergency = Emergency::query()
                ->where('trip_id', $trip->getKey())
                ->whereIn('status', [EmergencyStatus::Triggered->value, EmergencyStatus::Acknowledged->value])
                ->lockForUpdate()
                ->exists();

            if ($hasLiveEmergency) {
                return null;
            }

            $emergency = Emergency::create([
                'organization_id' => $trip->organization_id,
                'user_id' => $trip->user_id,
                'trip_id' => $trip->getKey(),
                'status' => EmergencyStatus::Triggered,
                'severity' => EmergencySeverity::High,
                'message' => $message,
                'triggered_at' => now(),
                'metadata' => ['source' => $source],
            ]);

            event(new EmergencyTriggered($emergency, null, [
                'status' => $emergency->status->value,
                'severity' => $emergency->severity->value,
                'trip_id' => $emergency->trip_id,
                'source' => $source,
            ]));

            return $emergency;
        });
    }

    /**
     * A responder/dispatcher takes ownership. Only a freshly-triggered emergency
     * can be acknowledged.
     */
    public function acknowledge(Emergency $emergency, User $actor): Emergency
    {
        return DB::transaction(function () use ($emergency, $actor): Emergency {
            $locked = $this->lockFresh($emergency);

            if ($locked->status !== EmergencyStatus::Triggered) {
                throw ValidationException::withMessages([
                    'status' => ["Only a triggered emergency can be acknowledged (this one is {$locked->status->value})."],
                ]);
            }

            $locked->update([
                'status' => EmergencyStatus::Acknowledged,
                'acknowledged_at' => now(),
                'acknowledged_by' => $actor->getKey(),
            ]);

            event(new EmergencyAcknowledged($locked, $actor->getKey(), ['status' => $locked->status->value]));

            return $locked;
        });
    }

    /**
     * Resolve an emergency that has not already reached a terminal state.
     */
    public function resolve(Emergency $emergency, User $actor, ?string $resolution = null): Emergency
    {
        return DB::transaction(function () use ($emergency, $actor, $resolution): Emergency {
            $locked = $this->lockFresh($emergency);
            $this->assertNotTerminal($locked);

            $locked->update([
                'status' => EmergencyStatus::Resolved,
                'resolved_at' => now(),
                'resolved_by' => $actor->getKey(),
                'metadata' => $this->withResolution($locked, $resolution),
            ]);

            event(new EmergencyResolved($locked, $actor->getKey(), ['status' => $locked->status->value]));

            return $locked;
        });
    }

    /**
     * Stand down a non-terminal emergency as a false alarm.
     */
    public function cancel(Emergency $emergency, User $actor): Emergency
    {
        return DB::transaction(function () use ($emergency, $actor): Emergency {
            $locked = $this->lockFresh($emergency);
            $this->assertNotTerminal($locked);

            $locked->update([
                'status' => EmergencyStatus::Cancelled,
                'cancelled_at' => now(),
            ]);

            event(new EmergencyCancelled($locked, $actor->getKey(), ['status' => $locked->status->value]));

            return $locked;
        });
    }

    /**
     * Re-read the row under a write lock so the state check + mutation are atomic
     * against concurrent transitions.
     */
    private function lockFresh(Emergency $emergency): Emergency
    {
        return Emergency::query()->whereKey($emergency->getKey())->lockForUpdate()->firstOrFail();
    }

    /**
     * @return array<string, mixed>
     */
    private function withResolution(Emergency $emergency, ?string $resolution): array
    {
        $metadata = $emergency->metadata ?? [];

        if ($resolution !== null && $resolution !== '') {
            $metadata['resolution'] = $resolution;
        }

        return $metadata;
    }

    private function assertNotTerminal(Emergency $emergency): void
    {
        if ($emergency->status->isTerminal()) {
            throw ValidationException::withMessages([
                'status' => ["This emergency is already {$emergency->status->value}."],
            ]);
        }
    }
}
