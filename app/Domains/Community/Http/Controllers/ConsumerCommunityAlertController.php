<?php

declare(strict_types=1);

namespace App\Domains\Community\Http\Controllers;

use App\Domains\Community\Http\Requests\ConfirmAlertRequest;
use App\Domains\Community\Http\Requests\NearbyAlertsRequest;
use App\Domains\Community\Http\Requests\ReportAlertRequest;
use App\Domains\Community\Http\Resources\CommunityAlertResource;
use App\Domains\Community\Models\CommunityAlert;
use App\Domains\Community\Services\CommunityAlertService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

/**
 * Consumer community alerts (user-scoped, ADR-0001). A geo feed of crowdsourced
 * alerts plus report + crowd verification (confirm/dismiss).
 */
final class ConsumerCommunityAlertController extends Controller
{
    public function __construct(private readonly CommunityAlertService $alerts) {}

    public function index(NearbyAlertsRequest $request): AnonymousResourceCollection
    {
        $radius = (int) $request->integer('radius', (int) config('sentrix.community.default_radius_m', 3000));

        $alerts = $this->alerts->nearby(
            (float) $request->float('lat'),
            (float) $request->float('lng'),
            $radius,
            $request->input('category'),
            $this->perPage($request),
        );

        return CommunityAlertResource::collection($alerts);
    }

    public function store(ReportAlertRequest $request): JsonResponse
    {
        /** @var array{category:string,title:string,note?:?string,impact?:?string,lat:float|int|string,lng:float|int|string} $data */
        $data = $request->validated();

        $alert = $this->alerts->report($request->user(), $data);

        return CommunityAlertResource::make($alert)
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(Request $request, CommunityAlert $alert): CommunityAlertResource
    {
        return CommunityAlertResource::make($alert);
    }

    public function confirm(ConfirmAlertRequest $request, CommunityAlert $alert): CommunityAlertResource
    {
        return CommunityAlertResource::make($this->alerts->recordVote(
            $alert,
            $request->user(),
            'confirm',
            $request->boolean('still_active', true),
            $request->input('impact'),
            $request->input('comment'),
        ));
    }

    public function dismiss(ConfirmAlertRequest $request, CommunityAlert $alert): CommunityAlertResource
    {
        return CommunityAlertResource::make($this->alerts->recordVote(
            $alert,
            $request->user(),
            'dismiss',
            false,
            $request->input('impact'),
            $request->input('comment'),
        ));
    }
}
