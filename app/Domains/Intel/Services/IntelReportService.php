<?php

declare(strict_types=1);

namespace App\Domains\Intel\Services;

use App\Domains\Assignment\Models\AssignmentResponder;
use App\Domains\Emergency\Models\Emergency;
use App\Domains\Evidence\Models\Observation;
use App\Domains\Incident\Models\Incident;
use App\Domains\Organization\Models\Organization;
use App\Domains\Trip\Models\Trip;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * The Intel reporting/analytics aggregation layer. Read-only: it rolls up over
 * the operational domains (incidents, emergencies, trips, evidence) and owns no
 * primary entity. Every query is scoped by organization_id.
 *
 * Response-time methodology (documented):
 *   - Incidents: opened_at -> earliest AssignmentResponder.accepted_at for that
 *     incident (the first responder acceptance is the cleanest per-incident
 *     dispatch timestamp the models expose; the Incident itself has no
 *     dispatched/acknowledged column, only escalated/resolved/closed).
 *   - Emergencies: triggered_at -> acknowledged_at.
 * Carbon 3's diff* helpers return floats; all durations are cast to whole int
 * seconds.
 */
final readonly class IntelReportService
{
    /**
     * Period report for the org over [since, now].
     *
     * @return array<string, mixed>
     */
    public function report(Organization $organization, CarbonInterface $since): array
    {
        $orgId = $organization->getKey();

        $incidents = Incident::query()
            ->where('organization_id', $orgId)
            ->where('opened_at', '>=', $since)
            ->get(['id', 'status', 'severity', 'opened_at']);

        $emergencies = Emergency::query()
            ->where('organization_id', $orgId)
            ->where('triggered_at', '>=', $since)
            ->get(['id', 'status', 'severity', 'triggered_at', 'acknowledged_at']);

        $tripsCount = Trip::query()
            ->where('organization_id', $orgId)
            ->where('created_at', '>=', $since)
            ->count();

        $observations = Observation::query()
            ->where('organization_id', $orgId)
            ->where('observed_at', '>=', $since)
            ->get(['id', 'camera_source_id', 'severity', 'lat', 'lng']);

        return [
            'range' => [
                'since' => $since->toIso8601String(),
                'until' => Carbon::now()->toIso8601String(),
            ],
            'incidents' => [
                'total' => $incidents->count(),
                'by_severity' => $this->countBy($incidents, 'severity'),
                'by_status' => $this->countBy($incidents, 'status'),
            ],
            'emergencies' => [
                'total' => $emergencies->count(),
                'by_status' => $this->countBy($emergencies, 'status'),
            ],
            'trips' => [
                'total' => $tripsCount,
            ],
            'observations' => [
                'total' => $observations->count(),
            ],
            'response_time_seconds' => $this->responseTimeStats($incidents, $emergencies),
            'top_zones' => $this->topZones($observations),
        ];
    }

    /**
     * Trend series + breakdowns + a coarse coordinate heatmap.
     *
     * @return array<string, mixed>
     */
    public function analytics(Organization $organization, CarbonInterface $since, string $bucket): array
    {
        $orgId = $organization->getKey();

        $incidents = Incident::query()
            ->where('organization_id', $orgId)
            ->where('opened_at', '>=', $since)
            ->get(['id', 'status', 'severity', 'opened_at']);

        $emergencies = Emergency::query()
            ->where('organization_id', $orgId)
            ->where('triggered_at', '>=', $since)
            ->get(['id', 'status', 'severity', 'triggered_at', 'lat', 'lng']);

        $observations = Observation::query()
            ->where('organization_id', $orgId)
            ->where('observed_at', '>=', $since)
            ->get(['id', 'kind', 'severity', 'lat', 'lng']);

        return [
            'range' => [
                'since' => $since->toIso8601String(),
                'until' => Carbon::now()->toIso8601String(),
                'bucket' => $bucket,
            ],
            'trends' => [
                'incidents' => $this->trend($incidents, 'opened_at', $bucket),
                'emergencies' => $this->trend($emergencies, 'triggered_at', $bucket),
            ],
            'breakdowns' => [
                'incidents_by_severity' => $this->countBy($incidents, 'severity'),
                'emergencies_by_severity' => $this->countBy($emergencies, 'severity'),
                'observations_by_kind' => $this->countBy($observations, 'kind'),
            ],
            'heatmap' => $this->heatmap($observations->concat($emergencies)),
        ];
    }

    /**
     * Count grouped by an enum/string column, keyed by the scalar value.
     *
     * @param  \Illuminate\Support\Collection<int, \Illuminate\Database\Eloquent\Model>  $rows
     * @return array<string, int>
     */
    private function countBy($rows, string $column): array
    {
        return $rows
            ->groupBy(static function ($row) use ($column): string {
                $value = $row->{$column};

                if ($value instanceof \BackedEnum) {
                    return (string) $value->value;
                }

                return $value === null ? 'unknown' : (string) $value;
            })
            ->map(static fn ($group): int => $group->count())
            ->all();
    }

    /**
     * Time-bucketed counts (oldest first).
     *
     * @param  \Illuminate\Support\Collection<int, \Illuminate\Database\Eloquent\Model>  $rows
     * @return list<array{bucket: string, count: int}>
     */
    private function trend($rows, string $timestampColumn, string $bucket): array
    {
        $format = $bucket === 'hour' ? 'Y-m-d\TH:00' : 'Y-m-d';

        return $rows
            ->filter(static fn ($row): bool => $row->{$timestampColumn} !== null)
            ->groupBy(static fn ($row): string => $row->{$timestampColumn}->format($format))
            ->map(static fn ($group, string $key): array => [
                'bucket' => $key,
                'count' => $group->count(),
            ])
            ->sortKeys()
            ->values()
            ->all();
    }

    /**
     * Response-time stats for incidents (opened -> first responder accept) and
     * emergencies (triggered -> acknowledged). All durations whole-second ints.
     *
     * @param  \Illuminate\Support\Collection<int, Incident>  $incidents
     * @param  \Illuminate\Support\Collection<int, Emergency>  $emergencies
     * @return array<string, mixed>
     */
    private function responseTimeStats($incidents, $emergencies): array
    {
        $incidentSeconds = [];

        $firstAccept = AssignmentResponder::query()
            ->whereIn('incident_id', $incidents->pluck('id'))
            ->whereNotNull('accepted_at')
            ->get(['incident_id', 'accepted_at'])
            ->groupBy('incident_id')
            ->map(static fn ($rows) => $rows->pluck('accepted_at')->filter()->min());

        foreach ($incidents as $incident) {
            $acceptedAt = $firstAccept->get($incident->id);
            if ($acceptedAt === null || $incident->opened_at === null) {
                continue;
            }
            // Carbon 3: diffInSeconds() returns a float — normalise to whole seconds.
            $incidentSeconds[] = (int) abs($incident->opened_at->diffInSeconds($acceptedAt));
        }

        $emergencySeconds = [];
        foreach ($emergencies as $emergency) {
            if ($emergency->triggered_at === null || $emergency->acknowledged_at === null) {
                continue;
            }
            $emergencySeconds[] = (int) abs($emergency->triggered_at->diffInSeconds($emergency->acknowledged_at));
        }

        $combined = array_merge($incidentSeconds, $emergencySeconds);

        return [
            'methodology' => 'incident: opened_at -> first AssignmentResponder.accepted_at; emergency: triggered_at -> acknowledged_at',
            'incident_dispatch' => $this->durationSummary($incidentSeconds),
            'emergency_acknowledge' => $this->durationSummary($emergencySeconds),
            'combined' => $this->durationSummary($combined),
        ];
    }

    /**
     * @param  list<int>  $seconds
     * @return array{measured: int, average: ?int, median: ?int}
     */
    private function durationSummary(array $seconds): array
    {
        return [
            'measured' => count($seconds),
            'average' => $seconds === [] ? null : (int) round(array_sum($seconds) / count($seconds)),
            'median' => $this->median($seconds),
        ];
    }

    /**
     * @param  list<int>  $values
     */
    private function median(array $values): ?int
    {
        if ($values === []) {
            return null;
        }
        sort($values);
        $count = count($values);
        $mid = intdiv($count, 2);

        return $count % 2 === 0
            ? (int) round(($values[$mid - 1] + $values[$mid]) / 2)
            : (int) $values[$mid];
    }

    /**
     * Top observation hotspots. Observations and Emergencies expose lat/lng but
     * Incidents carry no zone/site/coords column, so the period report groups by
     * camera_source_id (top cameras) — the only stable site-like grouping key.
     *
     * @param  \Illuminate\Support\Collection<int, Observation>  $observations
     * @return list<array{camera_source_id: ?string, count: int}>
     */
    private function topZones($observations): array
    {
        return $observations
            ->groupBy(static fn ($row): string => $row->camera_source_id ?? 'unknown')
            ->map(static fn ($group, string $key): array => [
                'camera_source_id' => $key === 'unknown' ? null : $key,
                'count' => $group->count(),
            ])
            ->sortByDesc('count')
            ->values()
            ->take(10)
            ->all();
    }

    /**
     * A coarse coordinate heatmap: counts grouped into ~0.01-degree lat/lng
     * cells (no zone/site column exists, only coords). Rows without coords are
     * dropped.
     *
     * @param  \Illuminate\Support\Collection<int, \Illuminate\Database\Eloquent\Model>  $rows
     * @return list<array{cell: string, lat: float, lng: float, count: int}>
     */
    private function heatmap($rows): array
    {
        return $rows
            ->filter(static fn ($row): bool => $row->lat !== null && $row->lng !== null)
            ->groupBy(static function ($row): string {
                $lat = round((float) $row->lat, 2);
                $lng = round((float) $row->lng, 2);

                return $lat.','.$lng;
            })
            ->map(static function ($group, string $key): array {
                [$lat, $lng] = explode(',', $key);

                return [
                    'cell' => $key,
                    'lat' => (float) $lat,
                    'lng' => (float) $lng,
                    'count' => $group->count(),
                ];
            })
            ->sortByDesc('count')
            ->values()
            ->take(50)
            ->all();
    }
}
