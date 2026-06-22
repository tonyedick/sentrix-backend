<?php

declare(strict_types=1);

namespace App\Domains\Community\Http\Resources;

use App\Domains\Community\Models\CommunityAlert;
use App\Domains\Community\Support\Enums\AlertSource;
use App\Domains\Community\Support\Enums\AlertStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin CommunityAlert
 */
final class CommunityAlertResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'category' => $this->category->value,
            'title' => $this->title,
            'note' => $this->note,
            'impact' => $this->impact->value,
            'status' => $this->status->value,
            'source' => ($this->source ?? AlertSource::Community)->value,
            'verified' => ($this->source ?? AlertSource::Community)->isTrusted() || $this->status === AlertStatus::Active,
            'confidence' => $this->confidence,
            'location' => [
                'lat' => $this->lat,
                'lng' => $this->lng,
            ],
            'confirmations_count' => $this->confirmations_count,
            'dismissals_count' => $this->dismissals_count,
            'reporter_id' => $this->reporter_id,
            'mine' => $request->user() !== null && $this->reporter_id === $request->user()->getKey(),
            // Present only on nearby queries (selectRaw distance_m), in metres.
            'distance_m' => $this->when($this->distance_m !== null, fn () => (float) $this->distance_m),
            'expires_at' => $this->expires_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
