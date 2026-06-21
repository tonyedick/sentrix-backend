<?php

declare(strict_types=1);

namespace App\Domains\RidesMarket\Http\Resources;

use App\Domains\RidesMarket\Models\RideBid;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin RideBid
 */
final class RideBidResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'ride_offer_id' => $this->ride_offer_id,
            'driver_id' => $this->driver_id,
            'driver_name' => $this->driver_name,
            'amount_cents' => $this->amount_cents,
            'kind' => $this->kind->value,
            'status' => $this->status->value,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
