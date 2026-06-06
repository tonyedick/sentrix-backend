<?php

declare(strict_types=1);

namespace App\Domains\Notification\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Notifications\DatabaseNotification;

/**
 * @mixin DatabaseNotification
 */
final class NotificationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->data['type'] ?? class_basename($this->type),
            // Named `payload` (not `data`) to avoid colliding with the JsonResource
            // `data` wrapper — a top-level `data` key makes the resource look
            // already-wrapped and the real attributes get dropped from the envelope.
            'payload' => $this->data,
            'read' => $this->read_at !== null,
            'read_at' => $this->read_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
