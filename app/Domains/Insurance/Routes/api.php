<?php

declare(strict_types=1);

use App\Domains\Insurance\Http\Controllers\ClaimController;
use App\Domains\Insurance\Http\Controllers\PolicyController;
use App\Domains\Insurance\Http\Controllers\RiskController;
use Illuminate\Support\Facades\Route;

/*
 | Insurance — the synergy layer between security posture and risk pricing.
 | Organization-scoped; abilities are enforced per-action (insurance.risk /
 | insurance.quote / insurance.policies.* / insurance.claims.*).
 */
Route::middleware(['auth:sanctum', 'organization.team'])
    ->prefix('v1/organizations/{organization}')
    ->group(function (): void {
        // Risk engine (read-only computed snapshots).
        Route::get('insurance/risk', [RiskController::class, 'profile'])->name('insurance.risk');
        Route::post('insurance/quote', [RiskController::class, 'quote'])->name('insurance.quote');

        // Policies.
        Route::get('insurance/policies', [PolicyController::class, 'index'])->name('insurance.policies.index');
        Route::post('insurance/policies', [PolicyController::class, 'store'])->name('insurance.policies.store');

        // Claims.
        Route::get('insurance/claims', [ClaimController::class, 'index'])->name('insurance.claims.index');
        Route::post('insurance/claims', [ClaimController::class, 'store'])->name('insurance.claims.store');
        Route::post('insurance/claims/{claim}/decide', [ClaimController::class, 'decide'])->name('insurance.claims.decide');
    });
