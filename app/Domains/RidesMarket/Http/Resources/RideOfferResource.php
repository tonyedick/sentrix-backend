<?php

declare(strict_types=1);

namespace App\Domains\RidesMarket\Http\Resources;

use App\Domains\RidesMarket\Models\RideOffer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin RideOffer
 */
final class RideOfferResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'origin' => [
                'label' => $this->origin_label,
                'lat' => $this->origin_lat,
                'lng' => $this->origin_lng,
            ],
            'destination' => [
                'label' => $this->dest_label,
                'lat' => $this->dest_lat,
                'lng' => $this->dest_lng,
            ],
            'distance_km' => $this->distance_km,
            'proposed_fare_cents' => $this->proposed_fare_cents,
            'fair_estimate_cents' => $this->fair_estimate_cents,
            'pricing_flag' => $this->pricing_flag->value,
            'status' => $this->status->value,
            'matched_ride_id' => $this->matched_ride_id,
            'bids' => RideBidResource::collection($this->whenLoaded('bids')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
