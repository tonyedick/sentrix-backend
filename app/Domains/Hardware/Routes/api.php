<?php

declare(strict_types=1);

use App\Domains\Hardware\Http\Controllers\DeviceController;
use Illuminate\Support\Facades\Route;

/*
 | Hardware device registry — physical security hardware. Organization-scoped;
 | abilities are enforced per-action (hardware.view / hardware.register /
 | hardware.resync / hardware.diagnose).
 */
Route::middleware(['auth:sanctum', 'organization.team'])
    ->prefix('v1/organizations/{organization}')
    ->group(function (): void {
        Route::get('hardware', [DeviceController::class, 'index'])->name('hardware.index');
        Route::post('hardware', [DeviceController::class, 'store'])->name('hardware.store');
        Route::get('hardware/{device}', [DeviceController::class, 'show'])->name('hardware.show');
        Route::post('hardware/{device}/resync', [DeviceController::class, 'resync'])->name('hardware.resync');
        Route::post('hardware/{device}/diagnose', [DeviceController::class, 'diagnose'])->name('hardware.diagnose');
    });
