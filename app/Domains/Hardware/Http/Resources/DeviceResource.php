<?php

declare(strict_types=1);

namespace App\Domains\Hardware\Http\Resources;

use App\Domains\Hardware\Models\Device;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Device
 */
final class DeviceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'organization_id' => $this->organization_id,
            'registered_by' => $this->registered_by,
            'kind' => $this->kind->value,
            'serial' => $this->serial,
            'name' => $this->name,
            'site' => $this->site,
            'zone' => $this->zone,
            'status' => $this->status->value,
            'last_seen_at' => $this->last_seen_at?->toIso8601String(),
            'metadata' => $this->metadata,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
