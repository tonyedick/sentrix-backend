<?php

declare(strict_types=1);

namespace App\Domains\Wallet\Http\Resources;

use App\Domains\Wallet\Models\PaymentMethod;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin PaymentMethod
 */
final class PaymentMethodResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'kind' => $this->kind->value,
            'label' => $this->label,
            'brand' => $this->brand,
            'last4' => $this->last4,
            'is_default' => $this->is_default,
            'removable' => $this->removable,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
