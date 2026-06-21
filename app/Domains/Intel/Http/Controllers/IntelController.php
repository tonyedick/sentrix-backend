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

    public function __construct(private readonly IntelReportService $intel) {}

    public function report(Request $request, Organization $organization): JsonResponse
    {
        abort_unless($request->user()->can(DefaultPermission::IntelView->value), Response::HTTP_FORBIDDEN);

        $since = $this->resolveSince($request);

        return response()->json(['data' => $this->intel->report($organization, $since)]);
    }

    public function analytics(Request $request, Organization $organization): JsonResponse
    {
        abort_unless($request->user()->can(DefaultPermission::IntelView->value), Response::HTTP_FORBIDDEN);

        $since = $this->resolveSince($request);
        $bucket = $this->resolveBucket($request);

        return response()->json(['data' => $this->intel->analytics($organization, $since, $bucket)]);
    }

    public function export(Request $request, Organization $organization): Response
    {
        abort_unless($request->user()->can(DefaultPermission::IntelExport->value), Response::HTTP_FORBIDDEN);

        $since = $this->resolveSince($request);
        $report = $this->intel->report($organization, $since);

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
