<?php

declare(strict_types=1);

use App\Domains\Core\Http\Controllers\CoreController;
use App\Domains\Core\Http\Controllers\CoreToolGatewayController;
use Illuminate\Support\Facades\Route;

/*
 | Sentrix Core bridge — PLATFORM-scoped (NOT organization-scoped): no
 | {organization} prefix, no organization.team middleware. The bridge proxies to
 | the external Python Core agent and broadcasts inbound product events.
 |
 | chat / act / command-center are authed by auth:sanctum (a real user, whose
 | scopes are forwarded to Core). events is authed by the X-Service-Token header
 | via the core.service middleware (a machine identity), NOT sanctum.
 */
Route::middleware(['auth:sanctum'])
    ->prefix('v1/core')
    ->group(function (): void {
        Route::post('chat', [CoreController::class, 'chat'])->name('core.chat');
        Route::post('act', [CoreController::class, 'act'])->name('core.act');
        Route::get('command-center', [CoreController::class, 'commandCenter'])->name('core.command-center');
    });

// Inbound product/detection events: authed by X-Service-Token (custom
// middleware), NOT sanctum.
Route::middleware(['core.service'])
    ->prefix('v1/core')
    ->group(function (): void {
        Route::post('events', [CoreController::class, 'events'])->name('core.events');
    });

// SentrixCore agent tool gateway: Core's tools POST to {base}/api/tools/{name}
// (the Omni tool contract). Authed by X-Service-Token (core.service). The `tools`
// prefix (no `v1/core`) matches Core's OmniAPI client path exactly.
Route::middleware(['core.service'])
    ->prefix('tools')
    ->group(function (): void {
        Route::post('{tool}', [CoreToolGatewayController::class, 'invoke'])->name('core.tools.invoke');
    });
