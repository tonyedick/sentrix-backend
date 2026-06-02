<?php

declare(strict_types=1);

use App\Domains\Organization\Http\Controllers\InvitationController;
use App\Domains\Organization\Http\Controllers\MemberController;
use App\Domains\Organization\Http\Controllers\OrganizationController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->prefix('v1')->group(function (): void {
    // Organization collection — no active team context required.
    Route::get('organizations', [OrganizationController::class, 'index'])->name('organizations.index');
    Route::post('organizations', [OrganizationController::class, 'store'])->name('organizations.store');

    // Invitation acceptance is token-bound; the user is not yet a member.
    Route::post('invitations/{invitation}/accept', [InvitationController::class, 'accept'])->name('invitations.accept');

    // Organization-scoped endpoints — team context resolved + membership enforced.
    Route::middleware('organization.team')->prefix('organizations/{organization}')->group(function (): void {
        Route::get('/', [OrganizationController::class, 'show'])->name('organizations.show');
        Route::patch('/', [OrganizationController::class, 'update'])->name('organizations.update');
        Route::delete('/', [OrganizationController::class, 'destroy'])->name('organizations.destroy');
        Route::post('switch', [OrganizationController::class, 'switch'])->name('organizations.switch');

        Route::get('members', [MemberController::class, 'index'])->name('members.index');
        Route::patch('members/{user}', [MemberController::class, 'update'])->name('members.update');
        Route::delete('members/{user}', [MemberController::class, 'destroy'])->name('members.destroy');

        Route::get('invitations', [InvitationController::class, 'index'])->name('invitations.index');
        Route::post('invitations', [InvitationController::class, 'store'])->name('invitations.store');
        Route::delete('invitations/{invitation}', [InvitationController::class, 'destroy'])->name('invitations.destroy');
    });
});
