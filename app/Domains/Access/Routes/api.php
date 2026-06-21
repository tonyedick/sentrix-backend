<?php

declare(strict_types=1);

use App\Domains\Access\Http\Controllers\GateController;
use App\Domains\Access\Http\Controllers\PassController;
use Illuminate\Support\Facades\Route;

/*
 | Access management — visitor passes + gate. Organization-scoped; abilities are
 | enforced per-action (passes.view / passes.issue / passes.manage,
 | gate.scan / gate.view / gate.log).
 */
Route::middleware(['auth:sanctum', 'organization.team'])
    ->prefix('v1/organizations/{organization}')
    ->group(function (): void {
        Route::get('passes', [PassController::class, 'index'])->name('passes.index');
        Route::post('passes', [PassController::class, 'store'])->name('passes.store');
        Route::post('passes/{pass}/revoke', [PassController::class, 'revoke'])->name('passes.revoke');

        Route::post('gate/scan', [GateController::class, 'scan'])->name('gate.scan');
        Route::get('gate', [GateController::class, 'index'])->name('gate.index');
        Route::post('gate', [GateController::class, 'store'])->name('gate.store');
    });
