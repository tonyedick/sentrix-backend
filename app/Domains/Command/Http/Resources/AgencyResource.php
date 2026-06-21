<?php

declare(strict_types=1);

namespace App\Domains\Command\Http\Resources;

use App\Domains\Command\Models\Agency;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Agency
 */
final class AgencyResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'country' => $this->country,
            'categories' => $this->categories,
            'hotline' => $this->hotline,
            'color' => $this->color,
            'logo_url' => $this->logo_url,
            'status' => $this->status->value,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
