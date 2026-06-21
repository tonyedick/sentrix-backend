<?php

declare(strict_types=1);

use App\Domains\Cad\Http\Controllers\CadController;
use Illuminate\Support\Facades\Route;

/*
 | Sentrix CAD (Computer-Aided Dispatch) — units / AVL / closest-unit dispatch /
 | BOLOs. Sits UNDER the same `v1/command` prefix as the Command domain so the
 | whole Security Command area is cohesive. PLATFORM-scoped (NOT organization-
 | scoped): no {organization} prefix, no organization.team middleware. Every
 | endpoint sits behind auth:sanctum and is gated on SuperAdmin in the controller
 | / Form Requests.
 |
 | TODO: accept command roles (dispatch_coordinator/monitor) once a platform-staff
 | RBAC layer exists.
 */
Route::middleware(['auth:sanctum'])
    ->prefix('v1/command')
    ->group(function (): void {
        Route::get('units', [CadController::class, 'units'])->name('command.units.index');
        Route::post('units', [CadController::class, 'createUnit'])->name('command.units.store');
        Route::patch('units/{unit}', [CadController::class, 'updateUnit'])->name('command.units.update');

        Route::get('incidents/{commandIncident}/closest-units', [CadController::class, 'closestUnits'])->name('command.incidents.closest_units');
        Route::post('incidents/{commandIncident}/dispatch', [CadController::class, 'dispatch'])->name('command.incidents.dispatch');

        Route::get('bolos', [CadController::class, 'bolos'])->name('command.bolos.index');
        Route::post('bolos', [CadController::class, 'createBolo'])->name('command.bolos.store');
        Route::post('bolos/{bolo}/clear', [CadController::class, 'clearBolo'])->name('command.bolos.clear');
    });
