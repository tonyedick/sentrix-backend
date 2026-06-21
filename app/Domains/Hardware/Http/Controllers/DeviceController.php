<?php

declare(strict_types=1);

namespace App\Domains\Hardware\Http\Controllers;

use App\Domains\Authorization\Support\Enums\DefaultPermission;
use App\Domains\Hardware\DTOs\RegisterDeviceData;
use App\Domains\Hardware\Http\Requests\RegisterDeviceRequest;
use App\Domains\Hardware\Http\Resources\DeviceResource;
use App\Domains\Hardware\Models\Device;
use App\Domains\Hardware\Services\DeviceService;
use App\Domains\Organization\Models\Organization;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

/**
 * Organization-scoped registry of physical security hardware. Abilities are
 * enforced per-action (hardware.view / hardware.register / hardware.resync /
 * hardware.diagnose).
 */
final class DeviceController extends Controller
{
    public function __construct(private readonly DeviceService $devices) {}

    public function index(Request $request, Organization $organization): AnonymousResourceCollection
    {
        abort_unless(
            $request->user()->can(DefaultPermission::HardwareView->value),
            Response::HTTP_FORBIDDEN,
        );

        $devices = Device::query()
            ->where('organization_id', $organization->getKey())
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')->value()))
            ->when($request->filled('kind'), fn ($query) => $query->where('kind', $request->string('kind')->value()))
            ->when($request->filled('site'), fn ($query) => $query->where('site', $request->string('site')->value()))
            ->latest('created_at')
            ->paginate($this->perPage($request));

        return DeviceResource::collection($devices);
    }

    public function store(RegisterDeviceRequest $request, Organization $organization): JsonResponse
    {
        $device = $this->devices->register(
            $organization,
            $request->user(),
            RegisterDeviceData::fromRequest($request),
        );

        return DeviceResource::make($device)
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(Request $request, Organization $organization, Device $device): DeviceResource
    {
        $this->assertDeviceInOrganization($organization, $device);
        abort_unless($request->user()->can(DefaultPermission::HardwareView->value), Response::HTTP_FORBIDDEN);

        return DeviceResource::make($device);
    }

    public function resync(Request $request, Organization $organization, Device $device): DeviceResource
    {
        $this->assertDeviceInOrganization($organization, $device);
        abort_unless($request->user()->can(DefaultPermission::HardwareResync->value), Response::HTTP_FORBIDDEN);

        return DeviceResource::make($this->devices->resync($device, $request->user()));
    }

    public function diagnose(Request $request, Organization $organization, Device $device): JsonResponse
    {
        $this->assertDeviceInOrganization($organization, $device);
        abort_unless($request->user()->can(DefaultPermission::HardwareDiagnose->value), Response::HTTP_FORBIDDEN);

        return response()->json(['data' => $this->devices->diagnose($device)]);
    }

    private function assertDeviceInOrganization(Organization $organization, Device $device): void
    {
        abort_if($device->organization_id !== $organization->getKey(), Response::HTTP_NOT_FOUND);
    }
}
