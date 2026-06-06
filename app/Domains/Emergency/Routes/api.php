<?php

declare(strict_types=1);

use App\Domains\Emergency\Http\Controllers\EmergencyController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'organization.team'])
    ->prefix('v1/organizations/{organization}')
    ->group(function (): void {
        Route::get('emergencies', [EmergencyController::class, 'index'])->name('emergencies.index');
        Route::post('emergencies', [EmergencyController::class, 'store'])->name('emergencies.store');
        Route::get('emergencies/{emergency}', [EmergencyController::class, 'show'])->name('emergencies.show');
        Route::post('emergencies/{emergency}/acknowledge', [EmergencyController::class, 'acknowledge'])->name('emergencies.acknowledge');
        Route::post('emergencies/{emergency}/resolve', [EmergencyController::class, 'resolve'])->name('emergencies.resolve');
        Route::post('emergencies/{emergency}/cancel', [EmergencyController::class, 'cancel'])->name('emergencies.cancel');
    });
