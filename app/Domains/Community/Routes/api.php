<?php

declare(strict_types=1);

use App\Domains\Community\Http\Controllers\ConsumerCommunityAlertController;
use Illuminate\Support\Facades\Route;

/*
 | Community alerts — consumer, user-scoped (ADR-0001). No organization context;
 | a geo feed plus report + crowd verification.
 */
Route::middleware('auth:sanctum')->prefix('v1/me')->group(function (): void {
    Route::get('alerts', [ConsumerCommunityAlertController::class, 'index'])->name('me.alerts.index');
    Route::post('alerts', [ConsumerCommunityAlertController::class, 'store'])->name('me.alerts.store');
    Route::get('alerts/{alert}', [ConsumerCommunityAlertController::class, 'show'])->name('me.alerts.show');
    Route::post('alerts/{alert}/confirm', [ConsumerCommunityAlertController::class, 'confirm'])->name('me.alerts.confirm');
    Route::post('alerts/{alert}/dismiss', [ConsumerCommunityAlertController::class, 'dismiss'])->name('me.alerts.dismiss');
});
