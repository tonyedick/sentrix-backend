<?php

declare(strict_types=1);

use App\Domains\Identity\Http\Controllers\AuthenticatedSessionController;
use App\Domains\Identity\Http\Controllers\CurrentUserController;
use App\Domains\Identity\Http\Controllers\EmailVerificationNotificationController;
use App\Domains\Identity\Http\Controllers\NewPasswordController;
use App\Domains\Identity\Http\Controllers\PasswordResetLinkController;
use App\Domains\Identity\Http\Controllers\RegisteredUserController;
use App\Domains\Identity\Http\Controllers\VerifyEmailController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1/auth')->group(function (): void {
    // Public endpoints. Throttled to blunt credential-stuffing.
    Route::post('register', [RegisteredUserController::class, 'store'])
        ->middleware('throttle:auth')
        ->name('auth.register');

    Route::post('login', [AuthenticatedSessionController::class, 'store'])
        ->middleware('throttle:auth')
        ->name('auth.login');

    // Password reset (public). Token-bound; throttled like other credential ops.
    Route::post('forgot-password', [PasswordResetLinkController::class, 'store'])
        ->middleware('throttle:auth')
        ->name('password.email');

    Route::post('reset-password', [NewPasswordController::class, 'store'])
        ->middleware('throttle:auth')
        ->name('password.update');

    // Email verification from a signed link. Stateless (signature + email hash
    // authenticate), so no auth guard — works for web and mobile alike.
    Route::get('email/verify/{id}/{hash}', VerifyEmailController::class)
        ->middleware(['signed', 'throttle:6,1'])
        ->name('verification.verify');

    // Authenticated (token or SPA session).
    Route::middleware('auth:sanctum')->group(function (): void {
        Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])->name('auth.logout');
        Route::get('me', [CurrentUserController::class, 'show'])->name('auth.me');

        // Resend the verification link to the current user.
        Route::post('email/verification-notification', [EmailVerificationNotificationController::class, 'store'])
            ->middleware('throttle:6,1')
            ->name('verification.send');
    });
});
