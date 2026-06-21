<?php

declare(strict_types=1);

use App\Domains\RidesMarket\Http\Controllers\RideMarketController;
use App\Domains\RidesMarket\Http\Controllers\SendController;
use Illuminate\Support\Facades\Route;

/*
 | Safe Rides — Marketplace (name-your-price) & Sentrix Send (parcel delivery).
 | User-scoped (ADR-0001); lives under the rides prefix alongside booking/safety.
 | No organization, no permission catalogue. ALL MONEY IS INTEGER CENTS.
 |
 | Static segments (market/offers/mine, market/offers/open, send/*) are declared
 | before the {rideOffer} wildcard so they aren't shadowed by route-model binding.
 */
Route::middleware('auth:sanctum')->prefix('v1/me/rides')->group(function (): void {
    // Marketplace.
    Route::post('market/offers', [RideMarketController::class, 'store'])->name('me.rides.market.offers.store');
    Route::get('market/offers/mine', [RideMarketController::class, 'mine'])->name('me.rides.market.offers.mine');
    Route::get('market/offers/open', [RideMarketController::class, 'open'])->name('me.rides.market.offers.open');
    Route::post('market/offers/{rideOffer}/bid', [RideMarketController::class, 'bid'])->name('me.rides.market.offers.bid');
    Route::post('market/offers/{rideOffer}/accept', [RideMarketController::class, 'accept'])->name('me.rides.market.offers.accept');

    // Sentrix Send.
    Route::post('send/quote', [SendController::class, 'quote'])->name('me.rides.send.quote');
    Route::post('send/book', [SendController::class, 'book'])->name('me.rides.send.book');
});
