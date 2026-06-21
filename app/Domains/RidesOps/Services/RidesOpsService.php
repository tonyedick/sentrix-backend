<?php

declare(strict_types=1);

namespace App\Domains\RidesOps\Services;

use App\Domains\Command\DTOs\RouteIncidentData;
use App\Domains\Command\Models\CommandIncident;
use App\Domains\Command\Services\CommandRoutingService;
use App\Domains\Command\Support\Enums\CommandIncidentSource;
use App\Domains\Command\Support\Enums\IncidentCategory;
use App\Domains\Command\Support\Enums\IncidentSeverity;
use App\Domains\DriverOnboarding\Models\Driver;
use App\Domains\DriverOnboarding\Support\Enums\DriverAvailability;
use App\Domains\DriverOnboarding\Support\Enums\DriverStage;
use App\Domains\RidesMarket\Models\Delivery;
use App\Domains\RidesMarket\Support\Enums\DeliveryStatus;
use App\Domains\Rides\Models\Ride;
use App\Domains\Rides\Services\RideService;
use App\Domains\Rides\Support\Enums\RideStatus;
use App\Domains\RidesOps\DTOs\ReassignData;
use App\Domains\RidesOps\DTOs\SurgeData;
use App\Domains\RidesOps\Events\DriverReinstated;
use App\Domains\RidesOps\Events\DriverSuspended;
use App\Domains\RidesOps\Events\RideEscalatedToHq;
use App\Domains\RidesOps\Events\RideForceCancelled;
use App\Domains\RidesOps\Events\RideReassigned;
use App\Domains\RidesOps\Events\SurgeChanged;
use App\Domains\RidesOps\Models\SurgeOverride;
use App\Models\User;
use Illuminate\Database\DatabaseManager;
use RuntimeException;

/**
 * Rides Ops — the platform/staff (SuperAdmin-gated) operations service.
 *
 * It READS the Rides, DriverOnboarding and RidesMarket domains to build the
 * ops dashboards, and applies a few operator overrides on top: force-cancel,
 * manual reassign, driver suspend/reinstate, manual surge, and escalate-to-HQ.
 * It owns ONE table (surge_overrides); everything else is read-only computed.
 *
 * Stateless final readonly service. Mutations run in DB::transaction() and
 * lockForUpdate() the ride/driver row before mutating. ALL MONEY IS INTEGER
 * CENTS (final_fare_cents etc.).
 */
final readonly class RidesOpsService
{
    /** Active ride statuses for the "live"/active KPIs. */
    private const ACTIVE_STATUSES = [
        RideStatus::Matched->value,
        RideStatus::Arriving->value,
        RideStatus::InProgress->value,
    ];

    /**
     * Zone bucketing: rides/drivers are grouped into rounded lat/lng cells of
     * this resolution (0.05 deg ~= 5.5km), so the demand/supply view is
     * deterministic without a real zone registry.
     */
    private const ZONE_PRECISION = 0.05;

    /** Bounded list size for the live map feed (drivers + active rides each). */
    private const LIVE_LIMIT = 100;

    public function __construct(
        private DatabaseManager $db,
        private RideService $rides,
        private CommandRoutingService $routing,
    ) {}

    // ---- Reads (computed dashboards) ---------------------------------------

    /**
     * Command-bar KPIs for the live ops board.
     *
     * @return array<string, mixed>
     */
    public function overview(): array
    {
        $statusCounts = Ride::query()
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $active = 0;
        foreach (self::ACTIVE_STATUSES as $status) {
            $active += (int) ($statusCounts[$status] ?? 0);
        }
        $scheduled = (int) ($statusCounts[RideStatus::Requested->value] ?? 0);
        $completed = (int) ($statusCounts[RideStatus::Completed->value] ?? 0);
        $cancelled = (int) ($statusCounts[RideStatus::Cancelled->value] ?? 0);

        $revenueCents = (int) Ride::query()
            ->where('status', RideStatus::Completed->value)
            ->sum('final_fare_cents');

        $finished = $completed + $cancelled;
        $cancelRate = $finished > 0 ? round(100 * $cancelled / $finished, 1) : 0.0;

        $driversOnline = (int) Driver::query()
            ->where('availability', DriverAvailability::Online->value)
            ->count();
        $driversOnTrip = (int) Driver::query()
            ->where('availability', DriverAvailability::OnTrip->value)
            ->count();

        $fleetTotal = (int) Driver::query()->count();
        $fleetActive = (int) Driver::query()
            ->where('stage', DriverStage::Active->value)
            ->count();

        $deliveriesActive = (int) Delivery::query()
            ->whereIn('status', [
                DeliveryStatus::Requested->value,
                DeliveryStatus::Matched->value,
                DeliveryStatus::InTransit->value,
            ])
            ->count();

        $surge = $this->currentSurge();

        return [
            'rides' => [
                'active' => $active,
                'scheduled' => $scheduled,
                'completed' => $completed,
                'cancelled' => $cancelled,
            ],
            'revenue_cents' => $revenueCents,
            'cancel_rate' => $cancelRate,
            'drivers' => [
                'online' => $driversOnline,
                'on_trip' => $driversOnTrip,
            ],
            'fleet' => [
                'total' => $fleetTotal,
                'active' => $fleetActive,
            ],
            'deliveries_active' => $deliveriesActive,
            'surge' => $surge,
        ];
    }

    /**
     * Driver onboarding funnel: counts grouped by Driver.stage (every stage
     * present, even at zero).
     *
     * @return array<string, mixed>
     */
    public function onboardingFunnel(): array
    {
        $counts = Driver::query()
            ->selectRaw('stage, count(*) as aggregate')
            ->groupBy('stage')
            ->pluck('aggregate', 'stage');

        $funnel = [];
        foreach (DriverStage::cases() as $stage) {
            $funnel[] = [
                'stage' => $stage->value,
                'count' => (int) ($counts[$stage->value] ?? 0),
            ];
        }

        return [
            'funnel' => $funnel,
            'total' => (int) Driver::query()->count(),
        ];
    }

    /**
     * Rides-by-hour, ride_class mix, revenue, current surge over a window.
     *
     * @return array<string, mixed>
     */
    public function analytics(int $days): array
    {
        $since = now()->subDays(max(1, $days));

        /** @var list<Ride> $rides */
        $rides = Ride::query()
            ->where('requested_at', '>=', $since)
            ->get(['ride_class', 'final_fare_cents', 'status', 'requested_at'])
            ->all();

        $byHour = array_fill(0, 24, 0);
        $classMix = [];
        foreach ($rides as $ride) {
            $hour = (int) $ride->requested_at?->hour;
            $byHour[$hour] = ($byHour[$hour] ?? 0) + 1;

            $class = $ride->ride_class->value;
            $classMix[$class] = ($classMix[$class] ?? 0) + 1;
        }

        $revenueCents = (int) Ride::query()
            ->where('status', RideStatus::Completed->value)
            ->where('requested_at', '>=', $since)
            ->sum('final_fare_cents');

        $mix = [];
        foreach ($classMix as $class => $count) {
            $mix[] = ['ride_class' => $class, 'count' => (int) $count];
        }

        return [
            'days' => max(1, $days),
            'rides_by_hour' => array_values($byHour),
            'class_mix' => $mix,
            'revenue_cents' => $revenueCents,
            'surge' => $this->currentSurge(),
        ];
    }

    /**
     * Operating zones derived by bucketing active rides into rounded lat/lng
     * cells (ZONE_PRECISION deg), counting active-ride demand per cell and the
     * available drivers per cell, with the current surge applied. Deterministic:
     * the same data always yields the same zones.
     *
     * @return array<string, mixed>
     */
    public function zones(): array
    {
        $surge = $this->currentSurge();

        /** @var list<Ride> $activeRides */
        $activeRides = Ride::query()
            ->whereIn('status', self::ACTIVE_STATUSES)
            ->get(['origin_lat', 'origin_lng'])
            ->all();

        /** @var list<Driver> $availableDrivers */
        $availableDrivers = Driver::query()
            ->whereIn('availability', [DriverAvailability::Online->value, DriverAvailability::OnTrip->value])
            ->get(['vehicle_make', 'vehicle_model'])
            ->all();

        $cells = [];
        foreach ($activeRides as $ride) {
            $key = $this->cellKey((float) $ride->origin_lat, (float) $ride->origin_lng);
            if (! isset($cells[$key])) {
                $cells[$key] = $this->newCell($key);
            }
            $cells[$key]['demand']++;
        }

        // Drivers carry no live coordinates in the onboarding model, so they are
        // pooled network-wide and reported as total available supply alongside
        // the per-cell demand (documented simplification).
        $supply = count($availableDrivers);

        $zones = [];
        foreach ($cells as $cell) {
            $zones[] = [
                'zone' => $cell['zone'],
                'lat' => $cell['lat'],
                'lng' => $cell['lng'],
                'demand' => $cell['demand'],
                'drivers_available' => $supply,
                'surge' => $surge,
            ];
        }

        return [
            'precision_deg' => self::ZONE_PRECISION,
            'drivers_available' => $supply,
            'surge' => $surge,
            'zones' => $zones,
        ];
    }

    /**
     * Live map feed: bounded active rides + available drivers.
     *
     * @return array<string, mixed>
     */
    public function live(): array
    {
        /** @var list<Ride> $rides */
        $rides = Ride::query()
            ->whereIn('status', self::ACTIVE_STATUSES)
            ->latest('requested_at')
            ->limit(self::LIVE_LIMIT)
            ->get(['id', 'status', 'driver_name', 'driver_lat', 'driver_lng', 'origin_lat', 'origin_lng', 'dest_lat', 'dest_lng'])
            ->all();

        $rideFeed = [];
        foreach ($rides as $ride) {
            $rideFeed[] = [
                'id' => $ride->getKey(),
                'status' => $ride->status->value,
                'driver_name' => $ride->driver_name,
                'driver_lat' => $ride->driver_lat !== null ? (float) $ride->driver_lat : null,
                'driver_lng' => $ride->driver_lng !== null ? (float) $ride->driver_lng : null,
                'origin_lat' => (float) $ride->origin_lat,
                'origin_lng' => (float) $ride->origin_lng,
                'dest_lat' => (float) $ride->dest_lat,
                'dest_lng' => (float) $ride->dest_lng,
            ];
        }

        /** @var list<Driver> $drivers */
        $drivers = Driver::query()
            ->whereIn('availability', [DriverAvailability::Online->value, DriverAvailability::OnTrip->value])
            ->limit(self::LIVE_LIMIT)
            ->get(['id', 'availability', 'vehicle_make', 'vehicle_model', 'vehicle_plate'])
            ->all();

        $driverFeed = [];
        foreach ($drivers as $driver) {
            $driverFeed[] = [
                'id' => $driver->getKey(),
                'availability' => $driver->availability->value,
                'vehicle' => trim((string) $driver->vehicle_make.' '.(string) $driver->vehicle_model),
                'plate' => $driver->vehicle_plate,
            ];
        }

        return [
            'rides' => $rideFeed,
            'drivers' => $driverFeed,
        ];
    }

    /**
     * The current manual surge: the latest active override's multiplier, or 1.0
     * when none is pinned.
     */
    public function currentSurge(): float
    {
        $override = $this->latestActiveOverride();

        return $override instanceof SurgeOverride ? (float) $override->multiplier : 1.0;
    }

    public function latestActiveOverride(): ?SurgeOverride
    {
        /** @var SurgeOverride|null $override */
        $override = SurgeOverride::query()
            ->where('active', true)
            ->latest('created_at')
            ->first();

        return $override;
    }

    // ---- Mutations ---------------------------------------------------------

    /**
     * Force-cancel a ride (safety/abuse). Reuses RideService::cancel when the
     * ride is still cancellable; otherwise it force-sets cancelled (ops override
     * can cancel a ride past the rider-cancellable window).
     */
    public function forceCancel(Ride $ride, ?User $actor, string $reason): Ride
    {
        return $this->db->transaction(function () use ($ride, $actor, $reason): Ride {
            /** @var Ride $locked */
            $locked = Ride::query()->whereKey($ride->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->status === RideStatus::Cancelled || $locked->status === RideStatus::Completed) {
                return $locked; // idempotent — already terminal
            }

            if ($locked->status->isCancellable()) {
                // Reuse the Rides domain's own cancel path so its invariants hold.
                $cancelled = $this->rides->cancel($locked, $reason);
            } else {
                // Past the rider-cancellable window: operator override.
                $locked->forceFill([
                    'status' => RideStatus::Cancelled->value,
                    'cancel_reason' => $reason,
                    'cancelled_at' => now(),
                ])->save();
                $cancelled = $locked->refresh();
            }

            event(new RideForceCancelled($cancelled, $actor?->getKey(), $reason));

            return $cancelled;
        });
    }

    /**
     * Manual dispatch override: overwrite the ride's denormalised driver snapshot
     * with the named driver. The real dispatch loop is SIMULATED — there is no
     * canonical driver pool yet, so we only update the snapshot fields the rider
     * app reads.
     */
    public function reassign(Ride $ride, ?User $actor, ReassignData $data): Ride
    {
        return $this->db->transaction(function () use ($ride, $actor, $data): Ride {
            /** @var Ride $locked */
            $locked = Ride::query()->whereKey($ride->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->status === RideStatus::Cancelled || $locked->status === RideStatus::Completed) {
                throw new RuntimeException('Ride is already closed.');
            }

            $name = $data->driverName ?? 'Ops Dispatch';
            $locked->forceFill(['driver_name' => $name])->save();
            $locked->refresh();

            event(new RideReassigned($locked, $actor?->getKey(), $name));

            return $locked;
        });
    }

    /**
     * Suspend a driver: stage=suspended + availability offline.
     */
    public function suspendDriver(Driver $driver, ?User $actor, string $reason): Driver
    {
        return $this->db->transaction(function () use ($driver, $actor, $reason): Driver {
            /** @var Driver $locked */
            $locked = Driver::query()->whereKey($driver->getKey())->lockForUpdate()->firstOrFail();

            $locked->forceFill([
                'stage' => DriverStage::Suspended->value,
                'availability' => DriverAvailability::Offline->value,
            ])->save();
            $locked->refresh();

            event(new DriverSuspended($locked, $actor?->getKey(), $reason));

            return $locked;
        });
    }

    /**
     * Reinstate a suspended driver back to stage=active.
     */
    public function reinstateDriver(Driver $driver, ?User $actor): Driver
    {
        return $this->db->transaction(function () use ($driver, $actor): Driver {
            /** @var Driver $locked */
            $locked = Driver::query()->whereKey($driver->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->stage !== DriverStage::Suspended) {
                throw new RuntimeException('Driver is not suspended.');
            }

            $locked->forceFill(['stage' => DriverStage::Active->value])->save();
            $locked->refresh();

            event(new DriverReinstated($locked, $actor?->getKey()));

            return $locked;
        });
    }

    /**
     * Pin or release the manual surge. PIN deactivates all prior active rows and
     * inserts a new active row; RELEASE just deactivates all active rows.
     *
     * @return array{multiplier: float, pinned: bool, zone: string|null}
     */
    public function setSurge(SurgeData $data, ?User $actor): array
    {
        return $this->db->transaction(function () use ($data, $actor): array {
            // Deactivate any currently-active overrides (lock first).
            SurgeOverride::query()
                ->where('active', true)
                ->lockForUpdate()
                ->update(['active' => false]);

            if ($data->release) {
                $result = ['multiplier' => 1.0, 'pinned' => false, 'zone' => null];
                event(new SurgeChanged(1.0, false, null, $actor?->getKey()));

                return $result;
            }

            $multiplier = max(1.0, min(3.0, (float) ($data->multiplier ?? 1.0)));

            SurgeOverride::create([
                'zone' => $data->zone,
                'multiplier' => $multiplier,
                'active' => true,
                'set_by' => $actor?->getKey(),
                'note' => $data->note,
                'created_at' => now(),
            ]);

            event(new SurgeChanged($multiplier, true, $data->zone, $actor?->getKey()));

            return ['multiplier' => $multiplier, 'pinned' => true, 'zone' => $data->zone];
        });
    }

    /**
     * Escalate a ride to HQ National Command. Reuses the Command domain's own
     * routing path (CommandRoutingService::route) so the incident is categorised,
     * routed to the lead agency's nearest command, and opened with SLA clocks +
     * the CommandIncidentRouted event — Rides Ops never bypasses those invariants.
     *
     * Returns null only when the country has no responder structure yet (no
     * agency leads the category and no crime fallback / command exists).
     */
    public function escalate(Ride $ride, ?User $actor): ?CommandIncident
    {
        $summary = sprintf(
            'Safe Rides escalation: ride %s (%s to %s)',
            $ride->getKey(),
            (string) $ride->origin_label,
            (string) $ride->dest_label,
        );

        $incident = $this->routing->route(new RouteIncidentData(
            severity: IncidentSeverity::High,
            summary: $summary,
            category: IncidentCategory::Crime->value,
            country: 'NG',
            lat: $ride->origin_lat !== null ? (float) $ride->origin_lat : null,
            lng: $ride->origin_lng !== null ? (float) $ride->origin_lng : null,
            sourceType: CommandIncidentSource::Sos,
            sourceRef: (string) $ride->getKey(),
        ));

        if (! $incident instanceof CommandIncident) {
            return null;
        }

        event(new RideEscalatedToHq($ride, $incident, $actor?->getKey()));

        return $incident;
    }

    // ---- Helpers -----------------------------------------------------------

    private function cellKey(float $lat, float $lng): string
    {
        $rl = round($lat / self::ZONE_PRECISION) * self::ZONE_PRECISION;
        $rn = round($lng / self::ZONE_PRECISION) * self::ZONE_PRECISION;

        return number_format($rl, 2, '.', '').','.number_format($rn, 2, '.', '');
    }

    /**
     * @return array{zone: string, lat: float, lng: float, demand: int}
     */
    private function newCell(string $key): array
    {
        [$lat, $lng] = array_map('floatval', explode(',', $key));

        return ['zone' => $key, 'lat' => $lat, 'lng' => $lng, 'demand' => 0];
    }
}
