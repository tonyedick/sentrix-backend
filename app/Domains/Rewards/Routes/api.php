<?php

declare(strict_types=1);

use App\Domains\Rewards\Http\Controllers\ConsumerRewardController;
use Illuminate\Support\Facades\Route;

/*
 | Consumer rewards — user-scoped (ADR-0001).
 */
Route::middleware('auth:sanctum')->prefix('v1/me')->group(function (): void {
    Route::get('rewards', [ConsumerRewardController::class, 'show'])->name('me.rewards.show');
    Route::get('rewards/ledger', [ConsumerRewardController::class, 'ledger'])->name('me.rewards.ledger');
    Route::post('rewards/redeem', [ConsumerRewardController::class, 'redeem'])->name('me.rewards.redeem');
});
