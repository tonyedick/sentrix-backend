<?php

declare(strict_types=1);

use App\Domains\Billing\Http\Controllers\CheckoutController;
use App\Domains\Billing\Http\Controllers\ConsumerSubscriptionController;
use App\Domains\Billing\Http\Controllers\PlanController;
use App\Domains\Billing\Http\Controllers\WebhookController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->prefix('v1')->group(function (): void {
    Route::get('plans', [PlanController::class, 'index'])->name('plans.index');
    // Multi-region priced catalog (?region=NG|KE|US).
    Route::get('billing/catalog', [CheckoutController::class, 'catalog'])->name('billing.catalog');
});

/*
 | PSP webhook ingress — NO auth, signature-verified (X-Sentrix-Signature).
 */
Route::post('v1/billing/webhook', [WebhookController::class, 'handle'])->name('billing.webhook');

/*
 | Consumer subscription + billing (user-scoped, ADR-0001).
 */
Route::middleware('auth:sanctum')->prefix('v1/me')->group(function (): void {
    Route::get('subscription', [ConsumerSubscriptionController::class, 'show'])->name('me.subscription.show');
    Route::post('subscription', [ConsumerSubscriptionController::class, 'subscribe'])->name('me.subscription.subscribe');
    Route::post('subscription/cancel', [ConsumerSubscriptionController::class, 'cancel'])->name('me.subscription.cancel');
    Route::patch('subscription/auto-renew', [ConsumerSubscriptionController::class, 'autoRenew'])->name('me.subscription.auto-renew');
    Route::get('billing/invoices', [ConsumerSubscriptionController::class, 'invoices'])->name('me.billing.invoices');

    // PSP checkout. {reference} is a STRING looked up scoped to the caller —
    // NOT a uuid route-model bind (never compare a uuid column to a non-uuid).
    Route::post('billing/checkout', [CheckoutController::class, 'checkout'])->name('me.billing.checkout');
    Route::get('billing/checkout/{reference}', [CheckoutController::class, 'show'])->name('me.billing.checkout.show');
    Route::post('billing/checkout/{reference}/simulate', [CheckoutController::class, 'simulate'])->name('me.billing.checkout.simulate');
});
