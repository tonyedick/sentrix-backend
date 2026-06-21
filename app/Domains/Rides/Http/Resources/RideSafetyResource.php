<?php

declare(strict_types=1);

namespace App\Domains\Rides\Http\Resources;

use App\Domains\Rides\Models\RideSafety;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin RideSafety
 */
final class RideSafetyResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'ride_id' => $this->ride_id,
            'armed' => $this->armed,
            'recording' => $this->recording,
            'guardians_notified' => $this->guardians_notified,
            'off_route' => $this->off_route,
            'overdue' => $this->overdue,
            'check_in_due' => $this->check_in_due,
            'evidence_count' => $this->evidence_count,
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
