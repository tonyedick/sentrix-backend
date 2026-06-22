<?php

declare(strict_types=1);

namespace App\Domains\Rewards\Http\Resources;

use App\Domains\Rewards\Models\RewardAccount;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin RewardAccount
 */
final class RewardAccountResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'points_balance' => $this->points_balance,
            'boost_multiplier' => $this->boost_multiplier,
            'boost_active' => $this->boostActive(),
            'boost_expires_at' => $this->boost_expires_at?->toIso8601String(),
            'streak_days' => $this->streak_days,
            'last_activity_on' => $this->last_activity_on?->toDateString(),
            'premium_days_granted' => $this->premium_days_granted,
        ];
    }
}
