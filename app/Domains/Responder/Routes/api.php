<?php

declare(strict_types=1);

use App\Domains\Responder\Http\Controllers\DutyShiftController;
use App\Domains\Responder\Http\Controllers\ResponderAssignmentController;
use App\Domains\Responder\Http\Controllers\ResponderCertificationController;
use App\Domains\Responder\Http\Controllers\ResponderController;
use App\Domains\Responder\Http\Controllers\ResponderLocationController;
use App\Domains\Responder\Http\Controllers\ResponderSkillController;
use App\Domains\Responder\Http\Controllers\SkillController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'organization.team'])
    ->prefix('v1/organizations/{organization}')
    ->group(function (): void {
        // Roster + status (slice 1)
        Route::get('responders', [ResponderController::class, 'index'])->name('responders.index');
        Route::post('responders', [ResponderController::class, 'store'])->name('responders.store');
        Route::get('responders/{responder}', [ResponderController::class, 'show'])->name('responders.show');
        Route::post('responders/{responder}/status', [ResponderController::class, 'changeStatus'])->name('responders.status');

        // Skill catalogue + responder skills (slice 2)
        Route::get('skills', [SkillController::class, 'index'])->name('skills.index');
        Route::post('skills', [SkillController::class, 'store'])->name('skills.store');
        Route::get('responders/{responder}/skills', [ResponderSkillController::class, 'index'])->name('responders.skills.index');
        Route::post('responders/{responder}/skills', [ResponderSkillController::class, 'store'])->name('responders.skills.store');
        Route::delete('responders/{responder}/skills/{skill}', [ResponderSkillController::class, 'destroy'])->name('responders.skills.destroy');

        // Responder's assignment participation — current + history (workspace)
        Route::get('responders/{responder}/assignments', [ResponderAssignmentController::class, 'index'])->name('responders.assignments.index');

        // Certifications (slice 2)
        Route::get('responders/{responder}/certifications', [ResponderCertificationController::class, 'index'])->name('responders.certifications.index');
        Route::post('responders/{responder}/certifications', [ResponderCertificationController::class, 'store'])->name('responders.certifications.store');
        Route::post('responders/{responder}/certifications/{certification}/verify', [ResponderCertificationController::class, 'verify'])->name('responders.certifications.verify');

        // Location tracking (slice 3)
        Route::get('responders-positions', [ResponderLocationController::class, 'positions'])->name('responders.positions');
        Route::post('responders/{responder}/locations', [ResponderLocationController::class, 'store'])->name('responders.locations.store');
        Route::get('responders/{responder}/locations', [ResponderLocationController::class, 'index'])->name('responders.locations.index');

        // Duty scheduling (slice 4)
        Route::get('responders/{responder}/shifts', [DutyShiftController::class, 'index'])->name('responders.shifts.index');
        Route::post('responders/{responder}/shifts', [DutyShiftController::class, 'store'])->name('responders.shifts.store');
        Route::delete('responders/{responder}/shifts/{shift}', [DutyShiftController::class, 'destroy'])->name('responders.shifts.destroy');
    });
