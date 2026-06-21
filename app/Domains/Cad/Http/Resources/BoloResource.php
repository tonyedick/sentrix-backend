<?php

declare(strict_types=1);

namespace App\Domains\Cad\Http\Resources;

use App\Domains\Cad\Models\Bolo;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Bolo
 */
final class BoloResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'agency_id' => $this->agency_id,
            'command_id' => $this->command_id,
            'kind' => $this->kind->value,
            'subject' => $this->subject,
            'details' => $this->details,
            'status' => $this->status->value,
            'issued_by' => $this->issued_by,
            'issued_at' => $this->issued_at?->toIso8601String(),
            'cleared_at' => $this->cleared_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
