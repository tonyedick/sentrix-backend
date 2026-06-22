<?php

declare(strict_types=1);

use App\Domains\Webhooks\Http\Controllers\WebhookController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'organization.team'])
    ->prefix('v1/organizations/{organization}')
    ->group(function (): void {
        Route::get('webhooks', [WebhookController::class, 'index'])->name('webhooks.index');
        Route::post('webhooks', [WebhookController::class, 'store'])->name('webhooks.store');
        Route::get('webhooks/{webhook}', [WebhookController::class, 'show'])->name('webhooks.show');
        Route::delete('webhooks/{webhook}', [WebhookController::class, 'destroy'])->name('webhooks.destroy');
        Route::get('webhooks/{webhook}/deliveries', [WebhookController::class, 'deliveries'])->name('webhooks.deliveries');
    });
