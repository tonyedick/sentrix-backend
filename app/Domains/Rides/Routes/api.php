<?php

declare(strict_types=1);

use App\Domains\Rides\Http\Controllers\RideController;
use App\Domains\Rides\Http\Controllers\RideSafetyController;
use Illuminate\Support\Facades\Route;

/*
 | Safe Rides — ride booking/lifecycle + in-ride safety (user-scoped, ADR-0001).
 | The {ride} wildcard is constrained to UUIDs (whereUuid) so it never shadows
 | literal sibling routes registered by OTHER domains under the same
 | `v1/me/rides` prefix (e.g. Wallet's `payment-methods`, `pay/*`, `referral/*`),
 | regardless of provider load order. Within-file ordering alone can't prevent a
 | cross-file collision; the UUID constraint does.
 */
Route::middleware('auth:sanctum')->prefix('v1/me/rides')->whereUuid('ride')->group(function (): void {
    Route::post('quote', [RideController::class, 'quote'])->name('me.rides.quote');
    Route::post('request', [RideController::class, 'request'])->name('me.rides.request');
    Route::get('cancel-reasons', [RideController::class, 'cancelReasons'])->name('me.rides.cancel-reasons');
    Route::get('/', [RideController::class, 'index'])->name('me.rides.index');

    Route::get('{ride}', [RideController::class, 'show'])->name('me.rides.show');
    Route::get('{ride}/track', [RideController::class, 'track'])->name('me.rides.track');
    Route::post('{ride}/cancel', [RideController::class, 'cancel'])->name('me.rides.cancel');
    Route::post('{ride}/complete', [RideController::class, 'complete'])->name('me.rides.complete');
    Route::get('{ride}/receipt', [RideController::class, 'receipt'])->name('me.rides.receipt');

    // In-ride safety.
    Route::post('{ride}/safety/arm', [RideSafetyController::class, 'arm'])->name('me.rides.safety.arm');
    Route::get('{ride}/safety', [RideSafetyController::class, 'show'])->name('me.rides.safety.show');
    Route::post('{ride}/sos', [RideSafetyController::class, 'sos'])->name('me.rides.sos');
    Route::post('{ride}/evidence', [RideSafetyController::class, 'evidence'])->name('me.rides.evidence');
    Route::post('{ride}/check-in', [RideSafetyController::class, 'checkIn'])->name('me.rides.check-in');
});
