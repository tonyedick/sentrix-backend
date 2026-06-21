<?php

declare(strict_types=1);

namespace App\Domains\Wallet\Http\Resources;

use App\Domains\Wallet\Models\Wallet;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Wallet
 */
final class WalletResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'balance_cents' => $this->balance_cents,
            'currency' => $this->currency,
            'lifetime_topup_cents' => $this->lifetime_topup_cents,
            'transactions' => WalletTransactionResource::collection(
                $this->whenLoaded('transactions'),
            ),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
