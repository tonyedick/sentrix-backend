<?php

declare(strict_types=1);

namespace App\Domains\DriverOnboarding\Http\Controllers;

use App\Domains\DriverOnboarding\Http\Requests\Staff\RecordDecisionRequest;
use App\Domains\DriverOnboarding\Http\Requests\Staff\RecordInspectionRequest;
use App\Domains\DriverOnboarding\Http\Requests\Staff\ReviewDocumentRequest;
use App\Domains\DriverOnboarding\Http\Resources\DriverDocumentResource;
use App\Domains\DriverOnboarding\Http\Resources\DriverResource;
use App\Domains\DriverOnboarding\Http\Resources\InspectionResource;
use App\Domains\DriverOnboarding\Models\Driver;
use App\Domains\DriverOnboarding\Models\DriverDocument;
use App\Domains\DriverOnboarding\Models\Inspection;
use App\Domains\DriverOnboarding\Services\DriverService;
use App\Domains\DriverOnboarding\Support\Enums\DriverStage;
use App\Domains\DriverOnboarding\Support\Enums\InspectionStatus;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

/**
 * Staff review surface for driver onboarding. PLATFORM-scoped (NOT
 * organization-scoped): every action is gated on SuperAdmin — enforced both in
 * the Form Requests' authorize() and (for plain reads) by assertSuperAdmin here.
 *
 * TODO: replace SuperAdmin gating with a platform-staff 'staff:drivers' role.
 */
final class DriverStaffController extends Controller
{
    public function __construct(private readonly DriverService $drivers) {}

    public function driverQueue(Request $request): AnonymousResourceCollection
    {
        $this->assertSuperAdmin($request);

        $drivers = Driver::query()
            ->where('stage', DriverStage::DocumentsReview->value)
            ->latest('created_at')
            ->paginate($this->perPage($request));

        return DriverResource::collection($drivers);
    }

    public function document(ReviewDocumentRequest $request, Driver $driver): DriverDocumentResource
    {
        // Ability enforced in the Form Request (SuperAdmin). Validate the document
        // belongs to {driver} (the document_id is uuid-validated, so this never
        // compares a uuid column to non-uuid input on Postgres).
        /** @var DriverDocument $document */
        $document = DriverDocument::query()
            ->where('driver_id', $driver->getKey())
            ->whereKey($request->string('document_id')->value())
            ->firstOrFail();

        $updated = $this->drivers->reviewDocument(
            $driver,
            $document,
            $request->string('decision')->value(),
            $request->filled('note') ? $request->string('note')->value() : null,
            $request->user(),
        );

        return DriverDocumentResource::make($updated);
    }

    public function decision(RecordDecisionRequest $request, Driver $driver): DriverResource
    {
        $updated = $this->drivers->recordDecision(
            $driver,
            $request->string('decision')->value(),
            $request->filled('note') ? $request->string('note')->value() : null,
            $request->user(),
        );

        return DriverResource::make($updated);
    }

    public function inspectionQueue(Request $request): AnonymousResourceCollection
    {
        $this->assertSuperAdmin($request);

        $inspections = Inspection::query()
            ->where('status', InspectionStatus::Booked->value)
            ->latest('created_at')
            ->paginate($this->perPage($request));

        return InspectionResource::collection($inspections);
    }

    public function inspection(RecordInspectionRequest $request, Driver $driver): InspectionResource
    {
        $inspection = $this->drivers->recordInspection(
            $driver,
            $request->string('decision')->value(),
            $request->filled('checklist') ? $request->input('checklist') : null,
            $request->user(),
        );

        return InspectionResource::make($inspection);
    }

    private function assertSuperAdmin(Request $request): void
    {
        // TODO: replace with platform-staff 'staff:drivers' role.
        abort_unless((bool) $request->user()?->isSuperAdmin(), Response::HTTP_FORBIDDEN);
    }
}
