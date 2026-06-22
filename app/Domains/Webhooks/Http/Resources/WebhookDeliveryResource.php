<?php

declare(strict_types=1);

namespace App\Domains\Webhooks\Http\Resources;

use App\Domains\Webhooks\Models\WebhookDelivery;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin WebhookDelivery
 */
final class WebhookDeliveryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'webhook_id' => $this->webhook_id,
            'event' => $this->event,
            'payload' => $this->payload,
            'signature' => $this->signature,
            'status_code' => $this->status_code,
            'success' => $this->success,
            'error' => $this->error,
            'attempts' => $this->attempts,
            'delivered_at' => $this->delivered_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
