<?php

declare(strict_types=1);

namespace App\Domains\Ingest\Http\Controllers;

use App\Domains\Ingest\Http\Resources\PublicIncidentResource;
use App\Domains\Ingest\Models\DetectionEvent;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * The anonymized PUBLIC situational-awareness feed (citizen-facing), modeled on
 * Omni's public-safety feed. NO auth — strictly read-only and anonymized.
 *
 * Each row is the coarse, non-identifying projection of an incident-bearing
 * detection: opaque ref, coarse category, softened severity, ~1 km coords, a
 * coarse area, and a date. No org names, no PII, no exact location — see
 * {@see PublicIncidentResource}.
 */
final class PublicIncidentController extends Controller
{
    /**
     * The hard ceiling on rows the public feed will ever return.
     */
    private const MAX_LIMIT = 200;

    /**
     * GET /api/v1/public/incidents — recent anonymized incidents.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $limit = max(1, min($request->integer('limit', 50), self::MAX_LIMIT));

        $events = DetectionEvent::query()
            ->where('triggered', true)
            ->whereNotNull('incident_id')
            // Only mappable, public-relevant events keep PII out by construction:
            // the resource exposes only coarse fields.
            ->when(
                $request->filled('country'),
                fn ($query) => $query->where('payload->country', $request->string('country')->upper()->value()),
            )
            ->latest('received_at')
            ->limit($limit)
            ->get();

        return PublicIncidentResource::collection($events);
    }
}
