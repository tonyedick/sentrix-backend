<?php

declare(strict_types=1);

namespace App\Domains\Cad\Http\Controllers;

use App\Domains\Cad\DTOs\CreateBoloData;
use App\Domains\Cad\DTOs\CreateUnitData;
use App\Domains\Cad\DTOs\UpdateUnitData;
use App\Domains\Cad\Http\Requests\CreateBoloRequest;
use App\Domains\Cad\Http\Requests\CreateUnitRequest;
use App\Domains\Cad\Http\Requests\DispatchUnitRequest;
use App\Domains\Cad\Http\Requests\UpdateUnitRequest;
use App\Domains\Cad\Http\Resources\BoloResource;
use App\Domains\Cad\Http\Resources\UnitDispatchResource;
use App\Domains\Cad\Http\Resources\UnitResource;
use App\Domains\Cad\Models\Bolo;
use App\Domains\Cad\Models\Unit;
use App\Domains\Cad\Services\CadService;
use App\Domains\Cad\Support\Enums\BoloStatus;
use App\Domains\Command\Models\CommandIncident;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sentrix CAD (Computer-Aided Dispatch) HTTP surface — units / AVL / closest-unit
 * dispatch / BOLOs.
 *
 * PLATFORM-scoped (NOT organization-scoped): the national agency/command layer is
 * cross-tenant. All actions are gated on SuperAdmin here and in the write Form
 * Requests.
 *
 * TODO: accept command roles (dispatch_coordinator/monitor) once a platform-staff
 * RBAC layer exists.
 */
final class CadController extends Controller
{
    public function __construct(private readonly CadService $cad) {}

    public function units(Request $request): AnonymousResourceCollection
    {
        $this->assertSuperAdmin($request);

        $units = Unit::query()
            ->when($request->filled('command_id'), fn ($q) => $q->where('command_id', $request->string('command_id')->value()))
            ->when($request->filled('agency_id'), fn ($q) => $q->where('agency_id', $request->string('agency_id')->value()))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')->value()))
            ->latest('created_at')
            ->paginate($this->perPage($request));

        return UnitResource::collection($units);
    }

    public function createUnit(CreateUnitRequest $request): JsonResponse
    {
        // Ability enforced in the Form Request's authorize() (SuperAdmin).
        $unit = $this->cad->createUnit(CreateUnitData::fromRequest($request));

        return UnitResource::make($unit)
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function updateUnit(UpdateUnitRequest $request, Unit $unit): UnitResource
    {
        $updated = $this->cad->updateUnit($unit, UpdateUnitData::fromRequest($request));

        return UnitResource::make($updated);
    }

    /**
     * Recommend the best units for an incident. Read-only computed snapshot — no
     * state change, no event, no Resource: an ability check then a plain `data`
     * array (each candidate carries distance_km).
     */
    public function closestUnits(Request $request, CommandIncident $commandIncident): JsonResponse
    {
        $this->assertSuperAdmin($request);

        $limit = $request->filled('limit') ? $request->integer('limit') : 5;
        $candidates = $this->cad->closestUnits($commandIncident, $limit);

        return response()->json([
            'data' => array_map(static fn (array $row): array => [
                'unit' => UnitResource::make($row['unit'])->resolve($request),
                'kind_match' => $row['kind_match'],
                'distance_km' => $row['distance_km'],
                'score' => round($row['score'], 1),
            ], $candidates),
        ]);
    }

    public function dispatch(DispatchUnitRequest $request, CommandIncident $commandIncident): JsonResponse
    {
        $dispatch = $this->cad->dispatch(
            $commandIncident,
            $request->string('unit_id')->value(),
            $request->user()?->getKey(),
        );

        return UnitDispatchResource::make($dispatch)
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function bolos(Request $request): AnonymousResourceCollection
    {
        $this->assertSuperAdmin($request);

        $status = $request->filled('status')
            ? $request->string('status')->value()
            : BoloStatus::Active->value;

        $bolos = Bolo::query()
            ->when($request->filled('agency_id'), fn ($q) => $q->where('agency_id', $request->string('agency_id')->value()))
            ->when($request->filled('command_id'), fn ($q) => $q->where('command_id', $request->string('command_id')->value()))
            ->where('status', $status)
            ->latest('issued_at')
            ->paginate($this->perPage($request));

        return BoloResource::collection($bolos);
    }

    public function createBolo(CreateBoloRequest $request): JsonResponse
    {
        $bolo = $this->cad->issueBolo(
            CreateBoloData::fromRequest($request),
            $request->user()?->getKey(),
        );

        return BoloResource::make($bolo)
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function clearBolo(Request $request, Bolo $bolo): BoloResource
    {
        $this->assertSuperAdmin($request);

        return BoloResource::make($this->cad->clearBolo($bolo));
    }

    private function assertSuperAdmin(Request $request): void
    {
        abort_unless((bool) $request->user()?->isSuperAdmin(), Response::HTTP_FORBIDDEN);
    }
}
