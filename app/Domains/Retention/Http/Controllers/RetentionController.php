<?php

declare(strict_types=1);

namespace App\Domains\Retention\Http\Controllers;

use App\Domains\Authorization\Support\Enums\DefaultPermission;
use App\Domains\Organization\Models\Organization;
use App\Domains\Retention\Http\Requests\ArchiveExportRequest;
use App\Domains\Retention\Http\Requests\PurgeRequest;
use App\Domains\Retention\Http\Requests\SweepRequest;
use App\Domains\Retention\Http\Resources\RetentionExportResource;
use App\Domains\Retention\Services\RetentionService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Organization-scoped Evidence vault storage lifecycle: usage rollup, the
 * re-tiering sweep, archive-first export, and archived-first purge. Thin
 * controller — all writes live in RetentionService inside a transaction.
 */
final class RetentionController extends Controller
{
    public function __construct(private readonly RetentionService $retention) {}

    /**
     * Read-only storage usage rollup vs the plan quota (no state change, no event).
     */
    public function storage(Request $request, Organization $organization): JsonResponse
    {
        abort_unless(
            $request->user()->can(DefaultPermission::StorageView->value),
            Response::HTTP_FORBIDDEN,
        );

        return response()->json([
            'data' => $this->retention->usage($organization)->toArray(),
        ]);
    }

    /**
     * Run the lifecycle sweep for this org. Idempotent; returns counts moved per tier.
     */
    public function sweep(SweepRequest $request, Organization $organization): JsonResponse
    {
        $moved = $this->retention->sweep($organization);

        return response()->json([
            'data' => [
                'moved' => $moved,
            ],
        ]);
    }

    /**
     * Archive-first export: bundle the eligible (or explicitly chosen) observations
     * into a manifest, seal + mark them archived, and return the export.
     */
    public function archive(ArchiveExportRequest $request, Organization $organization): JsonResponse
    {
        /** @var list<string>|null $ids */
        $ids = $request->has('observation_ids')
            ? array_values($request->validated('observation_ids', []))
            : null;

        $export = $this->retention->archive($organization, $ids, $request->user());

        return RetentionExportResource::make($export)
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * Archived-first deletion: purge archived, non-hold observations. Legal holds survive.
     */
    public function purge(PurgeRequest $request, Organization $organization): JsonResponse
    {
        $purged = $this->retention->purge($organization);

        return response()->json([
            'data' => [
                'purged' => $purged,
            ],
        ]);
    }
}
