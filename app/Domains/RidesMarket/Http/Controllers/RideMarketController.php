<?php

declare(strict_types=1);

namespace App\Domains\RidesMarket\Http\Controllers;

use App\Domains\RidesMarket\DTOs\CreateOfferData;
use App\Domains\RidesMarket\DTOs\PlaceBidData;
use App\Domains\RidesMarket\Http\Requests\AcceptBidRequest;
use App\Domains\RidesMarket\Http\Requests\CreateOfferRequest;
use App\Domains\RidesMarket\Http\Requests\PlaceBidRequest;
use App\Domains\RidesMarket\Http\Resources\RideOfferResource;
use App\Domains\RidesMarket\Models\RideOffer;
use App\Domains\RidesMarket\Services\RidesMarketService;
use App\Domains\RidesMarket\Support\Enums\OfferStatus;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

/**
 * Safe Rides — name-your-price marketplace (user-scoped, ADR-0001). A rider posts
 * an offer; simulated verified drivers bid; the rider accepts one and a real Ride
 * is materialised. Record-level ownership is asserted on each {rideOffer} action.
 */
final class RideMarketController extends Controller
{
    public function __construct(private readonly RidesMarketService $market) {}

    public function store(CreateOfferRequest $request): JsonResponse
    {
        $offer = $this->market->createOffer($request->user(), CreateOfferData::fromRequest($request));

        return RideOfferResource::make($offer)
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function mine(Request $request): AnonymousResourceCollection
    {
        $offers = RideOffer::query()
            ->where('user_id', $request->user()->getKey())
            ->with('bids')
            ->latest('created_at')
            ->paginate($this->perPage($request));

        return RideOfferResource::collection($offers);
    }

    /**
     * The driver-facing board of OPEN offers. Any caller may view it for now;
     * real driver-gating uses DriverOnboarding active status later. Each offer
     * carries its fair_estimate for context (already on the Resource).
     */
    public function open(Request $request): AnonymousResourceCollection
    {
        $offers = RideOffer::query()
            ->where('status', OfferStatus::Open->value)
            ->with('bids')
            ->latest('created_at')
            ->paginate($this->perPage($request));

        return RideOfferResource::collection($offers);
    }

    /**
     * A driver bids on an open offer. SIMPLIFICATION: the caller is the
     * authenticated user but is assumed to be acting driver-side (no driver
     * identity yet — that lands with the Driver domain).
     */
    public function bid(PlaceBidRequest $request, RideOffer $rideOffer): JsonResponse
    {
        $this->market->placeBid($rideOffer, PlaceBidData::fromRequest($request));

        return RideOfferResource::make($rideOffer->refresh()->load('bids'))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * The rider accepts a bid on their own offer. Materialises a Ride and closes
     * the offer. Must be the offer owner (404 otherwise) and the offer must be open.
     */
    public function accept(AcceptBidRequest $request, RideOffer $rideOffer): JsonResponse
    {
        $this->assertOwned($request, $rideOffer);

        $result = $this->market->acceptBid($rideOffer, $request->string('bid_id')->value());

        return response()->json([
            'data' => [
                'offer' => RideOfferResource::make($result['offer']),
                'ride_id' => $result['ride_id'],
            ],
        ]);
    }

    private function assertOwned(Request $request, RideOffer $offer): void
    {
        abort_if($offer->user_id !== $request->user()->getKey(), Response::HTTP_NOT_FOUND);
    }
}
