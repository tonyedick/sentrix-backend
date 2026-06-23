<?php

declare(strict_types=1);

namespace App\Domains\Evidence\Http\Controllers;

use App\Domains\Authorization\Support\Enums\DefaultPermission;
use App\Domains\Evidence\DTOs\ObservationData;
use App\Domains\Evidence\Http\Requests\BookmarkRequest;
use App\Domains\Evidence\Http\Requests\HoldRequest;
use App\Domains\Evidence\Http\Requests\ObserveRequest;
use App\Domains\Evidence\Http\Resources\ObservationResource;
use App\Domains\Evidence\Models\Observation;
use App\Domains\Evidence\Services\EvidenceVaultService;
use App\Domains\Evidence\Support\Enums\RetentionTier;
use App\Domains\Organization\Models\Organization;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Organization-scoped forensic evidence vault.
 *
 * The deterministic FACETED search lives here — the AI/Core layer parses a
 * natural-language query INTO these structured params (kind, plate, severity,
 * status, color/make/model/item, camera, date range, q). This backend never
 * parses NL itself; it executes the structured filter.
 */
final class EvidenceController extends Controller
{
    /** Cache TTL (seconds) for the vault stats roll-up. */
    private const STATS_CACHE_TTL = 30;

    /**
     * Attribute facets that map onto a key inside the `attributes` jsonb bag.
     * Each is matched with a case-insensitive substring on the extracted text.
     *
     * @var array<string, string>
     */
    private const ATTRIBUTE_FACETS = [
        'color' => 'color',
        'make' => 'make',
        'model' => 'model',
        'item' => 'item',
    ];

    public function __construct(private readonly EvidenceVaultService $vault) {}

    /**
     * Batch ingest. Returns the recorded count and the new ids.
     */
    public function observe(ObserveRequest $request, Organization $organization): JsonResponse
    {
        /** @var array<int, array<string, mixed>> $rows */
        $rows = $request->validated('observations');

        $items = array_map(
            static fn (array $row): ObservationData => ObservationData::fromArray($row),
            $rows,
        );

        $recorded = $this->vault->record($organization, array_values($items), $request->user());

        $ids = array_map(static fn (Observation $o): string => (string) $o->getKey(), $recorded);

        return response()->json([
            'data' => [
                'recorded' => count($recorded),
                'ids' => $ids,
            ],
        ], Response::HTTP_CREATED);
    }

    /**
     * Faceted forensic search. All query params are optional and AND-combined.
     */
    public function search(Request $request, Organization $organization): AnonymousResourceCollection
    {
        abort_unless($request->user()->can(DefaultPermission::EvidenceView->value), Response::HTTP_FORBIDDEN);

        $query = Observation::query()->where('organization_id', $organization->getKey());

        $this->applyFacets($request, $query);

        $observations = $query
            // Cursor (keyset) pagination: index-friendly and COUNT-free over a
            // potentially huge observations table. id tiebreaks equal timestamps.
            ->orderByDesc('observed_at')
            ->orderByDesc('id')
            ->cursorPaginate($this->perPage($request));

        return ObservationResource::collection($observations);
    }

    /**
     * Cross-camera vehicle journey: every observation on a plate for this org,
     * chronological, with camera + coords — "where has this vehicle been seen".
     *
     * `{plate}` is a STRING param (not a model bind); we normalize and query by it.
     */
    public function vehicle(Request $request, Organization $organization, string $plate): JsonResponse
    {
        abort_unless($request->user()->can(DefaultPermission::EvidenceView->value), Response::HTTP_FORBIDDEN);

        $normalized = strtoupper(trim($plate));

        $observations = Observation::query()
            ->where('organization_id', $organization->getKey())
            ->where('plate', $normalized)
            ->orderBy('observed_at')
            ->paginate($this->perPage($request));

        $trail = $observations->getCollection()->map(static fn (Observation $o): array => [
            'observation_id' => $o->getKey(),
            'camera_source_id' => $o->camera_source_id,
            'observed_at' => $o->observed_at?->toIso8601String(),
            'lat' => $o->lat !== null ? (float) $o->lat : null,
            'lng' => $o->lng !== null ? (float) $o->lng : null,
            'label' => $o->label,
            'snapshot_url' => $o->snapshot_url,
        ])->all();

        return response()->json([
            'data' => [
                'plate' => $normalized,
                'sightings' => $observations->total(),
                'trail' => $trail,
            ],
            'meta' => [
                'current_page' => $observations->currentPage(),
                'last_page' => $observations->lastPage(),
                'per_page' => $observations->perPage(),
                'total' => $observations->total(),
            ],
        ]);
    }

    /**
     * Vault rollup: a read-only computed snapshot (no state change, no event).
     */
    public function stats(Request $request, Organization $organization): JsonResponse
    {
        abort_unless($request->user()->can(DefaultPermission::EvidenceView->value), Response::HTTP_FORBIDDEN);

        // Cached briefly (Redis): several aggregate scans that dashboards poll.
        // Writes (hold/bookmark/observe) surface on the next refresh after the
        // window elapses.
        $data = Cache::remember(
            "evidence:stats:{$organization->getKey()}",
            self::STATS_CACHE_TTL,
            static function () use ($organization): array {
                $base = static fn (): Builder => Observation::query()
                    ->where('organization_id', $organization->getKey());

                return [
                    'total' => $base()->count(),
                    'by_kind' => $base()
                        ->selectRaw('kind, count(*) as aggregate')
                        ->groupBy('kind')
                        ->pluck('aggregate', 'kind')
                        ->all(),
                    'by_retention_tier' => $base()
                        ->selectRaw('retention_tier, count(*) as aggregate')
                        ->groupBy('retention_tier')
                        ->pluck('aggregate', 'retention_tier')
                        ->all(),
                    'on_legal_hold' => $base()->where('hold', true)->count(),
                    'bookmarked' => $base()->where('bookmarked', true)->count(),
                    // Awaiting review: not yet bookmarked, sealed, or held.
                    'awaiting_review' => $base()
                        ->where('bookmarked', false)
                        ->where('sealed', false)
                        ->where('hold', false)
                        ->count(),
                ];
            },
        );

        return response()->json(['data' => $data]);
    }

    /**
     * Toggle (or set) a legal hold.
     */
    public function hold(HoldRequest $request, Organization $organization, Observation $observation): ObservationResource
    {
        $this->assertInOrganization($organization, $observation);

        $hold = $request->has('hold') ? $request->boolean('hold') : null;

        return ObservationResource::make(
            $this->vault->toggleHold($observation, $hold, $request->user()),
        );
    }

    /**
     * Toggle (or set) a bookmark.
     */
    public function bookmark(BookmarkRequest $request, Organization $organization, Observation $observation): ObservationResource
    {
        $this->assertInOrganization($organization, $observation);

        $bookmarked = $request->has('bookmarked') ? $request->boolean('bookmarked') : null;

        return ObservationResource::make(
            $this->vault->toggleBookmark($observation, $bookmarked, $request->user()),
        );
    }

    /**
     * Apply every supplied facet to the query (AND-combined).
     *
     * @param  Builder<Observation>  $query
     */
    private function applyFacets(Request $request, Builder $query): void
    {
        $query
            ->when($request->filled('kind'), fn (Builder $q) => $q->where('kind', $request->string('kind')->value()))
            ->when($request->filled('severity'), fn (Builder $q) => $q->where('severity', $request->string('severity')->value()));

        // Plate: case-insensitive, dash/space-insensitive partial match. The
        // column stores an upper-cased plate; normalize the needle the same way.
        if ($request->filled('plate')) {
            $needle = strtoupper(preg_replace('/[\s-]+/', '', $request->string('plate')->value()) ?? '');
            $query->whereRaw("REPLACE(REPLACE(plate, '-', ''), ' ', '') LIKE ?", ['%'.$needle.'%']);
        }

        // status maps to a boolean flag column.
        if ($request->filled('status')) {
            $status = $request->string('status')->value();
            if (in_array($status, ['hold', 'bookmarked', 'sealed'], true)) {
                $column = $status === 'hold' ? 'hold' : $status;
                $query->where($column, true);
            }
        }

        // camera_source_id is a uuid column — NEVER compare it to non-uuid input
        // on Postgres; guard with Str::isUuid().
        if ($request->filled('camera_source_id')) {
            $candidate = $request->string('camera_source_id')->value();
            if (Str::isUuid($candidate)) {
                $query->where('camera_source_id', $candidate);
            } else {
                // A malformed uuid can never match a real source; return nothing
                // rather than throwing a Postgres uuid cast error.
                $query->whereRaw('1 = 0');
            }
        }

        // Attribute facets: match inside the jsonb bag via `->>` text extraction.
        // The jsonb key is inlined from a FIXED allowlist (self::ATTRIBUTE_FACETS),
        // never from user input, so there is no injection surface; only the value
        // is bound. (Binding the key in the `->>` position is unreliable on PG.)
        foreach (self::ATTRIBUTE_FACETS as $param => $key) {
            if ($request->filled($param)) {
                $value = $request->string($param)->value();
                $query->whereRaw("attributes->>'{$key}' ILIKE ?", ['%'.$value.'%']);
            }
        }

        // observed_at range.
        if ($request->filled('from')) {
            $query->where('observed_at', '>=', Carbon::parse($request->string('from')->value()));
        }
        if ($request->filled('to')) {
            $query->where('observed_at', '<=', Carbon::parse($request->string('to')->value()));
        }

        // q: substring on the human label.
        if ($request->filled('q')) {
            $query->where('label', 'ILIKE', '%'.$request->string('q')->value().'%');
        }
    }

    private function assertInOrganization(Organization $organization, Observation $observation): void
    {
        abort_if($observation->organization_id !== $organization->getKey(), Response::HTTP_NOT_FOUND);
    }
}
