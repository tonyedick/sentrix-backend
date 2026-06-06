<?php

declare(strict_types=1);

use App\Domains\Audit\Http\Controllers\AuditLogController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'organization.team'])
    ->prefix('v1/organizations/{organization}')
    ->group(function (): void {
        Route::get('audit-logs', [AuditLogController::class, 'index'])->name('audit-logs.index');
    });
