<?php

declare(strict_types=1);

namespace App\Domains\Rewards\Http\Controllers;

use App\Domains\Rewards\Http\Requests\ConvertPointsRequest;
use App\Domains\Rewards\Http\Requests\RedeemPointsRequest;
use App\Domains\Rewards\Http\Resources\RewardAccountResource;
use App\Domains\Rewards\Http\Resources\RewardLedgerEntryResource;
use App\Domains\Rewards\Models\RewardLedgerEntry;
use App\Domains\Rewards\Services\RewardGamificationService;
use App\Domains\Rewards\Services\RewardService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Consumer rewards (user-scoped, ADR-0001): balance, ledger, and redemption.
 * Earning happens server-side (e.g. verified reports) via RewardService::earn.
 */
final class ConsumerRewardController extends Controller
{
    public function __construct(
        private readonly RewardService $rewards,
        private readonly RewardGamificationService $gamification,
    ) {}

    public function show(Request $request): RewardAccountResource
    {
        return RewardAccountResource::make($this->rewards->accountFor($request->user()));
    }

    public function ledger(Request $request): AnonymousResourceCollection
    {
        $entries = RewardLedgerEntry::query()
            ->where('user_id', $request->user()->getKey())
            ->latest('created_at')
            ->paginate($this->perPage($request));

        return RewardLedgerEntryResource::collection($entries);
    }

    public function redeem(RedeemPointsRequest $request): RewardAccountResource
    {
        $account = $this->rewards->redeem(
            $request->user(),
            $request->integer('points'),
            $request->input('reason'),
        );

        return RewardAccountResource::make($account);
    }

    /**
     * The caller's earned badges + progress (derived from activity).
     */
    public function badges(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->gamification->badgesFor($request->user())]);
    }

    /**
     * Top users by points with the caller's rank included. Read-only.
     */
    public function leaderboard(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->gamification->leaderboard($request->user())]);
    }

    /**
     * Available daily/weekly missions + the caller's progress.
     */
    public function missions(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->gamification->missionsFor($request->user())]);
    }

    /**
     * Convert points into Premium days (422 on shortfall).
     */
    public function convert(ConvertPointsRequest $request): JsonResponse
    {
        $result = $this->gamification->convertPointsToPremium(
            $request->user(),
            (string) $request->string('pack_id'),
        );

        return response()->json(['data' => [
            'days' => $result['days'],
            'premium_until' => $result['premium_until'],
            'points_balance' => $result['account']->points_balance,
            'premium_days_granted' => $result['account']->premium_days_granted,
        ]]);
    }

    /**
     * Record a daily active day (drives the streak; idempotent per calendar day).
     */
    public function recordActivity(Request $request): RewardAccountResource
    {
        return RewardAccountResource::make($this->gamification->recordDailyActivity($request->user()));
    }
}
