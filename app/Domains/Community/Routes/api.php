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

    // Static segments before the {alert} wildcard so they bind by path, not id.
    Route::get('alerts/safe-places', [ConsumerCommunityAlertController::class, 'safePlaces'])->name('me.alerts.safe-places');
    // Staff/Core publish a trusted official/AI alert (SuperAdmin-gated).
    Route::post('alerts/publish', [ConsumerCommunityAlertController::class, 'publish'])->name('me.alerts.publish');

    Route::get('alerts/{alert}', [ConsumerCommunityAlertController::class, 'show'])->name('me.alerts.show');
    Route::post('alerts/{alert}/confirm', [ConsumerCommunityAlertController::class, 'confirm'])->name('me.alerts.confirm');
    Route::post('alerts/{alert}/dismiss', [ConsumerCommunityAlertController::class, 'dismiss'])->name('me.alerts.dismiss');
    Route::post('alerts/{alert}/verify', [ConsumerCommunityAlertController::class, 'verify'])->name('me.alerts.verify');
    Route::post('alerts/{alert}/dispute', [ConsumerCommunityAlertController::class, 'dispute'])->name('me.alerts.dispute');
    Route::post('alerts/{alert}/resolve', [ConsumerCommunityAlertController::class, 'resolve'])->name('me.alerts.resolve');
});
