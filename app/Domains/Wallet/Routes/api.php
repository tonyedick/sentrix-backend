<?php

declare(strict_types=1);

use App\Domains\Wallet\Http\Controllers\PaymentMethodController;
use App\Domains\Wallet\Http\Controllers\ReferralController;
use App\Domains\Wallet\Http\Controllers\WalletController;
use Illuminate\Support\Facades\Route;

/*
 | Safe Rides — Wallet & payments (user-scoped, ADR-0001). Lives under the
 | rides prefix alongside booking/safety; no organization, no permission
 | catalogue. ALL MONEY IS INTEGER CENTS.
 */
Route::middleware('auth:sanctum')->prefix('v1/me/rides')->group(function (): void {
    // Payment methods.
    Route::get('payment-methods', [PaymentMethodController::class, 'index'])->name('me.rides.payment-methods.index');
    Route::post('payment-methods', [PaymentMethodController::class, 'store'])->name('me.rides.payment-methods.store');
    Route::delete('payment-methods/{paymentMethod}', [PaymentMethodController::class, 'destroy'])->name('me.rides.payment-methods.destroy');

    // Wallet & pay.
    Route::get('pay/wallet', [WalletController::class, 'show'])->name('me.rides.pay.wallet');
    Route::post('pay/topup/initiate', [WalletController::class, 'initiateTopup'])->name('me.rides.pay.topup.initiate');
    Route::post('pay/topup/confirm', [WalletController::class, 'confirmTopup'])->name('me.rides.pay.topup.confirm');
    Route::post('pay/charge', [WalletController::class, 'charge'])->name('me.rides.pay.charge');
    Route::post('pay/payout', [WalletController::class, 'payout'])->name('me.rides.pay.payout');

    // Referral.
    Route::get('referral/me', [ReferralController::class, 'me'])->name('me.rides.referral.me');
    Route::post('referral/claim', [ReferralController::class, 'claim'])->name('me.rides.referral.claim');
});
