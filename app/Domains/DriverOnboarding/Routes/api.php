<?php

declare(strict_types=1);

use App\Domains\DriverOnboarding\Http\Controllers\DriverController;
use App\Domains\DriverOnboarding\Http\Controllers\DriverStaffController;
use Illuminate\Support\Facades\Route;

/*
 | Safe Rides — Driver onboarding. TWO scopes:
 |
 |  1) DRIVER (user-scoped, ADR-0001): v1/me/rides/driver/* — the authenticated
 |     user acting as a driver. The Driver row belongs to the caller; ownership
 |     is resolved from the token, never route-model binding.
 |
 |  2) STAFF (platform-scoped): v1/rides/staff/* — SuperAdmin-gated review queues
 |     and decisions. No {organization} prefix, no organization.team middleware.
 |     {driver} is route-model bound (UUID-safe).
 |
 | The real driver-accept dispatch loop is deferred — Rides core currently
 | SIMULATES the match. Activation here emits DriverActivated as the seam a later
 | dispatch domain will listen on to add the driver to the live pool.
 */

// ---- Driver (user-scoped) ----
Route::middleware('auth:sanctum')->prefix('v1/me/rides/driver')->group(function (): void {
    Route::post('register', [DriverController::class, 'register'])->name('me.rides.driver.register');
    Route::post('documents', [DriverController::class, 'documents'])->name('me.rides.driver.documents');
    Route::get('me', [DriverController::class, 'me'])->name('me.rides.driver.me');
    Route::post('online', [DriverController::class, 'online'])->name('me.rides.driver.online');
    Route::get('vetting-centers', [DriverController::class, 'vettingCenters'])->name('me.rides.driver.vetting-centers');
    Route::post('inspection/book', [DriverController::class, 'bookInspection'])->name('me.rides.driver.inspection.book');
});

// ---- Staff (platform-scoped, SuperAdmin-gated) ----
Route::middleware('auth:sanctum')->prefix('v1/rides/staff')->group(function (): void {
    Route::get('driver-queue', [DriverStaffController::class, 'driverQueue'])->name('rides.staff.driver-queue');
    Route::get('inspection-queue', [DriverStaffController::class, 'inspectionQueue'])->name('rides.staff.inspection-queue');

    Route::post('drivers/{driver}/document', [DriverStaffController::class, 'document'])->name('rides.staff.drivers.document');
    Route::post('drivers/{driver}/decision', [DriverStaffController::class, 'decision'])->name('rides.staff.drivers.decision');
    Route::post('drivers/{driver}/inspection', [DriverStaffController::class, 'inspection'])->name('rides.staff.drivers.inspection');
});
