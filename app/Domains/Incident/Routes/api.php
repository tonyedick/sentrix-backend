<?php

declare(strict_types=1);

use App\Domains\Incident\Http\Controllers\IncidentController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'organization.team'])
    ->prefix('v1/organizations/{organization}')
    ->group(function (): void {
        Route::get('incidents', [IncidentController::class, 'index'])->name('incidents.index');
        Route::post('incidents', [IncidentController::class, 'store'])->name('incidents.store');
        Route::get('incidents/{incident}', [IncidentController::class, 'show'])->name('incidents.show');
        Route::patch('incidents/{incident}', [IncidentController::class, 'update'])->name('incidents.update');
        Route::post('incidents/{incident}/investigate', [IncidentController::class, 'investigate'])->name('incidents.investigate');
        Route::post('incidents/{incident}/escalate', [IncidentController::class, 'escalate'])->name('incidents.escalate');
        Route::post('incidents/{incident}/resolve', [IncidentController::class, 'resolve'])->name('incidents.resolve');
        Route::post('incidents/{incident}/close', [IncidentController::class, 'close'])->name('incidents.close');
    });
