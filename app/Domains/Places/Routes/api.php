<?php

declare(strict_types=1);

use App\Domains\Places\Http\Controllers\PlaceController;
use Illuminate\Support\Facades\Route;

/*
 | Safety POI directory. Shared reference data (not user- or org-scoped), read by
 | any authenticated consumer.
 */
Route::middleware('auth:sanctum')->prefix('v1')->group(function (): void {
    Route::get('places', [PlaceController::class, 'index'])->name('places.index');
    Route::get('places/{place}', [PlaceController::class, 'show'])->name('places.show');
});
