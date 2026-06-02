<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

/*
 | Domain API routes (auth, authorization, organizations) are registered by
 | each domain's service provider from app/Domains/<Domain>/Routes/api.php.
 | This file only holds top-level, cross-cutting endpoints.
 */

Route::get('v1/health', static fn (): array => [
    'status' => 'ok',
    'version' => config('app.version', '1.0.0'),
])->name('health');
