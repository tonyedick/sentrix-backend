<?php

declare(strict_types=1);

namespace App\Domains\Rides\Services;

use App\Domains\Rides\DTOs\QuoteData;
use App\Domains\Rides\DTOs\RequestRideData;
use App\Domains\Rides\Events\RideCompleted;
use App\Domains\Rides\Events\RideEvidenceBanked;
use App\Domains\Rides\Events\RideRequested;
use App\Domains\Rides\Events\RideSafetyArmed;
use App\Domains\Rides\Events\RideSosTriggered;
use App\Domains\Rides\Models\Ride;
use App\Domains\Rides\Models\RideEvidence;
use App\Domains\Rides\Models\RideSafety;
use App\Domains\Rides\Support\Enums\EvidenceKind;
use App\Domains\Rides\Support\Enums\RideClass;
use App\Domains\Rides\Support\Enums\RideStatus;
use App\Models\User;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Ride booking/lifecycle + in-ride safety. User-scoped (ADR-0001): every ride
 * belongs to the authenticated rider. Mirrors the SentrixGo rides router, adapted
 * to Laravel + cents. The driver match is SIMULATED from a small deterministic
 * mock pool — the real driver pool (KYC, dispatch) integrates with the later
 * Driver domain.
 */
final readonly class RideService
{
    // go_safe base fare components in NGN cents (kobo). Comfort/XL scale by class.
    private const BASE_FARE_CENTS = 60000;     // flat pickup fee (₦600)
    private const PER_KM_CENTS = 20000;        // ₦200 / km
    private const BOOKING_FEE_CENTS = 10000;   // ₦100 platform fee
    private const MIN_FARE_CENTS = 80000;      // ₦800 floor

    // Simulated, deterministic verified-driver pool (real pool comes later).
    private const DRIVER_POOL = [
        ['id' => 'sd-4f2a9c', 'name' => 'Emeka U.', 'plate' => 'KJA-482-AB'],
        ['id' => 'sd-9b7e11', 'name' => 'Bola A.', 'plate' => 'LSD-119-CC'],
        ['id' => 'sd-1c3d55', 'name' => 'Sani M.', 'plate' => 'ABC-903-DE'],
    ];

    public function __construct(private DatabaseManager $db) {}

    // ---- Quote (no persistence) --------------------------------------------

    /**
     * Build per-class fare options for a leg, applying the time-of-day surge.
     *
     * @return array{
     *     distance_km: float,
     *     surge_multiplier: float,
     *     currency: string,
     *     options: list<array{ride_class:string,label:string,fare_cents:int,surge:float}>
     * }
     */
    public function quote(QuoteData $data): array
    {
        $distanceKm = $this->distanceKm($data->originLat, $data->originLng, $data->destLat, $data->destLng);
        $surge = $this->surgeNow();

        $options = [];
        foreach (RideClass::cases() as $class) {
            $options[] = [
                'ride_class' => $class->value,
                'label' => $class->label(),
                'fare_cents' => $this->fareCents($class, $distanceKm, $surge),
                'surge' => $surge,
            ];
        }

        return [
            'distance_km' => round($distanceKm, 2),
            'surge_multiplier' => $surge,
            'currency' => 'NGN',
            'options' => $options,
        ];
    }

    // ---- Request (create + simulate match + safety row) --------------------

    public function request(User $rider, RequestRideData $data): Ride
    {
        return $this->db->transaction(function () use ($rider, $data): Ride {
            $distanceKm = $this->distanceKm($data->originLat, $data->originLng, $data->destLat, $data->destLng);
            $surge = $this->surgeNow();
            $fareCents = $this->fareCents($data->rideClass, $distanceKm, $surge);

            $driver = $this->matchDriver($rider, $data);

            $ride = Ride::create([
                'user_id' => $rider->getKey(),
                'ride_class' => $data->rideClass->value,
                'status' => RideStatus::Matched->value,
                'origin_label' => $data->originLabel,
                'origin_lat' => $data->originLat,
                'origin_lng' => $data->originLng,
                'dest_label' => $data->destLabel,
                'dest_lat' => $data->destLat,
                'dest_lng' => $data->destLng,
                'distance_km' => round($distanceKm, 2),
                'fare_estimate_cents' => $fareCents,
                'tip_cents' => 0,
                'currency' => 'NGN',
                'surge_multiplier' => $surge,
                'payment_method' => $data->paymentMethod->value,
                'match_code' => $this->matchCode(),
                'driver_id' => $driver['driver_id'],
                'driver_name' => $driver['driver_name'],
                'driver_plate' => $driver['driver_plate'],
                'driver_lat' => $driver['driver_lat'],
                'driver_lng' => $driver['driver_lng'],
                'driver_eta_minutes' => $driver['driver_eta_minutes'],
                'driver_speed_kph' => 0,
                'requested_at' => now(),
            ]);

            RideSafety::create(['ride_id' => $ride->getKey()]);

            event(new RideRequested($ride));

            return $ride->refresh();
        });
    }

    // ---- Track (simulate progress toward destination) ----------------------

    /**
     * Live driver snapshot. We nudge the driver position deterministically toward
     * the destination based on seconds elapsed since the match, and ease the ETA
     * down, so a polling client sees believable progress without a dispatch loop.
     *
     * @return array<string, mixed>
     */
    public function track(Ride $ride): array
    {
        // Fraction of a notional 5-minute trip elapsed since the match.
        $elapsed = (int) Carbon::now()->diffInSeconds($ride->requested_at, true);
        $fraction = max(0.0, min(1.0, $elapsed / 300.0));

        $lat = (float) $ride->driver_lat + (((float) $ride->dest_lat - (float) $ride->driver_lat) * $fraction);
        $lng = (float) $ride->driver_lng + (((float) $ride->dest_lng - (float) $ride->driver_lng) * $fraction);

        $baseEta = $ride->driver_eta_minutes ?? 5;
        $eta = (int) max(0, (int) round($baseEta * (1.0 - $fraction)));
        // Believable urban cruise: 0 before pickup, ~38 kph mid-trip.
        $speed = $fraction <= 0.0 ? 0 : (int) round(38 + ($fraction * 6));

        return [
            'ride_id' => $ride->getKey(),
            'status' => $ride->status->value,
            'driver_lat' => round($lat, 7),
            'driver_lng' => round($lng, 7),
            'driver_eta_minutes' => $eta,
            'driver_speed_kph' => $speed,
            'progress' => round($fraction, 2),
        ];
    }

    // ---- Cancel / Complete -------------------------------------------------

    public function cancel(Ride $ride, string $reason): Ride
    {
        return $this->db->transaction(function () use ($ride, $reason): Ride {
            /** @var Ride $locked */
            $locked = Ride::query()->whereKey($ride->getKey())->lockForUpdate()->firstOrFail();

            if (! $locked->status->isCancellable()) {
                throw ValidationException::withMessages([
                    'status' => ['This ride can no longer be cancelled.'],
                ]);
            }

            $locked->forceFill([
                'status' => RideStatus::Cancelled->value,
                'cancel_reason' => $reason,
                'cancelled_at' => now(),
            ])->save();

            return $locked->refresh();
        });
    }

    public function complete(Ride $ride, ?int $rating, int $tipCents): Ride
    {
        return $this->db->transaction(function () use ($ride, $rating, $tipCents): Ride {
            /** @var Ride $locked */
            $locked = Ride::query()->whereKey($ride->getKey())->lockForUpdate()->firstOrFail();

            if (! $locked->status->isCompletable()) {
                throw ValidationException::withMessages([
                    'status' => ['This ride cannot be completed from its current state.'],
                ]);
            }

            $locked->forceFill([
                'status' => RideStatus::Completed->value,
                'final_fare_cents' => $locked->fare_estimate_cents + $tipCents,
                'tip_cents' => $tipCents,
                'rating' => $rating,
                'completed_at' => now(),
            ])->save();

            $locked->refresh();

            event(new RideCompleted($locked));

            return $locked;
        });
    }

    /**
     * Fare breakdown for the receipt (computed; no persistence).
     *
     * @return array<string, mixed>
     */
    public function receipt(Ride $ride): array
    {
        $surge = (float) $ride->surge_multiplier;
        $distanceKm = (float) $ride->distance_km;

        // Reverse the components from the stored estimate so base + surge + tip
        // always reconcile to the (final) total the rider paid.
        $mult = $ride->ride_class->fareMultiplier();
        $base = (int) round(self::BASE_FARE_CENTS * $mult);
        $distance = (int) round(self::PER_KM_CENTS * $distanceKm * $mult);
        $booking = self::BOOKING_FEE_CENTS;
        $tip = $ride->tip_cents;
        $fare = $ride->final_fare_cents ?? $ride->fare_estimate_cents;

        return [
            'ride_id' => $ride->getKey(),
            'currency' => $ride->currency,
            'base_cents' => $base,
            'distance_cents' => $distance,
            'booking_cents' => $booking,
            'surge_multiplier' => $surge,
            'tip_cents' => $tip,
            'fare_cents' => $fare,
            'total_cents' => $fare,
        ];
    }

    // ---- In-ride safety ----------------------------------------------------

    public function arm(Ride $ride): RideSafety
    {
        return $this->db->transaction(function () use ($ride): RideSafety {
            $safety = $this->safetyFor($ride);
            $safety->forceFill([
                'armed' => true,
                'recording' => true,
                'guardians_notified' => true,
            ])->save();

            event(new RideSafetyArmed($ride));

            return $safety->refresh();
        });
    }

    public function sos(Ride $ride): RideSafety
    {
        return $this->db->transaction(function () use ($ride): RideSafety {
            $safety = $this->safetyFor($ride);
            $safety->forceFill([
                'recording' => true,
                'guardians_notified' => true,
            ])->save();

            event(RideSosTriggered::fromRide($ride));

            return $safety->refresh();
        });
    }

    public function bankEvidence(Ride $ride, EvidenceKind $kind, string $url): RideEvidence
    {
        return $this->db->transaction(function () use ($ride, $kind, $url): RideEvidence {
            $evidence = RideEvidence::create([
                'ride_id' => $ride->getKey(),
                'kind' => $kind->value,
                'url' => $url,
                'recorded_at' => now(),
            ]);

            $safety = $this->safetyFor($ride);
            $safety->increment('evidence_count');

            event(new RideEvidenceBanked($evidence));

            return $evidence;
        });
    }

    /**
     * Rider responds to a wellness check. ok=true clears the prompt. ok=false
     * escalates straight to SOS (mirrors safety.py: "Not okay" → SOS) AND records
     * the off_route/overdue trouble flags so a later Emergency listener has context.
     */
    public function checkIn(Ride $ride, bool $ok): RideSafety
    {
        return $this->db->transaction(function () use ($ride, $ok): RideSafety {
            $safety = $this->safetyFor($ride);

            if ($ok) {
                $safety->forceFill(['check_in_due' => false])->save();

                return $safety->refresh();
            }

            // Escalate: flag trouble + trigger the same ride-aware SOS path.
            $safety->forceFill([
                'check_in_due' => false,
                'off_route' => true,
                'overdue' => true,
                'recording' => true,
                'guardians_notified' => true,
            ])->save();

            event(RideSosTriggered::fromRide($ride));

            return $safety->refresh();
        });
    }

    public function safetyFor(Ride $ride): RideSafety
    {
        return RideSafety::query()->firstOrCreate(['ride_id' => $ride->getKey()]);
    }

    // ---- Fare engine + helpers ---------------------------------------------

    /**
     * Per-class fare in cents: (base + per_km·km + booking)·class·surge, floored.
     */
    private function fareCents(RideClass $class, float $distanceKm, float $surge): int
    {
        $mult = $class->fareMultiplier();
        $subtotal = (self::BASE_FARE_CENTS * $mult)
            + (self::PER_KM_CENTS * max(0.0, $distanceKm) * $mult)
            + self::BOOKING_FEE_CENTS;

        $total = (int) round($subtotal * max(1.0, $surge));

        return max($total, self::MIN_FARE_CENTS);
    }

    /**
     * Time-of-day surge: peak commute (7-8 & 17-19) → 1.3; shoulders → 1.15; else 1.
     */
    private function surgeNow(): float
    {
        $hour = (int) Carbon::now()->hour;

        if (in_array($hour, [7, 8, 17, 18, 19], true)) {
            return 1.3;
        }

        if (in_array($hour, [6, 9, 16, 20], true)) {
            return 1.15;
        }

        return 1.0;
    }

    /**
     * Great-circle distance in km, padded ~1.3x for road distance and floored at
     * 1km so a tap-same-point still costs (mirrors the SentrixGo quote engine).
     */
    private function distanceKm(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earth = 6371.0;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = (sin($dLat / 2) ** 2)
            + (cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * (sin($dLng / 2) ** 2));
        $straight = $earth * 2 * atan2(sqrt($a), sqrt(1 - $a));

        return max(1.0, $straight * 1.3);
    }

    /**
     * Simulated deterministic match: pick a pool driver by hashing the rider id,
     * and seed the driver near the pickup. Real KYC-verified dispatch lands with
     * the Driver domain.
     *
     * @return array<string, mixed>
     */
    private function matchDriver(User $rider, RequestRideData $data): array
    {
        $index = abs(crc32((string) $rider->getKey())) % count(self::DRIVER_POOL);
        $driver = self::DRIVER_POOL[$index];

        return [
            'driver_id' => $driver['id'],
            'driver_name' => $driver['name'],
            'driver_plate' => $driver['plate'],
            // Seed the driver a short hop from the pickup.
            'driver_lat' => round($data->originLat + 0.004, 7),
            'driver_lng' => round($data->originLng + 0.004, 7),
            'driver_eta_minutes' => 4 + $index,
        ];
    }

    private function matchCode(): string
    {
        return Str::padLeft((string) random_int(0, 9999), 4, '0');
    }

    /**
     * @return list<string>
     */
    public function cancelReasons(): array
    {
        return [
            'Driver is too far',
            'Waiting too long',
            'Booked by mistake',
            'Driver asked to cancel',
            'Found another ride',
            'Other',
        ];
    }
}
