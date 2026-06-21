<?php

declare(strict_types=1);

namespace App\Domains\VisionGuard\Http\Resources;

use App\Domains\VisionGuard\Models\MediaAsset;
use App\Domains\VisionGuard\Services\VisionGuardService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin MediaAsset
 */
final class MediaAssetResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'camera_source_id' => $this->camera_source_id,
            'url' => app(VisionGuardService::class)->urlFor($this->resource),
            'content_type' => $this->content_type,
            'size_bytes' => $this->size_bytes,
            'status' => $this->status,
            'trip_id' => $this->trip_id,
            'emergency_id' => $this->emergency_id,
            'captured_at' => $this->captured_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
