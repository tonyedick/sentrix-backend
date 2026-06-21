<?php

declare(strict_types=1);

namespace App\Domains\Billing\Http\Resources;

use App\Domains\Billing\Models\Subscription;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Subscription
 */
final class SubscriptionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $plan = config("sentrix.billing.plans.{$this->plan_key}", []);

        return [
            'plan_key' => $this->plan_key,
            'plan_name' => $plan['name'] ?? $this->plan_key,
            'status' => $this->status,
            'auto_renew' => $this->auto_renew,
            'payment_method' => $this->payment_method_label,
            'current_period_end' => $this->current_period_end?->toIso8601String(),
            'entitlements' => $plan['entitlements'] ?? [],
        ];
    }
}
