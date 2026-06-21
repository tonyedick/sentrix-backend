<?php

declare(strict_types=1);

namespace App\Domains\Billing\Http\Controllers;

use App\Domains\Billing\Services\SubscriptionService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

/**
 * The plan catalogue (Choose a plan screen). Config-driven, read-only.
 */
final class PlanController extends Controller
{
    public function __construct(private readonly SubscriptionService $subscriptions) {}

    public function index(): JsonResponse
    {
        $plans = [];
        foreach ($this->subscriptions->catalogue() as $key => $plan) {
            $plans[] = [
                'key' => $key,
                'name' => $plan['name'],
                'price_cents' => $plan['price_cents'],
                'price' => number_format(((int) $plan['price_cents']) / 100, 2),
                'currency' => config('sentrix.billing.currency', 'USD'),
                'interval' => $plan['interval'],
                'popular' => (bool) ($plan['popular'] ?? false),
                'entitlements' => $plan['entitlements'] ?? [],
            ];
        }

        return response()->json(['data' => $plans]);
    }
}
