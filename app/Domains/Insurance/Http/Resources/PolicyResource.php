<?php

declare(strict_types=1);

namespace App\Domains\Insurance\Http\Resources;

use App\Domains\Insurance\Models\Policy;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Policy
 */
final class PolicyResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'organization_id' => $this->organization_id,
            'created_by' => $this->created_by,
            'title' => $this->title,
            'status' => $this->status->value,
            'premium_cents' => $this->premium_cents,
            'currency' => $this->currency,
            'coverage' => $this->coverage,
            'period_start' => $this->period_start?->toIso8601String(),
            'period_end' => $this->period_end?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
