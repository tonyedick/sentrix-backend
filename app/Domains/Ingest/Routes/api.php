<?php

declare(strict_types=1);

use App\Domains\Ingest\Http\Controllers\IngestController;
use App\Domains\Ingest\Http\Controllers\PublicIncidentController;
use Illuminate\Support\Facades\Route;

/*
 | Sentrix Ingest — the detection→decision pipeline. PLATFORM-scoped (no
 | {organization} prefix): the tenant is carried IN the body and authed by the
 | machine service token, not a user session.
 |
 | ingest/* and signal/ingest are authed by the X-Service-Token header via the
 | `core.service` middleware (a machine identity), NOT sanctum — reusing the
 | globally-registered alias the Core bridge owns.
 |
 | public/incidents is the anonymized citizen feed: NO auth, throttled,
 | strictly read-only.
 */
Route::middleware(['core.service'])
    ->prefix('v1')
    ->group(function (): void {
        Route::post('ingest/detections', [IngestController::class, 'detections'])->name('ingest.detections');
        Route::post('ingest/vision', [IngestController::class, 'vision'])->name('ingest.vision');
        Route::post('signal/ingest', [IngestController::class, 'signal'])->name('signal.ingest');
    });

// Anonymized public feed: no auth, basic throttle.
Route::middleware(['throttle:60,1'])
    ->prefix('v1')
    ->group(function (): void {
        Route::get('public/incidents', [PublicIncidentController::class, 'index'])->name('public.incidents');
    });
