<?php

declare(strict_types=1);

namespace Tests\Feature\Ingest;

use App\Domains\Authorization\Database\Seeders\PermissionCatalogueSeeder;
use App\Domains\Incident\Models\Incident;
use App\Domains\Incident\Support\Enums\IncidentSeverity;
use App\Domains\Ingest\Models\DetectionEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Covers the detection→decision→incident pipeline end-to-end via its machine
 * (service-token) endpoints, plus the anonymized public feed.
 */
final class IngestPipelineTest extends TestCase
{
    use RefreshDatabase;

    private const TOKEN = 'svc-secret';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionCatalogueSeeder::class);
        config(['sentrix.core.api_key' => self::TOKEN]);
    }

    /**
     * Create an org via the owner-creates-org pattern; return [id, slug].
     *
     * @return array{0: string, 1: string}
     */
    private function organization(): array
    {
        $owner = User::factory()->create();

        $data = $this->actingAs($owner, 'sanctum')
            ->postJson('/api/v1/organizations', ['name' => 'Acme Security'])
            ->json('data');

        return [(string) $data['id'], (string) $data['slug']];
    }

    /**
     * @param  array<string, mixed>  $body
     */
    private function postService(string $uri, array $body): \Illuminate\Testing\TestResponse
    {
        return $this->withHeaders(['X-Service-Token' => self::TOKEN])->postJson($uri, $body);
    }

    public function test_weapon_detection_opens_a_critical_machine_incident(): void
    {
        [$orgId] = $this->organization();

        $response = $this->postService('/api/v1/ingest/detections', [
            'organization_id' => $orgId,
            'camera_source_id' => (string) \Illuminate\Support\Str::uuid(),
            'type' => 'weapon_detected',
            'confidence' => 1.0,
            'site' => 'HQ',
            'zone' => 'Rear gate',
            'lat' => 6.5244,
            'lng' => 3.3792,
        ])->assertStatus(202);

        $response->assertJsonPath('data.triggered', true);

        $incident = Incident::query()->where('organization_id', $orgId)->firstOrFail();

        $this->assertSame(IncidentSeverity::Critical->value, $incident->severity->value);
        $this->assertNull($incident->opened_by);
        $this->assertSame('detection', DB::table('incidents')->where('id', $incident->getKey())->value('origin'));

        $event = DetectionEvent::query()->where('organization_id', $orgId)->firstOrFail();
        $this->assertTrue($event->triggered);
        $this->assertSame((string) $incident->getKey(), (string) $event->incident_id);
        // Whole-number risk must serialize as an int, not a float.
        $this->assertIsInt($event->risk_score);
        $this->assertSame(90, $event->risk_score);
    }

    public function test_low_confidence_loitering_records_event_without_incident(): void
    {
        [$orgId] = $this->organization();

        // loitering base 55 * 0.5 = ~28 → below the 40 trigger threshold.
        $this->postService('/api/v1/ingest/detections', [
            'organization_id' => $orgId,
            'type' => 'loitering',
            'confidence' => 0.5,
        ])->assertStatus(202)->assertJsonPath('data.triggered', false);

        $this->assertSame(0, Incident::query()->where('organization_id', $orgId)->count());
        $this->assertSame(1, DetectionEvent::query()->where('organization_id', $orgId)->count());

        $event = DetectionEvent::query()->where('organization_id', $orgId)->firstOrFail();
        $this->assertFalse($event->triggered);
        $this->assertIsInt($event->risk_score);
    }

    public function test_vision_payload_with_weapon_opens_incident(): void
    {
        [$orgId] = $this->organization();

        $this->postService('/api/v1/ingest/vision', [
            'organization_id' => $orgId,
            'provider' => 'acme-vision',
            'camera_source_id' => (string) \Illuminate\Support\Str::uuid(),
            'payload' => [
                'detections' => [
                    ['label' => 'person', 'confidence' => 0.9],
                    ['label' => 'gun', 'confidence' => 0.99],
                ],
                'behavior' => 'brandishing',
            ],
        ])->assertStatus(202)->assertJsonPath('data.triggered', true);

        $incident = Incident::query()->where('organization_id', $orgId)->firstOrFail();
        $this->assertSame(IncidentSeverity::Critical->value, $incident->severity->value);
        $this->assertNull($incident->opened_by);
    }

    public function test_signal_ingest_with_explicit_severity_opens_incident(): void
    {
        [$orgId, $slug] = $this->organization();

        // Resolve the org by SLUG to exercise the Str::isUuid guard.
        $this->postService('/api/v1/signal/ingest', [
            'organization_id' => $slug,
            'product' => 'go',
            'type' => 'panic_button',
            'severity' => 'high',
            'summary' => 'Lone worker pressed SOS',
            'lat' => 6.4,
            'lng' => 3.4,
        ])->assertStatus(202)->assertJsonPath('data.triggered', true);

        $incident = Incident::query()->where('organization_id', $orgId)->firstOrFail();
        $this->assertSame(IncidentSeverity::High->value, $incident->severity->value);
        $this->assertNull($incident->opened_by);
        $this->assertSame('signal', DB::table('incidents')->where('id', $incident->getKey())->value('origin'));
    }

    public function test_public_feed_is_anonymized_and_coarsened_without_auth(): void
    {
        [$orgId] = $this->organization();

        $this->postService('/api/v1/ingest/detections', [
            'organization_id' => $orgId,
            'type' => 'weapon_detected',
            'confidence' => 1.0,
            'lat' => 6.524389,
            'lng' => 3.379205,
            'zone' => 'Downtown',
        ])->assertStatus(202);

        $response = $this->getJson('/api/v1/public/incidents')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $row = $response->json('data.0');

        // Anonymized: no org id, no title/summary text.
        $this->assertArrayNotHasKey('organization_id', $row);
        $this->assertArrayNotHasKey('summary', $row);
        $this->assertArrayNotHasKey('title', $row);
        // Coarse category + softened severity (critical never leaks).
        $this->assertSame('weapon', $row['category']);
        $this->assertSame('high', $row['severity']);
        // Coords coarsened to 2 dp (~1 km).
        $this->assertSame(6.52, $row['coords']['lat']);
        $this->assertSame(3.38, $row['coords']['lng']);
    }

    public function test_ingest_without_service_token_is_unauthorized(): void
    {
        [$orgId] = $this->organization();

        $this->postJson('/api/v1/ingest/detections', [
            'organization_id' => $orgId,
            'type' => 'weapon_detected',
        ])->assertUnauthorized();
    }
}
