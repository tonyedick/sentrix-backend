<?php

declare(strict_types=1);

namespace App\Domains\Trip\Http\Controllers;

use App\Domains\Trip\DTOs\RoutePlan;
use App\Domains\Trip\Http\Requests\PlanRouteRequest;
use App\Domains\Trip\Services\RoutingService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

/**
 * Route planning for the Trips screen (Safest / Fastest). User-scoped, stateless.
 */
final class ConsumerRouteController extends Controller
{
    public function __construct(private readonly RoutingService $routing) {}

    public function plan(PlanRouteRequest $request): JsonResponse
    {
        $plans = $this->routing->plan(
            (float) $request->float('origin_lat'),
            (float) $request->float('origin_lng'),
            (float) $request->float('destination_lat'),
            (float) $request->float('destination_lng'),
        );

        return response()->json([
            'data' => array_map(static fn (RoutePlan $plan): array => $plan->toArray(), $plans),
        ]);
    }
}
