<?php

declare(strict_types=1);

namespace App\Domains\Ingest\Services;

use App\Domains\Incident\Events\IncidentOpened;
use App\Domains\Incident\Models\Incident;
use App\Domains\Incident\Support\Enums\IncidentStatus;
use App\Domains\Ingest\DTOs\IngestDetectionData;
use App\Domains\Ingest\DTOs\IngestSignalData;
use App\Domains\Ingest\DTOs\IngestVisionData;
use App\Domains\Ingest\Events\DetectionIngested;
use App\Domains\Ingest\Models\DetectionEvent;
use App\Domains\Ingest\Support\DecisionResult;
use App\Domains\Ingest\Support\Enums\DetectionSeverity;
use App\Domains\Ingest\Support\Enums\DetectionSource;
use App\Domains\Ingest\Support\Enums\IncidentOrigin;
use App\Domains\Ingest\Support\IngestResult;
use App\Domains\Organization\Models\Organization;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * The Ingest pipeline: turns raw detections / vision payloads / SafeSignals into
 * a persisted DetectionEvent and, when the DecisionEngine deems it actionable,
 * an Incident in the Incident domain.
 *
 * Cross-domain effects go through events: opening an incident fires the Incident
 * domain's IncidentOpened so its existing listeners (timeline projection,
 * notifications) run unchanged. A machine-opened incident has NO human opener
 * (opened_by = null) and carries origin = detection|signal.
 */
final readonly class IngestService
{
    public function __construct(private DecisionEngine $engine) {}

    /**
     * Native detection event (source = detection).
     */
    public function ingestDetection(IngestDetectionData $data): IngestResult
    {
        return DB::transaction(function () use ($data): IngestResult {
            $decision = $this->engine->assess($data->type, $data->confidence);

            $event = $this->persistEvent([
                'organization_id' => $data->organizationId,
                'source' => DetectionSource::Detection,
                'product' => $data->product,
                'camera_source_id' => $data->cameraSourceId,
                'type' => $data->type,
                'severity' => $decision->severity,
                'risk_score' => $decision->riskScore,
                'triggered' => $decision->triggered,
                'site' => $data->site,
                'zone' => $data->zone,
                'lat' => $data->lat,
                'lng' => $data->lng,
                'summary' => $this->humanize($data->type),
                'payload' => $data->payload,
            ]);

            return $this->finalize($event, $decision, IncidentOrigin::Detection);
        });
    }

    /**
     * Vision-provider payload (source = vision). Normalizes detections[] to a
     * single (type, confidence) by taking the highest-severity detection.
     */
    public function ingestVision(IngestVisionData $data): IngestResult
    {
        return DB::transaction(function () use ($data): IngestResult {
            [$type, $confidence] = $this->normalizeVision($data);

            $decision = $this->engine->assess($type, $confidence);

            $event = $this->persistEvent([
                'organization_id' => $data->organizationId,
                'source' => DetectionSource::Vision,
                'product' => $data->provider,
                'camera_source_id' => $data->cameraSourceId,
                'type' => $type,
                'severity' => $decision->severity,
                'risk_score' => $decision->riskScore,
                'triggered' => $decision->triggered,
                'site' => $data->site,
                'zone' => $data->zone,
                'lat' => $data->lat,
                'lng' => $data->lng,
                'summary' => $this->humanize($type),
                'payload' => [
                    'provider' => $data->provider,
                    'detections' => $data->detections,
                    'behavior' => $data->behavior,
                ],
            ]);

            return $this->finalize($event, $decision, IncidentOrigin::Detection);
        });
    }

    /**
     * SafeSignal cross-product report (source = signal). Life-safety: an explicit
     * severity is honored verbatim; otherwise the engine assesses the type.
     */
    public function ingestSignal(IngestSignalData $data, Organization $organization): IngestResult
    {
        return DB::transaction(function () use ($data, $organization): IngestResult {
            $decision = $this->decideSignal($data);

            $event = $this->persistEvent([
                'organization_id' => $organization->getKey(),
                'source' => DetectionSource::Signal,
                'product' => $data->product,
                'camera_source_id' => null,
                'type' => $data->type,
                'severity' => $decision->severity,
                'risk_score' => $decision->riskScore,
                'triggered' => $decision->triggered,
                'site' => $data->site,
                'zone' => $data->zone,
                'lat' => $data->lat,
                'lng' => $data->lng,
                'summary' => $data->summary !== '' ? $data->summary : $this->humanize($data->type),
                'payload' => array_merge($data->payload, ['subjects' => $data->subjects]),
            ]);

            return $this->finalize($event, $decision, IncidentOrigin::Signal);
        });
    }

    /**
     * Persist a detection_events row from the given attributes.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function persistEvent(array $attributes): DetectionEvent
    {
        $attributes['received_at'] = now();

        return DetectionEvent::query()->create($attributes);
    }

    /**
     * Open an incident if the decision triggered, link it back onto the event,
     * and emit the lightweight DetectionIngested event.
     */
    private function finalize(DetectionEvent $event, DecisionResult $decision, IncidentOrigin $origin): IngestResult
    {
        $incident = null;

        if ($decision->triggered) {
            $incident = $this->openIncident($event, $decision, $origin);

            $event->forceFill([
                'incident_id' => $incident->getKey(),
                'triggered' => true,
            ])->save();
        }

        event(new DetectionIngested($event, $incident?->getKey()));

        return new IngestResult($event, $incident);
    }

    /**
     * Open a machine-attributed incident: opened_by = null, origin = detection|
     * signal. `origin` is NOT in the Incident model's $fillable, so it is set via
     * forceFill after the fillable create (the migration's default keeps existing
     * code on 'human'). Fires IncidentOpened so the Incident domain's timeline +
     * notification listeners run.
     */
    private function openIncident(DetectionEvent $event, DecisionResult $decision, IncidentOrigin $origin): Incident
    {
        $severity = $decision->severity->toIncidentSeverity();
        $title = $this->humanize($event->type);

        /** @var Incident $incident */
        $incident = Incident::query()->create([
            'organization_id' => $event->organization_id,
            'opened_by' => null,
            'status' => IncidentStatus::Open,
            'severity' => $severity,
            'title' => $title,
            'summary' => $event->summary,
            'opened_at' => now(),
            'metadata' => [
                'source' => 'ingest.'.$event->source->value,
                'origin' => $origin->value,
                'detection_event_id' => $event->getKey(),
                'camera_source_id' => $event->camera_source_id,
                'type' => $event->type,
                'risk_score' => $decision->riskScore,
            ],
        ]);

        // origin is not fillable on the Incident model — set it explicitly.
        $incident->forceFill(['origin' => $origin->value])->save();

        event(new IncidentOpened($incident, null, [
            'status' => $incident->status->value,
            'severity' => $incident->severity->value,
            'origin' => $origin->value,
            'source' => 'ingest.'.$event->source->value,
            'risk_score' => $decision->riskScore,
        ]));

        return $incident;
    }

    /**
     * SafeSignal decision: honor an explicit, valid severity; else assess.
     */
    private function decideSignal(IngestSignalData $data): DecisionResult
    {
        $assessed = $this->engine->assess($data->type, null);

        if ($data->severity === null) {
            return $assessed;
        }

        $explicit = DetectionSeverity::tryFrom(Str::lower($data->severity));

        if ($explicit === null) {
            return $assessed;
        }

        // Honor the explicit severity but keep a sensible risk floor for it.
        $risk = max($assessed->riskScore, $this->riskFloor($explicit));

        return new DecisionResult($risk, $explicit, $explicit->isActionable());
    }

    /**
     * Highest-severity detection from a vision payload → (type, confidence).
     *
     * @return array{0: string|null, 1: float|null}
     */
    private function normalizeVision(IngestVisionData $data): array
    {
        $best = null;
        $bestRisk = -1;

        foreach ($data->detections as $detection) {
            $label = is_string($detection['label'] ?? null) ? (string) $detection['label'] : null;
            $confidence = isset($detection['confidence']) ? (float) $detection['confidence'] : null;

            $assessed = $this->engine->assess($label, $confidence);

            if ($assessed->riskScore > $bestRisk) {
                $bestRisk = $assessed->riskScore;
                $best = [$label, $confidence];
            }
        }

        // No detections: fall back to a behavior label, if any.
        if ($best === null) {
            return [$data->behavior, null];
        }

        return $best;
    }

    private function riskFloor(DetectionSeverity $severity): int
    {
        return match ($severity) {
            DetectionSeverity::Critical => 85,
            DetectionSeverity::High => 70,
            DetectionSeverity::Medium => 40,
            DetectionSeverity::Low => 20,
            DetectionSeverity::Info => 0,
        };
    }

    /**
     * Turn a snake/kebab type into a human title, e.g. weapon_detected →
     * "Weapon detected".
     */
    private function humanize(?string $type): string
    {
        $type = trim((string) $type);

        if ($type === '') {
            return 'Detection event';
        }

        return Str::ucfirst(str_replace(['_', '-'], ' ', Str::lower($type)));
    }
}
