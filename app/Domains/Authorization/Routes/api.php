<?php

declare(strict_types=1);

use App\Domains\Authorization\Http\Controllers\PermissionController;
use App\Domains\Authorization\Http\Controllers\RoleController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'organization.team'])
    ->prefix('v1/organizations/{organization}')
    ->group(function (): void {
        // Reads are open to any member.
        Route::apiResource('roles', RoleController::class)->only(['index', 'show']);
        Route::get('permissions', [PermissionController::class, 'index'])->name('permissions.index');

        // Role mutations are administrative — require a verified email.
        Route::middleware('verified')->group(function (): void {
            Route::apiResource('roles', RoleController::class)->only(['store', 'update', 'destroy']);
        });
    });
