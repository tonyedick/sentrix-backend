<?php

declare(strict_types=1);

namespace App\Domains\Intel\Http\Controllers;

use App\Domains\Authorization\Support\Enums\DefaultPermission;
use App\Domains\Intel\Services\IntelReportService;
use App\Domains\Organization\Models\Organization;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Intel: read-only organization reporting + analytics. Every action is a
 * computed read (no model projection, no event), so it returns a plain
 * ['data' => ...] array rather than an API Resource.
 */
final class IntelController extends Controller
{
    /** Allowed range tokens mapped to whole-day windows. */
    private const RANGES = [
        '24h' => 1,
        '7d' => 7,
        '30d' => 30,
    ];

    private const DEFAULT_RANGE = '7d';

    /**
     * Cache TTL (seconds) for the computed roll-ups. Short, so dashboards that
     * poll don't re-run the aggregates every request while staying near-live.
     * Writes are reflected on the next refresh after the window elapses.
     */
    private const CACHE_TTL = 30;

    public function __construct(private readonly IntelReportService $intel) {}

    public function report(Request $request, Organization $organization): JsonResponse
    {
        abort_unless($request->user()->can(DefaultPermission::IntelView->value), Response::HTTP_FORBIDDEN);

        return response()->json(['data' => $this->cachedReport($request, $organization)]);
    }

    public function analytics(Request $request, Organization $organization): JsonResponse
    {
        abort_unless($request->user()->can(DefaultPermission::IntelView->value), Response::HTTP_FORBIDDEN);

        $range = $this->rangeLabel($request);
        $bucket = $this->resolveBucket($request);

        $data = Cache::remember(
            "intel:analytics:{$organization->getKey()}:{$range}:{$bucket}",
            self::CACHE_TTL,
            fn (): array => $this->intel->analytics($organization, $this->resolveSince($request), $bucket),
        );

        return response()->json(['data' => $data]);
    }

    public function export(Request $request, Organization $organization): Response
    {
        abort_unless($request->user()->can(DefaultPermission::IntelExport->value), Response::HTTP_FORBIDDEN);

        $report = $this->cachedReport($request, $organization);

        // format=json returns the same report as JSON (still wrapped by the
        // envelope). Default + format=csv streams a CSV download — WrapApiResponse
        // only rewrites JsonResponse, so a StreamedResponse passes through untouched.
        if ($request->string('format')->value() === 'json') {
            return response()->json(['data' => $report]);
        }

        $filename = 'intel-report-'.Carbon::now()->format('Ymd-His').'.csv';

        return new StreamedResponse(function () use ($report): void {
            $handle = fopen('php://output', 'wb');

            fputcsv($handle, ['metric', 'key', 'value']);

            fputcsv($handle, ['range', 'since', $report['range']['since']]);
            fputcsv($handle, ['range', 'until', $report['range']['until']]);

            fputcsv($handle, ['incidents', 'total', $report['incidents']['total']]);
            foreach ($report['incidents']['by_severity'] as $key => $value) {
                fputcsv($handle, ['incidents.by_severity', (string) $key, $value]);
            }
            foreach ($report['incidents']['by_status'] as $key => $value) {
                fputcsv($handle, ['incidents.by_status', (string) $key, $value]);
            }

            fputcsv($handle, ['emergencies', 'total', $report['emergencies']['total']]);
            foreach ($report['emergencies']['by_status'] as $key => $value) {
                fputcsv($handle, ['emergencies.by_status', (string) $key, $value]);
            }

            fputcsv($handle, ['trips', 'total', $report['trips']['total']]);
            fputcsv($handle, ['observations', 'total', $report['observations']['total']]);

            foreach ($report['response_time_seconds'] as $block => $summary) {
                if (! is_array($summary)) {
                    continue;
                }
                foreach ($summary as $key => $value) {
                    fputcsv($handle, ['response_time_seconds.'.$block, (string) $key, $value ?? '']);
                }
            }

            foreach ($report['top_zones'] as $zone) {
                fputcsv($handle, ['top_zones', (string) ($zone['camera_source_id'] ?? 'unknown'), $zone['count']]);
            }

            fclose($handle);
        }, Response::HTTP_OK, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    /**
     * The period report, memoised per org+range for CACHE_TTL.
     *
     * @return array<string, mixed>
     */
    private function cachedReport(Request $request, Organization $organization): array
    {
        $range = $this->rangeLabel($request);

        return Cache::remember(
            "intel:report:{$organization->getKey()}:{$range}",
            self::CACHE_TTL,
            fn (): array => $this->intel->report($organization, $this->resolveSince($request)),
        );
    }

    /** Normalised range token used in cache keys (unknown -> default). */
    private function rangeLabel(Request $request): string
    {
        $range = $request->string('range')->value();

        return array_key_exists($range, self::RANGES) ? $range : self::DEFAULT_RANGE;
    }

    /**
     * Resolve ?range to a since-timestamp. Unknown ranges clamp to the 7d
     * default (documented choice — friendlier than a 422 for a read endpoint).
     */
    private function resolveSince(Request $request): Carbon
    {
        $range = $request->string('range')->value();
        $days = self::RANGES[$range] ?? self::RANGES[self::DEFAULT_RANGE];

        return Carbon::now()->subDays($days);
    }

    private function resolveBucket(Request $request): string
    {
        $bucket = $request->string('bucket')->value();

        return $bucket === 'hour' ? 'hour' : 'day';
    }
}
