<?php

declare(strict_types=1);

use App\Domains\VisionGuard\Http\Controllers\ConsumerMediaController;
use App\Domains\VisionGuard\Http\Controllers\ConsumerSourceController;
use Illuminate\Support\Facades\Route;

/*
 | Vision Guard — consumer, user-scoped (ADR-0001). Camera sources + media.
 */
Route::middleware('auth:sanctum')->prefix('v1/me')->group(function (): void {
    Route::get('sources', [ConsumerSourceController::class, 'index'])->name('me.sources.index');
    Route::post('sources', [ConsumerSourceController::class, 'store'])->name('me.sources.store');
    Route::patch('sources/{source}', [ConsumerSourceController::class, 'update'])->name('me.sources.update');
    Route::delete('sources/{source}', [ConsumerSourceController::class, 'destroy'])->name('me.sources.destroy');

    Route::get('media', [ConsumerMediaController::class, 'index'])->name('me.media.index');
    Route::post('media/upload-url', [ConsumerMediaController::class, 'uploadUrl'])->name('me.media.upload-url');
    Route::post('media', [ConsumerMediaController::class, 'store'])->name('me.media.store');

    // Local-storage upload receiver (dev only; production PUTs straight to S3/GCS).
    // {key} contains slashes (media/{userId}/{uuid}), hence the .* constraint.
    Route::put('media/local-upload/{key}', [ConsumerMediaController::class, 'localUpload'])
        ->where('key', '.*')
        ->name('me.media.local-upload');
});
