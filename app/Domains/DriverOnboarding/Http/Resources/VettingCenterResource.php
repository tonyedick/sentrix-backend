<?php

declare(strict_types=1);

namespace App\Domains\DriverOnboarding\Http\Resources;

use App\Domains\DriverOnboarding\Models\VettingCenter;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin VettingCenter
 */
final class VettingCenterResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'address' => $this->address,
            'location' => [
                'lat' => $this->lat,
                'lng' => $this->lng,
            ],
            'slots' => $this->slots,
        ];
    }
}
