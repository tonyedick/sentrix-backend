<?php

declare(strict_types=1);

namespace App\Domains\Ingest\Http\Controllers;

use App\Domains\Ingest\DTOs\IngestDetectionData;
use App\Domains\Ingest\DTOs\IngestSignalData;
use App\Domains\Ingest\DTOs\IngestVisionData;
use App\Domains\Ingest\Http\Requests\IngestDetectionRequest;
use App\Domains\Ingest\Http\Requests\IngestSignalRequest;
use App\Domains\Ingest\Http\Requests\IngestVisionRequest;
use App\Domains\Ingest\Http\Resources\DetectionEventResource;
use App\Domains\Ingest\Services\IngestService;
use App\Domains\Ingest\Support\IngestResult;
use App\Domains\Organization\Models\Organization;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Machine ingest surface. Authed by the service-token middleware (`core.service`,
 * X-Service-Token), NOT sanctum — there is no user. Each endpoint persists a
 * DetectionEvent, runs the DecisionEngine, and (when triggered) opens an Incident.
 */
final class IngestController extends Controller
{
    public function __construct(private readonly IngestService $ingest) {}

    /**
     * POST /api/v1/ingest/detections — a native detection event.
     */
    public function detections(IngestDetectionRequest $request): JsonResponse
    {
        $result = $this->ingest->ingestDetection(IngestDetectionData::fromRequest($request));

        return $this->respond($result);
    }

    /**
     * POST /api/v1/ingest/vision — a vision-provider payload.
     */
    public function vision(IngestVisionRequest $request): JsonResponse
    {
        $result = $this->ingest->ingestVision(IngestVisionData::fromRequest($request));

        return $this->respond($result);
    }

    /**
     * POST /api/v1/signal/ingest — a SafeSignal cross-product report. The tenant
     * may be a UUID or an org slug; resolve it with a Str::isUuid guard so
     * Postgres never compares its uuid column to a non-uuid string.
     */
    public function signal(IngestSignalRequest $request): JsonResponse
    {
        $data = IngestSignalData::fromRequest($request);

        $organization = $this->resolveOrganization($data->organization);

        if ($organization === null) {
            return response()->json([
                'message' => 'Unknown organization.',
                'errors' => ['organization' => ['No organization matches the supplied reference.']],
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $result = $this->ingest->ingestSignal($data, $organization);

        return $this->respond($result);
    }

    /**
     * 202 Accepted with the persisted event and the opened incident id (if any).
     */
    private function respond(IngestResult $result): JsonResponse
    {
        return response()->json([
            'message' => $result->incident !== null ? 'Incident opened.' : 'Detection recorded.',
            'data' => [
                'detection_event' => DetectionEventResource::make($result->detectionEvent)->resolve(),
                'incident_id' => $result->incident?->getKey(),
                'triggered' => (bool) $result->detectionEvent->triggered,
            ],
        ], Response::HTTP_ACCEPTED);
    }

    /**
     * Resolve a tenant reference (UUID or slug) to an Organization. Only compares
     * against the uuid `id` column when the value is actually a UUID.
     */
    private function resolveOrganization(string $reference): ?Organization
    {
        if ($reference === '') {
            return null;
        }

        return Organization::query()
            ->when(
                Str::isUuid($reference),
                fn ($query) => $query->where('id', $reference),
                fn ($query) => $query->where('slug', $reference),
            )
            ->first();
    }
}
