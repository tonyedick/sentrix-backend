<?php

declare(strict_types=1);

use App\Domains\Intel\Http\Controllers\IntelController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'organization.team'])
    ->prefix('v1/organizations/{organization}')
    ->group(function (): void {
        Route::get('intel/reports', [IntelController::class, 'report'])->name('intel.reports');
        Route::get('intel/reports/export', [IntelController::class, 'export'])->name('intel.reports.export');
        Route::get('intel/analytics', [IntelController::class, 'analytics'])->name('intel.analytics');
    });
