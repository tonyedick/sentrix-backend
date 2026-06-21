<?php

declare(strict_types=1);

use App\Domains\Ledger\Http\Controllers\LedgerController;
use Illuminate\Support\Facades\Route;

/*
 | Sentrix Ledger — the ecosystem write-spine. PLATFORM-scoped (NOT
 | organization-scoped): no {organization} prefix, no organization.team
 | middleware.
 |
 | Admin endpoints (stats / feed / sources / lifecycle) sit behind auth:sanctum
 | and are gated on SuperAdmin in the controller / Form Requests.
 |
 | The ingest endpoint is authed by the X-Ledger-Key header via the ledger.key
 | middleware (registered by the domain service provider), NOT sanctum.
 */
Route::middleware(['auth:sanctum'])
    ->prefix('v1/ledger')
    ->group(function (): void {
        Route::get('stats', [LedgerController::class, 'stats'])->name('ledger.stats');
        Route::get('writes', [LedgerController::class, 'writes'])->name('ledger.writes');
        Route::get('sources', [LedgerController::class, 'sources'])->name('ledger.sources.index');
        Route::post('sources', [LedgerController::class, 'onboard'])->name('ledger.sources.store');
        Route::post('sources/{source}/activate', [LedgerController::class, 'activate'])->name('ledger.sources.activate');
        Route::post('sources/{source}/suspend', [LedgerController::class, 'suspend'])->name('ledger.sources.suspend');
        Route::post('sources/{source}/revoke', [LedgerController::class, 'revoke'])->name('ledger.sources.revoke');
        Route::post('sources/{source}/rotate-key', [LedgerController::class, 'rotateKey'])->name('ledger.sources.rotate-key');
    });

// Ingest: authed by X-Ledger-Key (custom middleware), NOT sanctum.
Route::middleware(['ledger.key'])
    ->prefix('v1/ledger')
    ->group(function (): void {
        Route::post('ingest', [LedgerController::class, 'ingest'])->name('ledger.ingest');
    });
