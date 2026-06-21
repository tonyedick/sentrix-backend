<?php

declare(strict_types=1);

use App\Domains\Assignment\Http\Controllers\AssignmentController;
use App\Domains\Assignment\Http\Controllers\AssignmentResponderController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'organization.team'])
    ->prefix('v1/organizations/{organization}')
    ->group(function (): void {
        // Aggregate
        Route::get('assignments', [AssignmentController::class, 'index'])->name('assignments.index');
        Route::post('assignments', [AssignmentController::class, 'store'])->name('assignments.store');
        Route::get('assignments/{assignment}', [AssignmentController::class, 'show'])->name('assignments.show');
        Route::post('assignments/{assignment}/responders', [AssignmentController::class, 'addResponder'])->name('assignments.responders.store');
        Route::post('assignments/{assignment}/cancel', [AssignmentController::class, 'cancel'])->name('assignments.cancel');
        Route::post('assignments/{assignment}/complete', [AssignmentController::class, 'complete'])->name('assignments.complete');

        // Per-responder line actions
        Route::post('assignments/{assignment}/responders/{line}/accept', [AssignmentResponderController::class, 'accept'])->name('assignments.responders.accept');
        Route::post('assignments/{assignment}/responders/{line}/decline', [AssignmentResponderController::class, 'decline'])->name('assignments.responders.decline');
        Route::post('assignments/{assignment}/responders/{line}/en-route', [AssignmentResponderController::class, 'enRoute'])->name('assignments.responders.en-route');
        Route::post('assignments/{assignment}/responders/{line}/on-scene', [AssignmentResponderController::class, 'onScene'])->name('assignments.responders.on-scene');
        Route::post('assignments/{assignment}/responders/{line}/complete', [AssignmentResponderController::class, 'complete'])->name('assignments.responders.complete');
    });
