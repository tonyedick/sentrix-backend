<?php

declare(strict_types=1);

namespace App\Domains\Command\Http\Controllers;

use App\Domains\Command\DTOs\CreateAgencyData;
use App\Domains\Command\DTOs\CreateCommandData;
use App\Domains\Command\DTOs\RouteIncidentData;
use App\Domains\Command\Http\Requests\ActOnIncidentRequest;
use App\Domains\Command\Http\Requests\CreateAgencyRequest;
use App\Domains\Command\Http\Requests\CreateCommandRequest;
use App\Domains\Command\Http\Requests\RouteIncidentRequest;
use App\Domains\Command\Http\Resources\AgencyResource;
use App\Domains\Command\Http\Resources\CommandIncidentResource;
use App\Domains\Command\Http\Resources\CommandResource;
use App\Domains\Command\Models\Agency;
use App\Domains\Command\Models\Command;
use App\Domains\Command\Models\CommandIncident;
use App\Domains\Command\Services\CommandRoutingService;
use App\Domains\Command\Services\CommandService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sentrix Responder Command HTTP surface.
 *
 * PLATFORM-scoped (NOT organization-scoped): the national agency/command layer
 * is cross-tenant. All actions are gated on SuperAdmin here and in the write
 * Form Requests.
 *
 * TODO: accept command roles (dispatch_coordinator/monitor) once a platform-staff
 * RBAC layer exists.
 */
final class CommandController extends Controller
{
    public function __construct(
        private readonly CommandService $commands,
        private readonly CommandRoutingService $routing,
    ) {}

    public function overview(Request $request): JsonResponse
    {
        $this->assertSuperAdmin($request);

        return response()->json(['data' => $this->commands->overview()]);
    }

    public function agencies(Request $request): AnonymousResourceCollection
    {
        $this->assertSuperAdmin($request);

        $agencies = Agency::query()
            ->when($request->filled('country'), fn ($q) => $q->where('country', strtoupper($request->string('country')->value())))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')->value()))
            ->latest('created_at')
            ->paginate($this->perPage($request));

        return AgencyResource::collection($agencies);
    }

    public function createAgency(CreateAgencyRequest $request): JsonResponse
    {
        // Ability enforced in the Form Request's authorize() (SuperAdmin).
        $agency = $this->commands->createAgency(CreateAgencyData::fromRequest($request));

        return AgencyResource::make($agency)
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * Resolve a Sentrix AI responder key (npf/frsc/ffs/…) to an onboarded
     * agency. Lookup is by CODE only — never the uuid id column — so a non-uuid
     * key can never trigger Postgres' "invalid input syntax for type uuid".
     */
    public function resolveAgency(Request $request): JsonResponse
    {
        $this->assertSuperAdmin($request);

        $key = $request->string('key')->value();
        $country = $request->filled('country')
            ? $request->string('country')->value()
            : 'NG';

        $result = $this->commands->resolveAgencyKey($key, $country);

        return response()->json([
            'data' => [
                'matched' => $result['matched'],
                'key' => $result['key'],
                'label' => $result['label'],
                'agency' => $result['agency'] instanceof Agency
                    ? AgencyResource::make($result['agency'])
                    : null,
            ],
        ]);
    }

    public function commands(Request $request): AnonymousResourceCollection
    {
        $this->assertSuperAdmin($request);

        $commands = Command::query()
            ->when($request->filled('agency_id'), fn ($q) => $q->where('agency_id', $request->string('agency_id')->value()))
            ->when($request->filled('tier'), fn ($q) => $q->where('tier', $request->string('tier')->value()))
            ->latest('created_at')
            ->paginate($this->perPage($request));

        return CommandResource::collection($commands);
    }

    public function createCommand(CreateCommandRequest $request): JsonResponse
    {
        $command = $this->commands->createCommand(CreateCommandData::fromRequest($request));

        return CommandResource::make($command)
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function routeIncident(RouteIncidentRequest $request): JsonResponse
    {
        $incident = $this->routing->route(RouteIncidentData::fromRequest($request));

        abort_if($incident === null, Response::HTTP_UNPROCESSABLE_ENTITY, 'no_responder_for_country');

        return CommandIncidentResource::make($incident)
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function incidents(Request $request): AnonymousResourceCollection
    {
        $this->assertSuperAdmin($request);

        $incidents = CommandIncident::query()
            ->when($request->filled('category'), fn ($q) => $q->where('category', $request->string('category')->value()))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')->value()))
            ->when($request->filled('severity'), fn ($q) => $q->where('severity', $request->string('severity')->value()))
            ->when($request->filled('agency_id'), fn ($q) => $q->where('agency_id', $request->string('agency_id')->value()))
            ->when($request->filled('command_id'), fn ($q) => $q->where('command_id', $request->string('command_id')->value()))
            ->latest('opened_at')
            ->paginate($this->perPage($request));

        return CommandIncidentResource::collection($incidents);
    }

    public function showIncident(Request $request, CommandIncident $commandIncident): CommandIncidentResource
    {
        $this->assertSuperAdmin($request);

        return CommandIncidentResource::make($commandIncident);
    }

    public function actOnIncident(ActOnIncidentRequest $request, CommandIncident $commandIncident): CommandIncidentResource
    {
        $updated = $this->commands->act(
            $commandIncident,
            $request->string('action')->value(),
            $request->user()?->getKey(),
        );

        return CommandIncidentResource::make($updated);
    }

    private function assertSuperAdmin(Request $request): void
    {
        abort_unless((bool) $request->user()?->isSuperAdmin(), Response::HTTP_FORBIDDEN);
    }
}
