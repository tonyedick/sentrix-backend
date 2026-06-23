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
use Illuminate\Support\Facades\DB;

/**
 * The Intel reporting/analytics aggregation layer. Read-only: it rolls up over
 * the operational domains (incidents, emergencies, trips, evidence) and owns no
 * primary entity. Every query is scoped by organization_id.
 *
 * Aggregation happens in SQL (GROUP BY / date_trunc / percentile_cont / a
 * round()-grid heatmap) rather than by loading rows into PHP — so memory and
 * latency stay flat as data volume grows. The response shape is unchanged from
 * the previous in-PHP implementation (see IntelReportingTest).
 *
 * Response-time methodology (documented):
 *   - Incidents: opened_at -> earliest AssignmentResponder.accepted_at for that
 *     incident (the cleanest per-incident dispatch timestamp the models expose).
 *   - Emergencies: triggered_at -> acknowledged_at.
 * Durations are whole-second integers; average/median are null when unmeasured.
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
        $orgId = (string) $organization->getKey();
        $sinceTs = $since->toDateTimeString();

        $incidents = (new Incident)->getTable();
        $emergencies = (new Emergency)->getTable();
        $trips = (new Trip)->getTable();
        $observations = (new Observation)->getTable();

        return [
            'range' => [
                'since' => $since->toIso8601String(),
                'until' => Carbon::now()->toIso8601String(),
            ],
            'incidents' => [
                'total' => $this->total($incidents, $orgId, 'opened_at', $sinceTs),
                'by_severity' => $this->grouped($incidents, $orgId, 'opened_at', $sinceTs, 'severity'),
                'by_status' => $this->grouped($incidents, $orgId, 'opened_at', $sinceTs, 'status'),
            ],
            'emergencies' => [
                'total' => $this->total($emergencies, $orgId, 'triggered_at', $sinceTs),
                'by_status' => $this->grouped($emergencies, $orgId, 'triggered_at', $sinceTs, 'status'),
            ],
            'trips' => [
                'total' => $this->total($trips, $orgId, 'created_at', $sinceTs),
            ],
            'observations' => [
                'total' => $this->total($observations, $orgId, 'observed_at', $sinceTs),
            ],
            'response_time_seconds' => $this->responseTimes($orgId, $sinceTs),
            'top_zones' => $this->topZones($orgId, $sinceTs),
        ];
    }

    /**
     * Trend series + breakdowns + a coarse coordinate heatmap.
     *
     * @return array<string, mixed>
     */
    public function analytics(Organization $organization, CarbonInterface $since, string $bucket): array
    {
        $orgId = (string) $organization->getKey();
        $sinceTs = $since->toDateTimeString();

        $incidents = (new Incident)->getTable();
        $emergencies = (new Emergency)->getTable();
        $observations = (new Observation)->getTable();

        return [
            'range' => [
                'since' => $since->toIso8601String(),
                'until' => Carbon::now()->toIso8601String(),
                'bucket' => $bucket,
            ],
            'trends' => [
                'incidents' => $this->trend($incidents, $orgId, 'opened_at', $sinceTs, $bucket),
                'emergencies' => $this->trend($emergencies, $orgId, 'triggered_at', $sinceTs, $bucket),
            ],
            'breakdowns' => [
                'incidents_by_severity' => $this->grouped($incidents, $orgId, 'opened_at', $sinceTs, 'severity'),
                'emergencies_by_severity' => $this->grouped($emergencies, $orgId, 'triggered_at', $sinceTs, 'severity'),
                'observations_by_kind' => $this->grouped($observations, $orgId, 'observed_at', $sinceTs, 'kind'),
            ],
            'heatmap' => $this->heatmap($orgId, $sinceTs),
        ];
    }

    /** Row count for a table over [since, now], org-scoped. */
    private function total(string $table, string $orgId, string $tsColumn, string $sinceTs): int
    {
        return (int) DB::scalar(
            "select count(*) from {$table} where organization_id = ? and {$tsColumn} >= ?",
            [$orgId, $sinceTs],
        );
    }

    /**
     * Count grouped by a column (null -> 'unknown'), keyed by the value.
     *
     * @return array<string, int>
     */
    private function grouped(string $table, string $orgId, string $tsColumn, string $sinceTs, string $column): array
    {
        $rows = DB::select(
            "select coalesce({$column}::text, 'unknown') as k, count(*) as c "
            ."from {$table} where organization_id = ? and {$tsColumn} >= ? group by 1",
            [$orgId, $sinceTs],
        );

        $out = [];
        foreach ($rows as $row) {
            $out[$row->k] = (int) $row->c;
        }

        return $out;
    }

    /**
     * Time-bucketed counts (oldest first). Bucket strings match the previous
     * implementation: "YYYY-MM-DD" by day, "YYYY-MM-DDTHH:00" by hour.
     *
     * @return list<array{bucket: string, count: int}>
     */
    private function trend(string $table, string $orgId, string $tsColumn, string $sinceTs, string $bucket): array
    {
        $trunc = $bucket === 'hour' ? 'hour' : 'day';
        $fmt = $bucket === 'hour' ? 'YYYY-MM-DD"T"HH24":00"' : 'YYYY-MM-DD';

        $rows = DB::select(
            "select to_char(date_trunc('{$trunc}', {$tsColumn}), '{$fmt}') as bucket, count(*) as c "
            ."from {$table} where organization_id = ? and {$tsColumn} >= ? and {$tsColumn} is not null "
            .'group by 1 order by 1',
            [$orgId, $sinceTs],
        );

        return array_map(
            static fn ($row): array => ['bucket' => $row->bucket, 'count' => (int) $row->c],
            $rows,
        );
    }

    /**
     * Response-time stats computed in SQL.
     *
     * @return array<string, mixed>
     */
    private function responseTimes(string $orgId, string $sinceTs): array
    {
        $incidents = (new Incident)->getTable();
        $emergencies = (new Emergency)->getTable();
        $responders = (new AssignmentResponder)->getTable();

        $incidentSeconds = "select abs(extract(epoch from (min(ar.accepted_at) - i.opened_at))) as secs "
            ."from {$incidents} i "
            ."join {$responders} ar on ar.incident_id = i.id and ar.accepted_at is not null "
            .'where i.organization_id = ? and i.opened_at >= ? and i.opened_at is not null '
            .'group by i.id, i.opened_at';

        $emergencySeconds = 'select abs(extract(epoch from (acknowledged_at - triggered_at))) as secs '
            ."from {$emergencies} "
            .'where organization_id = ? and triggered_at >= ? '
            .'and triggered_at is not null and acknowledged_at is not null';

        return [
            'methodology' => 'incident: opened_at -> first AssignmentResponder.accepted_at; emergency: triggered_at -> acknowledged_at',
            'incident_dispatch' => $this->durationStats($incidentSeconds, [$orgId, $sinceTs]),
            'emergency_acknowledge' => $this->durationStats($emergencySeconds, [$orgId, $sinceTs]),
            'combined' => $this->durationStats(
                "({$incidentSeconds}) union all ({$emergencySeconds})",
                [$orgId, $sinceTs, $orgId, $sinceTs],
            ),
        ];
    }

    /**
     * count / average / median over a subquery that yields a single `secs`
     * column. average and median are null when nothing was measured.
     *
     * @param  list<mixed>  $bindings
     * @return array{measured: int, average: ?int, median: ?int}
     */
    private function durationStats(string $secondsSql, array $bindings): array
    {
        $row = DB::selectOne(
            'select count(*) as measured, '
            .'round(avg(secs))::int as average, '
            .'round(percentile_cont(0.5) within group (order by secs))::int as median '
            ."from ({$secondsSql}) as t",
            $bindings,
        );

        return [
            'measured' => (int) ($row->measured ?? 0),
            'average' => isset($row->average) ? (int) $row->average : null,
            'median' => isset($row->median) ? (int) $row->median : null,
        ];
    }

    /**
     * Top observation hotspots by camera (the only stable site-like key).
     *
     * @return list<array{camera_source_id: ?string, count: int}>
     */
    private function topZones(string $orgId, string $sinceTs): array
    {
        $observations = (new Observation)->getTable();

        $rows = DB::select(
            "select camera_source_id, count(*) as c from {$observations} "
            .'where organization_id = ? and observed_at >= ? '
            .'group by camera_source_id order by c desc, camera_source_id nulls last limit 10',
            [$orgId, $sinceTs],
        );

        return array_map(
            static fn ($row): array => [
                'camera_source_id' => $row->camera_source_id,
                'count' => (int) $row->c,
            ],
            $rows,
        );
    }

    /**
     * Coarse coordinate heatmap: counts grouped into ~0.01° lat/lng cells across
     * observations + emergencies. Rows without coords are dropped.
     *
     * @return list<array{cell: string, lat: float, lng: float, count: int}>
     */
    private function heatmap(string $orgId, string $sinceTs): array
    {
        $observations = (new Observation)->getTable();
        $emergencies = (new Emergency)->getTable();

        $sql = 'select cell, lat, lng, count(*) as c from ('
            .'select round(lat::numeric, 2)::float8 as lat, round(lng::numeric, 2)::float8 as lng, '
            ."round(lat::numeric, 2)::float8::text || ',' || round(lng::numeric, 2)::float8::text as cell "
            ."from {$observations} where organization_id = ? and observed_at >= ? and lat is not null and lng is not null "
            .'union all '
            .'select round(lat::numeric, 2)::float8, round(lng::numeric, 2)::float8, '
            ."round(lat::numeric, 2)::float8::text || ',' || round(lng::numeric, 2)::float8::text "
            ."from {$emergencies} where organization_id = ? and triggered_at >= ? and lat is not null and lng is not null"
            .') p group by cell, lat, lng order by c desc limit 50';

        $rows = DB::select($sql, [$orgId, $sinceTs, $orgId, $sinceTs]);

        return array_map(
            static fn ($row): array => [
                'cell' => $row->cell,
                'lat' => (float) $row->lat,
                'lng' => (float) $row->lng,
                'count' => (int) $row->c,
            ],
            $rows,
        );
    }
}
