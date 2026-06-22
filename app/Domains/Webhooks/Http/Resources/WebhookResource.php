<?php

declare(strict_types=1);

namespace App\Domains\Webhooks\Http\Resources;

use App\Domains\Webhooks\Models\Webhook;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Webhook
 */
final class WebhookResource extends JsonResource
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
            'url' => $this->url,
            'events' => $this->events,
            'secret' => $this->secret,
            'active' => $this->active,
            'description' => $this->description,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
