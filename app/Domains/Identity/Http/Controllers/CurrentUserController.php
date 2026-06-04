<?php

declare(strict_types=1);

namespace App\Domains\Identity\Http\Controllers;

use App\Domains\Identity\Http\Resources\UserResource;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

final class CurrentUserController extends Controller
{
    /**
     * The authenticated user with their org + role context.
     */
    public function show(Request $request): UserResource
    {
        $user = $request->user();

        $user->loadMissing(['currentOrganization', 'organizations', 'roles']);

        return UserResource::make($user);
    }
}
