<?php

declare(strict_types=1);

use App\Domains\Coordination\Http\Controllers\CoordinationController;
use Illuminate\Support\Facades\Route;

/*
 | Coordination — the final slice of the Security Command / CAD area: mutual aid,
 | unit comms, command analytics, and the duty/taskings staffing layer.
 | Platform-scoped (SuperAdmin-gated in the controller), under the v1/command prefix.
 */
Route::middleware(['auth:sanctum'])
    ->prefix('v1/command')
    ->group(function (): void {
        // Mutual aid
        Route::get('mutual-aid', [CoordinationController::class, 'listAid'])->name('command.mutual-aid.index');
        Route::post('mutual-aid', [CoordinationController::class, 'requestAid'])->name('command.mutual-aid.store');
        Route::post('mutual-aid/{mutualAidRequest}/accept', [CoordinationController::class, 'acceptAid'])->name('command.mutual-aid.accept');
        Route::post('mutual-aid/{mutualAidRequest}/decline', [CoordinationController::class, 'declineAid'])->name('command.mutual-aid.decline');

        // Unit comms
        Route::get('units/{unit}/messages', [CoordinationController::class, 'thread'])->name('command.units.messages.index');
        Route::post('units/{unit}/messages', [CoordinationController::class, 'sendMessage'])->name('command.units.messages.store');

        // Command analytics
        Route::get('analytics', [CoordinationController::class, 'analytics'])->name('command.analytics');

        // Taskings
        Route::get('taskings', [CoordinationController::class, 'listTaskings'])->name('command.taskings.index');
        Route::post('taskings', [CoordinationController::class, 'routeTasking'])->name('command.taskings.store');
        Route::post('taskings/{tasking}/ack', [CoordinationController::class, 'acknowledgeTasking'])->name('command.taskings.ack');
        Route::post('taskings/{tasking}/resolve', [CoordinationController::class, 'resolveTasking'])->name('command.taskings.resolve');

        // Duty book
        Route::get('duty', [CoordinationController::class, 'listDuty'])->name('command.duty.index');
        Route::post('duty', [CoordinationController::class, 'recordDuty'])->name('command.duty.store');
    });
