<?php

declare(strict_types=1);

namespace App\Domains\Insurance\Http\Resources;

use App\Domains\Insurance\Models\Claim;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Claim
 */
final class ClaimResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'organization_id' => $this->organization_id,
            'policy_id' => $this->policy_id,
            'filed_by' => $this->filed_by,
            'amount_cents' => $this->amount_cents,
            'currency' => $this->currency,
            'status' => $this->status->value,
            'description' => $this->description,
            'decided_by' => $this->decided_by,
            'decided_at' => $this->decided_at?->toIso8601String(),
            'metadata' => $this->metadata,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
