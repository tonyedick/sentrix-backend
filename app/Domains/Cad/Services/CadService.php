<?php

declare(strict_types=1);

namespace App\Domains\Cad\Services;

use App\Domains\Cad\DTOs\CreateBoloData;
use App\Domains\Cad\DTOs\CreateUnitData;
use App\Domains\Cad\DTOs\UpdateUnitData;
use App\Domains\Cad\Events\BoloIssued;
use App\Domains\Cad\Events\UnitDispatched;
use App\Domains\Cad\Models\Bolo;
use App\Domains\Cad\Models\Unit;
use App\Domains\Cad\Models\UnitDispatch;
use App\Domains\Cad\Support\Enums\BoloStatus;
use App\Domains\Cad\Support\Enums\UnitKind;
use App\Domains\Cad\Support\Enums\UnitStatus;
use App\Domains\Command\Models\Command;
use App\Domains\Command\Models\CommandIncident;
use App\Domains\Command\Services\CommandService;
use App\Domains\Command\Support\Enums\CommandIncidentStatus;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/**
 * Computer-Aided Dispatch: field units (AVL), closest-unit recommendation,
 * unit dispatch with status interlock, and BOLO broadcasts.
 *
 * Stateless final readonly service. Writes run in a transaction; the dispatch
 * path locks + re-reads the unit before mutating (concurrency-safe). Mirrors
 * Omni's lib/cad.js (createUnit, updateUnit, closestUnits, assignUnit, BOLOs).
 */
final readonly class CadService
{
    /** Earth radius in km (haversine). */
    private const EARTH_RADIUS_KM = 6371.0;

    public function __construct(
        private CommandService $commands,
    ) {}

    public function createUnit(CreateUnitData $data): Unit
    {
        return DB::transaction(function () use ($data): Unit {
            // Derive the agency from the command (denormalized for fast filters).
            /** @var Command $command */
            $command = Command::query()->whereKey($data->commandId)->firstOrFail();

            return Unit::create([
                'command_id' => $command->getKey(),
                'agency_id' => $command->agency_id,
                'call_sign' => $data->callSign,
                'kind' => $data->kind,
                'capabilities' => $data->capabilities,
                'crew' => $data->crew,
                'status' => $data->status,
                'lat' => $data->lat,
                'lng' => $data->lng,
                'area' => $data->area ?? $command->area,
                'assigned_incident_id' => null,
            ]);
        });
    }

    /**
     * Partial update of a unit (AVL / status / capabilities / area). Locks +
     * re-reads the row (concurrency-safe), then applies only the present fields.
     */
    public function updateUnit(Unit $unit, UpdateUnitData $data): Unit
    {
        return DB::transaction(function () use ($unit, $data): Unit {
            /** @var Unit $locked */
            $locked = Unit::query()->whereKey($unit->getKey())->lockForUpdate()->firstOrFail();

            if ($data->callSign !== null) {
                $locked->call_sign = $data->callSign;
            }
            if ($data->kind !== null) {
                $locked->kind = $data->kind;
            }
            if ($data->hasCapabilities) {
                $locked->capabilities = $data->capabilities ?? [];
            }
            if ($data->crew !== null) {
                $locked->crew = $data->crew;
            }
            if ($data->status !== null) {
                $locked->status = $data->status;
                // Going off-call clears the incident link (mirrors lib/cad.js).
                if (! $data->status->isOnCall()) {
                    $locked->assigned_incident_id = null;
                }
            }
            if ($data->hasLat) {
                $locked->lat = $data->lat;
            }
            if ($data->hasLng) {
                $locked->lng = $data->lng;
            }
            if ($data->hasArea) {
                $locked->area = $data->area;
            }

            $locked->save();

            return $locked;
        });
    }

    /**
     * Recommend the best units for an incident: available units of the incident's
     * agency, scored by kind-suitability + haversine distance (closest first).
     * Mirrors closestUnits() in lib/cad.js. Read-only (no state change, no event).
     *
     * @return list<array{unit: Unit, kind_match: bool, distance_km: float|null, score: float}>
     */
    public function closestUnits(CommandIncident $incident, int $limit = 5): array
    {
        $limit = max(1, min(20, $limit));
        $wantKinds = UnitKind::forCategory($incident->category);
        $incLat = $incident->lat;
        $incLng = $incident->lng;
        $haystack = strtolower((string) $incident->summary);

        /** @var \Illuminate\Support\Collection<int, Unit> $units */
        $units = Unit::query()
            ->where('agency_id', $incident->agency_id)
            ->where('status', UnitStatus::Available->value)
            ->get();

        $scored = $units
            ->map(function (Unit $unit) use ($wantKinds, $incLat, $incLng, $haystack): array {
                $kindMatch = in_array($unit->kind->value, $wantKinds, true);
                $distanceKm = null;
                $score = 0.0;

                if ($incLat !== null && $incLng !== null && $unit->lat !== null && $unit->lng !== null) {
                    $distanceKm = $this->haversine($incLat, $incLng, $unit->lat, $unit->lng);
                    $score = 1000.0 - min(999.0, $distanceKm);
                } elseif ($unit->area !== null && $unit->area !== '' && str_contains($haystack, strtolower($unit->area))) {
                    $score = 600.0;
                } else {
                    $score = 100.0;
                }

                if ($kindMatch) {
                    $score += 500.0; // right tool for the job
                }

                return [
                    'unit' => $unit,
                    'kind_match' => $kindMatch,
                    'distance_km' => $distanceKm === null ? null : round($distanceKm, 1),
                    'score' => $score,
                ];
            })
            ->sortByDesc('score')
            ->take($limit)
            ->values()
            ->all();

        /** @var list<array{unit: Unit, kind_match: bool, distance_km: float|null, score: float}> $scored */
        return $scored;
    }

    /**
     * Dispatch (assign) a unit to a command incident. Concurrency-safe: the unit
     * is locked + re-read inside the transaction. Requires the unit to be
     * available and to belong to the incident's agency (else 422). Creates the
     * assignment record, links the unit, and advances the incident to at least
     * 'acknowledged' (reusing CommandService::act). Mirrors assignUnit() in cad.js.
     */
    public function dispatch(CommandIncident $incident, string $unitId, ?string $actorId = null): UnitDispatch
    {
        return DB::transaction(function () use ($incident, $unitId, $actorId): UnitDispatch {
            /** @var Unit $unit */
            $unit = Unit::query()->whereKey($unitId)->lockForUpdate()->firstOrFail();

            abort_unless(
                $unit->status === UnitStatus::Available,
                Response::HTTP_UNPROCESSABLE_ENTITY,
                'unit_not_available'
            );

            abort_unless(
                $unit->agency_id === $incident->agency_id,
                Response::HTTP_UNPROCESSABLE_ENTITY,
                'cross_agency'
            );

            $unit->status = UnitStatus::Assigned;
            $unit->assigned_incident_id = $incident->getKey();
            $unit->save();

            $dispatch = UnitDispatch::create([
                'unit_id' => $unit->getKey(),
                'command_incident_id' => $incident->getKey(),
                'dispatched_by' => $actorId,
                'dispatched_at' => now(),
            ]);

            // Assignment dispatches the incident: stamp it to at least
            // 'acknowledged' if it is still 'new'. Reuse the Command domain's
            // validated transition (CommandService::act) so the lifecycle stays
            // authoritative there; only act while still 'new'.
            $incident->refresh();
            if ($incident->status === CommandIncidentStatus::New) {
                $this->commands->act($incident, 'acknowledge', $actorId);
            }

            event(new UnitDispatched($dispatch, $actorId));

            return $dispatch;
        });
    }

    public function issueBolo(CreateBoloData $data, ?string $actorId = null): Bolo
    {
        return DB::transaction(function () use ($data, $actorId): Bolo {
            // Derive the agency from the issuing command.
            /** @var Command $command */
            $command = Command::query()->whereKey($data->commandId)->firstOrFail();

            $bolo = Bolo::create([
                'agency_id' => $command->agency_id,
                'command_id' => $command->getKey(),
                'kind' => $data->kind,
                'subject' => $data->subject,
                'details' => $data->details,
                'status' => BoloStatus::Active,
                'issued_by' => $actorId,
                'issued_at' => now(),
            ]);

            event(new BoloIssued($bolo, $actorId));

            return $bolo;
        });
    }

    /**
     * Clear a BOLO (idempotent). Locks + re-reads; a cleared BOLO is returned
     * unchanged.
     */
    public function clearBolo(Bolo $bolo): Bolo
    {
        return DB::transaction(function () use ($bolo): Bolo {
            /** @var Bolo $locked */
            $locked = Bolo::query()->whereKey($bolo->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->status === BoloStatus::Cleared) {
                return $locked; // idempotent
            }

            $locked->status = BoloStatus::Cleared;
            $locked->cleared_at = now();
            $locked->save();

            return $locked;
        });
    }

    /**
     * Great-circle distance between two lat/lng pairs in km (haversine).
     */
    private function haversine(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $toRad = static fn (float $deg): float => $deg * M_PI / 180.0;
        $dLat = $toRad($lat2 - $lat1);
        $dLng = $toRad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2
            + cos($toRad($lat1)) * cos($toRad($lat2)) * sin($dLng / 2) ** 2;

        return 2 * self::EARTH_RADIUS_KM * asin(min(1.0, sqrt($a)));
    }
}
