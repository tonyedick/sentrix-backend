<?php

declare(strict_types=1);

namespace App\Domains\Places\Http\Resources;

use App\Domains\Places\Models\Place;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Place
 */
final class PlaceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'category' => $this->category->value,
            'rating' => $this->rating,
            'reviews_count' => $this->reviews_count,
            'open_24_7' => $this->is_24_7,
            'hours' => $this->is_24_7
                ? 'Open 24/7'
                : ($this->opens_at !== null && $this->closes_at !== null
                    ? substr((string) $this->opens_at, 0, 5).'–'.substr((string) $this->closes_at, 0, 5)
                    : null),
            'phone' => $this->phone,
            'address' => $this->address,
            'location' => [
                'lat' => $this->lat,
                'lng' => $this->lng,
            ],
            'distance_m' => $this->when($this->distance_m !== null, fn () => (float) $this->distance_m),
        ];
    }
}
