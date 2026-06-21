<?php

declare(strict_types=1);

namespace App\Domains\Billing\Http\Resources;

use App\Domains\Billing\Models\Invoice;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Invoice
 */
final class InvoiceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'number' => $this->number,
            'plan_key' => $this->plan_key,
            'amount_cents' => $this->amount_cents,
            'amount' => number_format($this->amount_cents / 100, 2),
            'currency' => $this->currency,
            'status' => $this->status,
            'issued_at' => $this->issued_at?->toIso8601String(),
        ];
    }
}
