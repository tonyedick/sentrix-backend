<?php

declare(strict_types=1);

use App\Domains\Evidence\Http\Controllers\EvidenceController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'organization.team'])
    ->prefix('v1/organizations/{organization}')
    ->group(function (): void {
        Route::post('evidence/observe', [EvidenceController::class, 'observe'])->name('evidence.observe');
        Route::get('evidence/search', [EvidenceController::class, 'search'])->name('evidence.search');
        Route::get('evidence/stats', [EvidenceController::class, 'stats'])->name('evidence.stats');
        // {plate} is a STRING param, NOT a route-model bind — the controller
        // queries the plate column directly. Declared after the static segments
        // above so "stats"/"search" are never captured as a plate.
        Route::get('evidence/vehicle/{plate}', [EvidenceController::class, 'vehicle'])->name('evidence.vehicle');
        Route::post('evidence/{observation}/hold', [EvidenceController::class, 'hold'])->name('evidence.hold');
        Route::post('evidence/{observation}/bookmark', [EvidenceController::class, 'bookmark'])->name('evidence.bookmark');
    });
