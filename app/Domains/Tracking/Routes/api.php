<?php

declare(strict_types=1);

use App\Domains\Tracking\Http\Controllers\LocationController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'organization.team'])
    ->prefix('v1/organizations/{organization}')
    ->group(function (): void {
        Route::post('trips/{trip}/locations', [LocationController::class, 'store'])->name('trips.locations.store');
        Route::get('trips/{trip}/locations', [LocationController::class, 'index'])->name('trips.locations.index');
        Route::get('locations/latest', [LocationController::class, 'latest'])->name('locations.latest');
    });
