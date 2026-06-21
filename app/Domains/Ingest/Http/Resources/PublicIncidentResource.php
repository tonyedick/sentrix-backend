<?php

declare(strict_types=1);

namespace App\Domains\Ingest\Http\Resources;

use App\Domains\Ingest\Models\DetectionEvent;
use App\Domains\Ingest\Support\Enums\DetectionSeverity;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

/**
 * The privacy-first PUBLIC projection of an incident-bearing detection. Modeled
 * on Omni's public-safety feed: it exposes ONLY coarse, non-identifying signals.
 *
 * Emitted: an opaque short ref (never the real id), a coarse category, a
 * softened severity band (the public never sees raw "critical"), coordinates
 * coarsened to ~1 km (rounded to 2 dp), a coarse area label (site/zone only),
 * and the date (no exact timestamp).
 *
 * NEVER emitted: organization id/name, the precise summary/title text, exact
 * coordinates, camera ids, subjects/PII, risk scores, or internal references.
 *
 * @mixin DetectionEvent
 */
final class PublicIncidentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'ref' => Str::upper(Str::substr((string) $this->incident_id, -6)),
            'category' => $this->category(),
            'severity' => $this->softSeverity(),
            'area' => $this->coarseArea(),
            'coords' => $this->coarseCoords(),
            'date' => $this->received_at?->toDateString(),
        ];
    }

    /**
     * A coarse, public-safe category derived from the detection type.
     */
    private function category(): string
    {
        $type = Str::lower((string) $this->type);

        return match (true) {
            $type === '' => 'other',
            Str::contains($type, ['weapon', 'gun', 'firearm', 'knife', 'gunshot']) => 'weapon',
            Str::contains($type, ['fire', 'smoke']) => 'fire',
            Str::contains($type, ['intrusion', 'perimeter', 'breach', 'unauthorized']) => 'intrusion',
            Str::contains($type, ['crowd', 'stampede']) => 'crowd',
            Str::contains($type, ['fall']) => 'medical',
            Str::contains($type, ['loiter']) => 'suspicious_activity',
            Str::contains($type, ['sos', 'distress', 'scream']) => 'distress',
            default => 'other',
        };
    }

    /**
     * Soften the severity: the public never sees the operational "critical"
     * (mapped to high) nor the log-only "info" (mapped to low).
     */
    private function softSeverity(): string
    {
        return match ($this->severity) {
            DetectionSeverity::Critical => 'high',
            DetectionSeverity::High => 'high',
            DetectionSeverity::Medium => 'medium',
            DetectionSeverity::Low, DetectionSeverity::Info => 'low',
        };
    }

    /**
     * Only a coarse area label — never the exact site string when it could be an
     * address. We expose zone/site verbatim only as a neighbourhood-level hint.
     */
    private function coarseArea(): ?string
    {
        return $this->zone ?? $this->site;
    }

    /**
     * Coordinates coarsened to ~1.1 km (rounded to 2 dp), per public-safety.js.
     *
     * @return array{lat: float, lng: float}|null
     */
    private function coarseCoords(): ?array
    {
        if ($this->lat === null || $this->lng === null) {
            return null;
        }

        return [
            'lat' => round((float) $this->lat, 2),
            'lng' => round((float) $this->lng, 2),
        ];
    }
}
