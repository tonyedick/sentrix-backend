<?php

declare(strict_types=1);

use App\Domains\Billing\Http\Controllers\ConsumerSubscriptionController;
use App\Domains\Billing\Http\Controllers\PlanController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->prefix('v1')->group(function (): void {
    Route::get('plans', [PlanController::class, 'index'])->name('plans.index');
});

/*
 | Consumer subscription + billing (user-scoped, ADR-0001).
 */
Route::middleware('auth:sanctum')->prefix('v1/me')->group(function (): void {
    Route::get('subscription', [ConsumerSubscriptionController::class, 'show'])->name('me.subscription.show');
    Route::post('subscription', [ConsumerSubscriptionController::class, 'subscribe'])->name('me.subscription.subscribe');
    Route::post('subscription/cancel', [ConsumerSubscriptionController::class, 'cancel'])->name('me.subscription.cancel');
    Route::patch('subscription/auto-renew', [ConsumerSubscriptionController::class, 'autoRenew'])->name('me.subscription.auto-renew');
    Route::get('billing/invoices', [ConsumerSubscriptionController::class, 'invoices'])->name('me.billing.invoices');
});
