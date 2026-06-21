<?php

declare(strict_types=1);

use App\Domains\RidesOps\Http\Controllers\RidesOpsController;
use Illuminate\Support\Facades\Route;

/*
 | Rides Ops — the Safe Rides operations/admin console. PLATFORM/STAFF-scoped
 | (NOT organization-scoped): no {organization} prefix, no organization.team
 | middleware. Every endpoint sits behind auth:sanctum and is gated on
 | SuperAdmin (reads in the controller, writes in their Form Requests).
 |
 | TODO: replace the SuperAdmin gate with a rides:ops / rides:dispatch
 | platform-staff role once those are modelled.
 */
Route::middleware(['auth:sanctum'])
    ->prefix('v1/rides/admin')
    ->group(function (): void {
        // Reads (computed dashboards / model rosters).
        Route::get('overview', [RidesOpsController::class, 'overview'])->name('rides.admin.overview');
        Route::get('rides', [RidesOpsController::class, 'rides'])->name('rides.admin.rides');
        Route::get('drivers', [RidesOpsController::class, 'drivers'])->name('rides.admin.drivers');
        Route::get('onboarding', [RidesOpsController::class, 'onboarding'])->name('rides.admin.onboarding');
        Route::get('analytics', [RidesOpsController::class, 'analytics'])->name('rides.admin.analytics');
        Route::get('zones', [RidesOpsController::class, 'zones'])->name('rides.admin.zones');
        Route::get('live', [RidesOpsController::class, 'live'])->name('rides.admin.live');

        // Writes (operator overrides).
        Route::post('rides/{ride}/cancel', [RidesOpsController::class, 'cancel'])->name('rides.admin.rides.cancel');
        Route::post('rides/{ride}/reassign', [RidesOpsController::class, 'reassign'])->name('rides.admin.rides.reassign');
        Route::post('rides/{ride}/escalate', [RidesOpsController::class, 'escalate'])->name('rides.admin.rides.escalate');
        Route::post('drivers/{driver}/suspend', [RidesOpsController::class, 'suspend'])->name('rides.admin.drivers.suspend');
        Route::post('drivers/{driver}/reinstate', [RidesOpsController::class, 'reinstate'])->name('rides.admin.drivers.reinstate');
        Route::post('surge', [RidesOpsController::class, 'surge'])->name('rides.admin.surge');
    });
