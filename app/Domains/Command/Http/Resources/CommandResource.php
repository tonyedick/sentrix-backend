<?php

declare(strict_types=1);

namespace App\Domains\Command\Http\Resources;

use App\Domains\Command\Models\Command;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Command
 */
final class CommandResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'agency_id' => $this->agency_id,
            'parent_id' => $this->parent_id,
            'tier' => $this->tier->value,
            'name' => $this->name,
            'area' => $this->area,
            'lat' => $this->lat,
            'lng' => $this->lng,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
