<?php

declare(strict_types=1);

namespace App\Domains\Rides\Http\Resources;

use App\Domains\Rides\Models\RideEvidence;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin RideEvidence
 */
final class RideEvidenceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'ride_id' => $this->ride_id,
            'kind' => $this->kind->value,
            'url' => $this->url,
            'recorded_at' => $this->recorded_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
