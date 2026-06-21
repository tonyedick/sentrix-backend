<?php

declare(strict_types=1);

use App\Domains\Command\Http\Controllers\CommandController;
use Illuminate\Support\Facades\Route;

/*
 | Sentrix Responder Command — the NATIONAL AGENCY layer. PLATFORM-scoped (NOT
 | organization-scoped): no {organization} prefix, no organization.team
 | middleware. Every endpoint sits behind auth:sanctum and is gated on
 | SuperAdmin in the controller / Form Requests.
 |
 | TODO: accept command roles (dispatch_coordinator/monitor) once a platform-staff
 | RBAC layer exists.
 */
Route::middleware(['auth:sanctum'])
    ->prefix('v1/command')
    ->group(function (): void {
        Route::get('overview', [CommandController::class, 'overview'])->name('command.overview');

        Route::get('agencies', [CommandController::class, 'agencies'])->name('command.agencies.index');
        Route::post('agencies', [CommandController::class, 'createAgency'])->name('command.agencies.store');
        Route::get('agencies/resolve', [CommandController::class, 'resolveAgency'])->name('command.agencies.resolve');

        Route::get('commands', [CommandController::class, 'commands'])->name('command.commands.index');
        Route::post('commands', [CommandController::class, 'createCommand'])->name('command.commands.store');

        Route::post('incidents/route', [CommandController::class, 'routeIncident'])->name('command.incidents.route');
        Route::get('incidents', [CommandController::class, 'incidents'])->name('command.incidents.index');
        Route::get('incidents/{commandIncident}', [CommandController::class, 'showIncident'])->name('command.incidents.show');
        Route::post('incidents/{commandIncident}/act', [CommandController::class, 'actOnIncident'])->name('command.incidents.act');
    });
