<?php

declare(strict_types=1);

namespace App\Domains\Crm\Http\Resources;

use App\Domains\Crm\Models\Lead;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Lead
 */
final class LeadResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'client_type' => $this->client_type->value,
            'contact_name' => $this->contact_name,
            'contact_email' => $this->contact_email,
            'contact_phone' => $this->contact_phone,
            'region' => $this->region,
            'source' => $this->source,
            'stage' => $this->stage->value,
            'quote' => $this->quote,
            'notes' => $this->notes,
            'converted_organization_id' => $this->converted_organization_id,
            'created_by' => $this->created_by,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
