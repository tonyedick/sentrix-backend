<?php

declare(strict_types=1);

namespace App\Domains\Rides\Http\Resources;

use App\Domains\Rides\Models\Ride;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Ride
 */
final class RideResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'ride_class' => $this->ride_class->value,
            'status' => $this->status->value,
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
            'fare_estimate_cents' => $this->fare_estimate_cents,
            'final_fare_cents' => $this->final_fare_cents,
            'tip_cents' => $this->tip_cents,
            'currency' => $this->currency,
            'surge_multiplier' => $this->surge_multiplier,
            'payment_method' => $this->payment_method->value,
            'match_code' => $this->match_code,
            'rating' => $this->rating,
            'cancel_reason' => $this->cancel_reason,
            'driver' => [
                'id' => $this->driver_id,
                'name' => $this->driver_name,
                'plate' => $this->driver_plate,
                'lat' => $this->driver_lat,
                'lng' => $this->driver_lng,
                'eta_minutes' => $this->driver_eta_minutes,
                'speed_kph' => $this->driver_speed_kph,
            ],
            'requested_at' => $this->requested_at?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'cancelled_at' => $this->cancelled_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
