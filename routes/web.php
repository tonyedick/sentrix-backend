<?php

use Illuminate\Support\Facades\Route;

/*
 | The Operations Dashboard is a client-rendered React SPA. Every non-API path
 | returns the same shell; React Router takes over client-side routing. API,
 | broadcasting-auth, Sanctum and health endpoints are excluded so they keep
 | their own handlers.
 */
Route::view('/{any?}', 'app')
    ->where('any', '^(?!api|broadcasting|sanctum|up|storage|build).*$');
