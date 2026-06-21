<?php

declare(strict_types=1);

namespace App\Domains\Identity\Http\Resources;

use App\Domains\Identity\Models\SavedLocation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin SavedLocation
 */
final class SavedLocationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'label' => $this->label,
            'kind' => $this->kind,
            'address' => $this->address,
            'location' => ['lat' => $this->lat, 'lng' => $this->lng],
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
