<?php

declare(strict_types=1);

namespace App\Domains\Identity\Http\Controllers;

use App\Domains\Identity\Http\Resources\UserResource;
use App\Domains\Identity\Services\AuthService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;

final class RefreshTokenController extends Controller
{
    public function __construct(private readonly AuthService $auth) {}

    /**
     * Exchange a valid refresh token for a fresh access + refresh pair.
     *
     * The presented token must be a refresh token (ability `refresh`, not the
     * `*` wildcard of an access token). The old pair is rotated out, so each
     * refresh token is single-use.
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        if ($user === null) {
            abort(401);
        }

        $token = $user->currentAccessToken();
        $abilities = $token instanceof PersonalAccessToken ? ($token->abilities ?? []) : [];

        $isRefreshToken = in_array('refresh', $abilities, true) && ! in_array('*', $abilities, true);

        if (! $token instanceof PersonalAccessToken || ! $isRefreshToken) {
            abort(403, __('A valid refresh token is required.'));
        }

        // Reuse the originating device name so the rotated pair stays grouped.
        $deviceName = (string) preg_replace('/\s*\(refresh\)$/', '', (string) $token->name);
        if ($deviceName === '') {
            $deviceName = 'api';
        }

        // issueTokenPair revokes the old access + refresh tokens for this device
        // name before minting the new pair — i.e. the used refresh token is rotated.
        $pair = $this->auth->issueTokenPair($user, $deviceName);

        return response()->json([
            'user' => UserResource::make($user),
            'token' => $pair['access']->plainTextToken,
            'access_token' => $pair['access']->plainTextToken,
            'refresh_token' => $pair['refresh']->plainTextToken,
            'expires_at' => $pair['expires_at']->toIso8601String(),
        ]);
    }
}
