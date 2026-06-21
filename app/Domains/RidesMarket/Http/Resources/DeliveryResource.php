<?php

declare(strict_types=1);

namespace App\Domains\RidesMarket\Http\Resources;

use App\Domains\RidesMarket\Models\Delivery;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Delivery
 */
final class DeliveryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'parcel_size' => $this->parcel_size->value,
            'pickup' => [
                'label' => $this->pickup_label,
                'lat' => $this->pickup_lat,
                'lng' => $this->pickup_lng,
            ],
            'dropoff' => [
                'label' => $this->dropoff_label,
                'lat' => $this->dropoff_lat,
                'lng' => $this->dropoff_lng,
            ],
            'distance_km' => $this->distance_km,
            'fare_cents' => $this->fare_cents,
            'cod_amount_cents' => $this->cod_amount_cents,
            'payment_method' => $this->payment_method->value,
            'status' => $this->status->value,
            'recipient_name' => $this->recipient_name,
            'recipient_phone' => $this->recipient_phone,
            'driver_name' => $this->driver_name,
            'match_code' => $this->match_code,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
