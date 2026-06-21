<?php

declare(strict_types=1);

namespace App\Domains\Responder\Http\Controllers;

use App\Domains\Authorization\Support\Enums\DefaultPermission;
use App\Domains\Organization\Models\Organization;
use App\Domains\Responder\Http\Requests\ScheduleDutyShiftRequest;
use App\Domains\Responder\Http\Resources\DutyShiftResource;
use App\Domains\Responder\Models\DutyShift;
use App\Domains\Responder\Models\Responder;
use App\Domains\Responder\Services\DutyService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\Response;

/**
 * Duty shift scheduling for a responder. Activation/closure is handled by the
 * duty sweep; this surface only schedules and cancels.
 */
final class DutyShiftController extends Controller
{
    public function __construct(private readonly DutyService $duty) {}

    public function index(Request $request, Organization $organization, Responder $responder): AnonymousResourceCollection
    {
        $this->assertResponderInOrganization($organization, $responder);
        abort_unless($request->user()->can(DefaultPermission::RespondersView->value), Response::HTTP_FORBIDDEN);

        return DutyShiftResource::collection(
            $responder->shifts()->latest('starts_at')->paginate($this->perPage($request)),
        );
    }

    public function store(ScheduleDutyShiftRequest $request, Organization $organization, Responder $responder): JsonResponse
    {
        $this->assertResponderInOrganization($organization, $responder);

        $shift = $this->duty->schedule(
            $responder,
            Carbon::parse($request->string('starts_at')->value()),
            Carbon::parse($request->string('ends_at')->value()),
        );

        return DutyShiftResource::make($shift)
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function destroy(Request $request, Organization $organization, Responder $responder, DutyShift $shift): DutyShiftResource
    {
        $this->assertResponderInOrganization($organization, $responder);
        abort_if($shift->responder_id !== $responder->getKey(), Response::HTTP_NOT_FOUND);
        abort_unless($request->user()->can(DefaultPermission::SchedulesManage->value), Response::HTTP_FORBIDDEN);

        return DutyShiftResource::make($this->duty->cancel($shift));
    }

    private function assertResponderInOrganization(Organization $organization, Responder $responder): void
    {
        abort_if($responder->organization_id !== $organization->getKey(), Response::HTTP_NOT_FOUND);
    }
}
