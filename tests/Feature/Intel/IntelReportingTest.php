<?php

declare(strict_types=1);

namespace Tests\Feature\Intel;

use App\Domains\Authorization\Database\Seeders\PermissionCatalogueSeeder;
use App\Domains\Emergency\Models\Emergency;
use App\Domains\Incident\Models\Incident;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

final class IntelReportingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionCatalogueSeeder::class);
    }

    /**
     * @return array{0: User, 1: string}
     */
    private function ownerWithOrganization(): array
    {
        $owner = User::factory()->create();
        $org = $this->actingAs($owner, 'sanctum')
            ->postJson('/api/v1/organizations', ['name' => 'Acme'])
            ->json('data.id');

        return [$owner, $org];
    }

    private function seedActivity(User $owner, string $org): void
    {
        $now = Carbon::now();

        Incident::query()->create([
            'organization_id' => $org,
            'opened_by' => $owner->getKey(),
            'status' => 'open',
            'severity' => 'high',
            'title' => 'Perimeter breach',
            'opened_at' => $now->copy()->subHours(2),
        ]);

        Incident::query()->create([
            'organization_id' => $org,
            'opened_by' => $owner->getKey(),
            'status' => 'resolved',
            'severity' => 'low',
            'title' => 'False alarm',
            'opened_at' => $now->copy()->subDays(2),
        ]);

        Emergency::query()->create([
            'organization_id' => $org,
            'user_id' => $owner->getKey(),
            'status' => 'acknowledged',
            'severity' => 'critical',
            'message' => 'Panic button',
            'triggered_at' => $now->copy()->subHours(1),
            'acknowledged_at' => $now->copy()->subHours(1)->addMinutes(3),
        ]);
    }

    public function test_report_returns_totals_breakdowns_and_response_times(): void
    {
        [$owner, $org] = $this->ownerWithOrganization();
        $this->seedActivity($owner, $org);

        $this->actingAs($owner, 'sanctum')
            ->getJson("/api/v1/organizations/{$org}/intel/reports?range=30d")
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'range' => ['since', 'until'],
                    'incidents' => ['total', 'by_severity', 'by_status'],
                    'emergencies' => ['total', 'by_status'],
                    'trips' => ['total'],
                    'observations' => ['total'],
                    'response_time_seconds' => [
                        'emergency_acknowledge' => ['measured', 'average', 'median'],
                    ],
                    'top_zones',
                ],
            ])
            ->assertJsonPath('data.incidents.total', 2)
            ->assertJsonPath('data.incidents.by_severity.high', 1)
            ->assertJsonPath('data.emergencies.total', 1)
            ->assertJsonPath('data.response_time_seconds.emergency_acknowledge.measured', 1);
    }

    public function test_analytics_returns_trend_series_and_breakdowns(): void
    {
        [$owner, $org] = $this->ownerWithOrganization();
        $this->seedActivity($owner, $org);

        $this->actingAs($owner, 'sanctum')
            ->getJson("/api/v1/organizations/{$org}/intel/analytics?range=30d&bucket=day")
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'range' => ['since', 'until', 'bucket'],
                    'trends' => ['incidents', 'emergencies'],
                    'breakdowns' => [
                        'incidents_by_severity',
                        'emergencies_by_severity',
                        'observations_by_kind',
                    ],
                    'heatmap',
                ],
            ])
            ->assertJsonPath('data.range.bucket', 'day')
            ->assertJsonPath('data.breakdowns.incidents_by_severity.high', 1);
    }

    public function test_export_csv_streams_a_csv_download(): void
    {
        [$owner, $org] = $this->ownerWithOrganization();
        $this->seedActivity($owner, $org);

        $response = $this->actingAs($owner, 'sanctum')
            ->get("/api/v1/organizations/{$org}/intel/reports/export?range=30d&format=csv");

        $response->assertOk();
        $this->assertStringContainsString('text/csv', (string) $response->headers->get('Content-Type'));
        $this->assertStringContainsString('attachment', (string) $response->headers->get('Content-Disposition'));

        $body = $response->streamedContent();
        $this->assertStringContainsString('metric,key,value', $body);
    }

    public function test_outsider_is_forbidden(): void
    {
        [, $org] = $this->ownerWithOrganization();
        $outsider = User::factory()->create();

        $this->actingAs($outsider, 'sanctum')
            ->getJson("/api/v1/organizations/{$org}/intel/reports?range=7d")
            ->assertForbidden();
    }
}
