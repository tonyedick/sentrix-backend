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
    Route::get('rewards/badges', [ConsumerRewardController::class, 'badges'])->name('me.rewards.badges');
    Route::get('rewards/leaderboard', [ConsumerRewardController::class, 'leaderboard'])->name('me.rewards.leaderboard');
    Route::get('rewards/missions', [ConsumerRewardController::class, 'missions'])->name('me.rewards.missions');
    Route::post('rewards/redeem', [ConsumerRewardController::class, 'redeem'])->name('me.rewards.redeem');
    Route::post('rewards/convert', [ConsumerRewardController::class, 'convert'])->name('me.rewards.convert');
    Route::post('rewards/record-activity', [ConsumerRewardController::class, 'recordActivity'])->name('me.rewards.record-activity');
});
