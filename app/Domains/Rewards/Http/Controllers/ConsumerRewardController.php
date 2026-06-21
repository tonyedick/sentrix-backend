<?php

declare(strict_types=1);

namespace App\Domains\Rewards\Http\Controllers;

use App\Domains\Rewards\Http\Requests\RedeemPointsRequest;
use App\Domains\Rewards\Http\Resources\RewardAccountResource;
use App\Domains\Rewards\Http\Resources\RewardLedgerEntryResource;
use App\Domains\Rewards\Models\RewardLedgerEntry;
use App\Domains\Rewards\Services\RewardService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Consumer rewards (user-scoped, ADR-0001): balance, ledger, and redemption.
 * Earning happens server-side (e.g. verified reports) via RewardService::earn.
 */
final class ConsumerRewardController extends Controller
{
    public function __construct(private readonly RewardService $rewards) {}

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
}
