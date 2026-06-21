<?php

declare(strict_types=1);

use App\Domains\Retention\Http\Controllers\RetentionController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'organization.team'])
    ->prefix('v1/organizations/{organization}')
    ->group(function (): void {
        Route::get('storage', [RetentionController::class, 'storage'])->name('retention.storage');
        Route::post('retention/sweep', [RetentionController::class, 'sweep'])->name('retention.sweep');
        Route::post('evidence/archive/export', [RetentionController::class, 'archive'])->name('retention.evidence.archive');
        Route::post('evidence/purge', [RetentionController::class, 'purge'])->name('retention.evidence.purge');
    });
