<?php

declare(strict_types=1);

namespace App\Domains\Retention\Http\Resources;

use App\Domains\Retention\Models\RetentionExport;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin RetentionExport
 */
final class RetentionExportResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'organization_id' => $this->organization_id,
            'exported_by' => $this->exported_by,
            'format' => $this->format->value,
            'count' => $this->count,
            'manifest' => $this->manifest,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
