<?php

declare(strict_types=1);

use App\Domains\Identity\Http\Controllers\AuthenticatedSessionController;
use App\Domains\Identity\Http\Controllers\CurrentUserController;
use App\Domains\Identity\Http\Controllers\EmailVerificationNotificationController;
use App\Domains\Identity\Http\Controllers\MeController;
use App\Domains\Identity\Http\Controllers\NewPasswordController;
use App\Domains\Identity\Http\Controllers\OtpController;
use App\Domains\Identity\Http\Controllers\PasswordResetLinkController;
use App\Domains\Identity\Http\Controllers\PushTokenController;
use App\Domains\Identity\Http\Controllers\RecentSearchController;
use App\Domains\Identity\Http\Controllers\RegisteredUserController;
use App\Domains\Identity\Http\Controllers\SafetyContactController;
use App\Domains\Identity\Http\Controllers\SavedLocationController;
use App\Domains\Identity\Http\Controllers\SocialLoginController;
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

    // Social sign-in (Apple / Google) — verifies a provider token, returns a token.
    Route::post('social', [SocialLoginController::class, 'store'])
        ->middleware('throttle:auth')
        ->name('auth.social');

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

        // OTP verification (mobile onboarding): verify a 6-digit code or resend.
        Route::post('otp/verify', [OtpController::class, 'verify'])
            ->middleware('throttle:6,1')
            ->name('auth.otp.verify');
        Route::post('otp/resend', [OtpController::class, 'resend'])
            ->middleware('throttle:6,1')
            ->name('auth.otp.resend');
    });
});

/*
 | Consumer user-scoped surface (ADR-0001). No organization context — the
 | authenticated user owns these resources. Mobile authenticates with a Sanctum
 | bearer token.
 */
Route::prefix('v1/me')->middleware('auth:sanctum')->group(function (): void {
    Route::get('/', [MeController::class, 'show'])->name('me.show');
    Route::patch('/', [MeController::class, 'update'])->name('me.update');

    Route::post('devices/push-token', [PushTokenController::class, 'store'])->name('me.push-token.store');
    Route::delete('devices/push-token', [PushTokenController::class, 'destroy'])->name('me.push-token.destroy');

    // Safety contacts (1–5) — notified on SOS / overdue / emergency.
    Route::get('contacts', [SafetyContactController::class, 'index'])->name('me.contacts.index');
    Route::post('contacts', [SafetyContactController::class, 'store'])->name('me.contacts.store');
    Route::patch('contacts/{contact}', [SafetyContactController::class, 'update'])->name('me.contacts.update');
    Route::delete('contacts/{contact}', [SafetyContactController::class, 'destroy'])->name('me.contacts.destroy');

    // Saved places (Home / Work / custom) for quick trip planning.
    Route::get('saved-locations', [SavedLocationController::class, 'index'])->name('me.saved-locations.index');
    Route::post('saved-locations', [SavedLocationController::class, 'store'])->name('me.saved-locations.store');
    Route::patch('saved-locations/{savedLocation}', [SavedLocationController::class, 'update'])->name('me.saved-locations.update');
    Route::delete('saved-locations/{savedLocation}', [SavedLocationController::class, 'destroy'])->name('me.saved-locations.destroy');

    // Recent destination searches — recorded on search, surfaced on trip planning.
    Route::get('recent-searches', [RecentSearchController::class, 'index'])->name('me.recent-searches.index');
    Route::post('recent-searches', [RecentSearchController::class, 'store'])->name('me.recent-searches.store');
    Route::delete('recent-searches', [RecentSearchController::class, 'destroy'])->name('me.recent-searches.clear');
});
