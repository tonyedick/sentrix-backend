<?php

declare(strict_types=1);

namespace App\Domains\Responder\Http\Resources;

use App\Domains\Responder\Models\ResponderLocation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ResponderLocation
 */
final class ResponderLocationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'responder_id' => $this->responder_id,
            'lat' => $this->lat,
            'lng' => $this->lng,
            'accuracy' => $this->accuracy,
            'speed' => $this->speed,
            'heading' => $this->heading,
            'recorded_at' => $this->recorded_at?->toIso8601String(),
        ];
    }
}
