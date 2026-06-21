<?php

declare(strict_types=1);

namespace App\Domains\RidesOps\Http\Resources;

use App\Domains\Rides\Models\Ride;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Ops projection of a Ride for the operations roster (cross-rider view). Money
 * is integer cents.
 *
 * @mixin Ride
 */
final class OpsRideResource extends JsonResource
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
            'origin_label' => $this->origin_label,
            'dest_label' => $this->dest_label,
            'distance_km' => $this->distance_km,
            'fare_estimate_cents' => $this->fare_estimate_cents,
            'final_fare_cents' => $this->final_fare_cents,
            'currency' => $this->currency,
            'surge_multiplier' => $this->surge_multiplier,
            'driver_name' => $this->driver_name,
            'driver_plate' => $this->driver_plate,
            'cancel_reason' => $this->cancel_reason,
            'requested_at' => $this->requested_at?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'cancelled_at' => $this->cancelled_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
