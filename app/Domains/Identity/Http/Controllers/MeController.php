<?php

declare(strict_types=1);

namespace App\Domains\Identity\Http\Controllers;

use App\Domains\Identity\Http\Requests\UpdateProfileRequest;
use App\Domains\Identity\Http\Resources\UserResource;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

/**
 * The authenticated consumer's own profile (user-scoped, no organization).
 */
final class MeController extends Controller
{
    public function show(Request $request): UserResource
    {
        return UserResource::make($request->user());
    }

    public function update(UpdateProfileRequest $request): UserResource
    {
        $user = $request->user();
        $attributes = $request->only(['name', 'phone']);

        // Changing the phone number invalidates a prior phone verification.
        if (array_key_exists('phone', $attributes) && $attributes['phone'] !== $user->phone) {
            $user->phone_verified_at = null;
        }

        $user->fill($attributes);

        // Merge preferences so a partial update never wipes other keys.
        if ($request->has('preferences')) {
            $user->preferences = array_merge($user->preferences ?? [], (array) $request->input('preferences'));
        }

        $user->save();

        return UserResource::make($user->refresh());
    }
}
