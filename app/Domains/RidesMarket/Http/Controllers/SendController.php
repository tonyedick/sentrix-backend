<?php

declare(strict_types=1);

namespace App\Domains\RidesMarket\Http\Controllers;

use App\Domains\RidesMarket\DTOs\BookDeliveryData;
use App\Domains\RidesMarket\DTOs\SendQuoteData;
use App\Domains\RidesMarket\Http\Requests\BookDeliveryRequest;
use App\Domains\RidesMarket\Http\Requests\SendQuoteRequest;
use App\Domains\RidesMarket\Http\Resources\DeliveryResource;
use App\Domains\RidesMarket\Services\RidesMarketService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sentrix Send — parcel delivery (user-scoped, ADR-0001). Quote by parcel size
 * then book a COD/wallet delivery with a simulated verified courier.
 */
final class SendController extends Controller
{
    public function __construct(private readonly RidesMarketService $market) {}

    /**
     * Computed quote: a fare scaled by parcel size. No persistence, no Resource.
     */
    public function quote(SendQuoteRequest $request): JsonResponse
    {
        return response()->json([
            'data' => $this->market->quoteDelivery(SendQuoteData::fromRequest($request)),
        ]);
    }

    public function book(BookDeliveryRequest $request): JsonResponse
    {
        $delivery = $this->market->bookDelivery($request->user(), BookDeliveryData::fromRequest($request));

        return DeliveryResource::make($delivery)
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }
}
