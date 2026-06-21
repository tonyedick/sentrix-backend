<?php

declare(strict_types=1);

use App\Domains\Crm\Http\Controllers\LeadController;
use Illuminate\Support\Facades\Route;

/*
 * PLATFORM-scoped (NOT organization-scoped): leads exist before any tenant does,
 * so these routes use the bare `v1` prefix (no `v1/organizations/{organization}`)
 * and do NOT use the `organization.team` middleware. Authentication is
 * `auth:sanctum`; per-action authorization is the SuperAdmin gate in the
 * controllers and Form Requests.
 *
 * The base provider mounts these under the `api` prefix, yielding `/api/v1/...`.
 */
Route::middleware(['auth:sanctum'])
    ->prefix('v1')
    ->group(function (): void {
        Route::get('leads', [LeadController::class, 'index'])->name('leads.index');
        Route::post('leads', [LeadController::class, 'store'])->name('leads.store');
        Route::get('leads/{lead}', [LeadController::class, 'show'])->name('leads.show');
        Route::patch('leads/{lead}', [LeadController::class, 'update'])->name('leads.update');
        Route::post('leads/{lead}/quote', [LeadController::class, 'quote'])->name('leads.quote');
        Route::post('leads/{lead}/convert', [LeadController::class, 'convert'])->name('leads.convert');
    });
