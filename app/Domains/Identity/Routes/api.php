<?php

declare(strict_types=1);

use App\Domains\Identity\Http\Controllers\AuthenticatedSessionController;
use App\Domains\Identity\Http\Controllers\CurrentUserController;
use App\Domains\Identity\Http\Controllers\RegisteredUserController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1/auth')->group(function (): void {
    // Public endpoints. Throttled to blunt credential-stuffing.
    Route::post('register', [RegisteredUserController::class, 'store'])
        ->middleware('throttle:auth')
        ->name('auth.register');

    Route::post('login', [AuthenticatedSessionController::class, 'store'])
        ->middleware('throttle:auth')
        ->name('auth.login');

    // Authenticated (token or SPA session).
    Route::middleware('auth:sanctum')->group(function (): void {
        Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])->name('auth.logout');
        Route::get('me', [CurrentUserController::class, 'show'])->name('auth.me');
    });
});
