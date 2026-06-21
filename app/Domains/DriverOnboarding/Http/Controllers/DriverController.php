<?php

declare(strict_types=1);

namespace App\Domains\DriverOnboarding\Http\Controllers;

use App\Domains\DriverOnboarding\DTOs\BookInspectionData;
use App\Domains\DriverOnboarding\DTOs\RegisterDriverData;
use App\Domains\DriverOnboarding\DTOs\UploadDocumentData;
use App\Domains\DriverOnboarding\Http\Requests\BookInspectionRequest;
use App\Domains\DriverOnboarding\Http\Requests\RegisterDriverRequest;
use App\Domains\DriverOnboarding\Http\Requests\SetOnlineRequest;
use App\Domains\DriverOnboarding\Http\Requests\UploadDocumentRequest;
use App\Domains\DriverOnboarding\Http\Resources\DriverDocumentResource;
use App\Domains\DriverOnboarding\Http\Resources\DriverResource;
use App\Domains\DriverOnboarding\Http\Resources\InspectionResource;
use App\Domains\DriverOnboarding\Http\Resources\VettingCenterResource;
use App\Domains\DriverOnboarding\Models\Driver;
use App\Domains\DriverOnboarding\Models\VettingCenter;
use App\Domains\DriverOnboarding\Services\DriverService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

/**
 * Driver-scoped onboarding surface. User-scoped (ADR-0001): the Driver row
 * belongs to the authenticated user — there is no route-model binding here; the
 * caller's own driver is always resolved from $request->user().
 */
final class DriverController extends Controller
{
    public function __construct(private readonly DriverService $drivers) {}

    public function register(RegisterDriverRequest $request): JsonResponse
    {
        // Repo-consistent 409 pre-check (the service also race-guards under lock).
        abort_if(
            Driver::query()->where('user_id', $request->user()->getKey())->exists(),
            Response::HTTP_CONFLICT,
            'You are already registered as a driver.',
        );

        $driver = $this->drivers->register($request->user(), RegisterDriverData::fromRequest($request));

        return DriverResource::make($driver)->response()->setStatusCode(Response::HTTP_CREATED);
    }

    public function documents(UploadDocumentRequest $request): JsonResponse
    {
        $driver = $this->ownDriver($request);

        $document = $this->drivers->uploadDocument($driver, UploadDocumentData::fromRequest($request));

        return DriverDocumentResource::make($document)->response()->setStatusCode(Response::HTTP_CREATED);
    }

    public function me(Request $request): DriverResource
    {
        $driver = $this->ownDriver($request);
        $driver->load(['documents' => fn ($q) => $q->latest('created_at')]);
        $driver->setRelation('inspections', $driver->inspections()->latest('created_at')->limit(1)->get());

        return DriverResource::make($driver);
    }

    public function online(SetOnlineRequest $request): DriverResource
    {
        $driver = $this->ownDriver($request);

        return DriverResource::make($this->drivers->setOnline($driver, $request->boolean('online')));
    }

    public function vettingCenters(Request $request): AnonymousResourceCollection
    {
        $centers = VettingCenter::query()
            ->orderBy('name')
            ->paginate($this->perPage($request));

        return VettingCenterResource::collection($centers);
    }

    public function bookInspection(BookInspectionRequest $request): JsonResponse
    {
        $driver = $this->ownDriver($request);

        $inspection = $this->drivers->bookInspection($driver, BookInspectionData::fromRequest($request));

        return InspectionResource::make($inspection)->response()->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * Resolve the authenticated user's own driver profile, or 404. This is the
     * ownership assertion for every driver-scoped action — a user can only ever
     * touch their own Driver row.
     */
    private function ownDriver(Request $request): Driver
    {
        /** @var Driver|null $driver */
        $driver = Driver::query()->where('user_id', $request->user()->getKey())->first();

        abort_if($driver === null, Response::HTTP_NOT_FOUND, 'You are not registered as a driver.');

        return $driver;
    }
}
