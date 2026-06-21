<?php

declare(strict_types=1);

namespace App\Domains\Ledger\Http\Resources;

use App\Domains\Ledger\Models\LedgerWrite;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin LedgerWrite
 */
final class LedgerWriteResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'ledger_source_id' => $this->ledger_source_id,
            'type' => $this->type,
            'summary' => $this->summary,
            'ref' => $this->ref,
            'organization_id' => $this->organization_id,
            'recorded_at' => $this->recorded_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
