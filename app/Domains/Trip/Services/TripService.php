<?php

declare(strict_types=1);

namespace App\Domains\Trip\Services;

use App\Domains\Organization\Models\Organization;
use App\Domains\Trip\DTOs\StartTripData;
use App\Domains\Trip\DTOs\UpdateTripData;
use App\Domains\Trip\Events\TripCancelled;
use App\Domains\Trip\Events\TripCompleted;
use App\Domains\Trip\Events\TripMarkedOverdue;
use App\Domains\Trip\Events\TripStarted;
use App\Domains\Trip\Models\Trip;
use App\Domains\Trip\Support\Enums\TripStatus;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Owns the trip lifecycle. Every state change happens in a transaction that locks
 * and re-reads the row before mutating, so concurrent completes/cancels can't
 * race past the terminal-state check.
 */
final readonly class TripService
{
    /**
     * Start a monitored journey.
     */
    public function start(Organization $organization, StartTripData $data, User $actor): Trip
    {
        return DB::transaction(function () use ($organization, $data, $actor): Trip {
            $trip = Trip::create([
                'organization_id' => $organization->getKey(),
                'user_id' => $data->userId,
                'status' => TripStatus::Active,
                'origin_label' => $data->originLabel,
                'origin_lat' => $data->originLat,
                'origin_lng' => $data->originLng,
                'destination_label' => $data->destinationLabel,
                'destination_lat' => $data->destinationLat,
                'destination_lng' => $data->destinationLng,
                'started_at' => now(),
                'expected_arrival_at' => $data->expectedArrivalAt,
                'notes' => $data->notes,
            ]);

            event(new TripStarted($trip, $actor->getKey(), [
                'status' => $trip->status->value,
                'user_id' => $trip->user_id,
            ]));

            return $trip;
        });
    }

    /**
     * Patch mutable trip fields (destination, ETA, notes). Terminal trips are
     * immutable.
     */
    public function update(Trip $trip, UpdateTripData $data): Trip
    {
        return DB::transaction(function () use ($trip, $data): Trip {
            $locked = $this->lockFresh($trip);
            $this->assertNotTerminal($locked);

            $attributes = $data->toAttributes();

            if ($attributes !== []) {
                $locked->update($attributes);
            }

            return $locked->refresh();
        });
    }

    /**
     * Mark the journey completed (arrived safely).
     */
    public function complete(Trip $trip, User $actor): Trip
    {
        return DB::transaction(function () use ($trip, $actor): Trip {
            $locked = $this->lockFresh($trip);
            $this->assertNotTerminal($locked);

            $locked->update([
                'status' => TripStatus::Completed,
                'completed_at' => now(),
            ]);

            event(new TripCompleted($locked, $actor->getKey(), ['status' => $locked->status->value]));

            return $locked;
        });
    }

    /**
     * Cancel the journey before completion.
     */
    public function cancel(Trip $trip, User $actor): Trip
    {
        return DB::transaction(function () use ($trip, $actor): Trip {
            $locked = $this->lockFresh($trip);
            $this->assertNotTerminal($locked);

            $locked->update([
                'status' => TripStatus::Cancelled,
                'cancelled_at' => now(),
            ]);

            event(new TripCancelled($locked, $actor->getKey(), ['status' => $locked->status->value]));

            return $locked;
        });
    }

    /**
     * Flag an active, past-due trip as overdue. Intended to be driven by a
     * scheduled sweep over Trip::overdueCandidates(). Idempotent under the lock:
     * returns the trip unchanged if it is no longer an active candidate.
     */
    public function markOverdue(Trip $trip): Trip
    {
        return DB::transaction(function () use ($trip): Trip {
            $locked = $this->lockFresh($trip);

            if ($locked->status !== TripStatus::Active) {
                return $locked;
            }

            $locked->update(['status' => TripStatus::Overdue]);

            event(new TripMarkedOverdue($locked, null, [
                'status' => $locked->status->value,
                'expected_arrival_at' => $locked->expected_arrival_at?->toIso8601String(),
            ]));

            return $locked;
        });
    }

    private function lockFresh(Trip $trip): Trip
    {
        return Trip::query()->whereKey($trip->getKey())->lockForUpdate()->firstOrFail();
    }

    private function assertNotTerminal(Trip $trip): void
    {
        if ($trip->status->isTerminal()) {
            throw ValidationException::withMessages([
                'status' => ["This trip is already {$trip->status->value} and can no longer change."],
            ]);
        }
    }
}
