<?php

declare(strict_types=1);

namespace App\Domains\Identity\Http\Controllers;

use App\Domains\Identity\DTOs\LoginData;
use App\Domains\Identity\Http\Requests\LoginRequest;
use App\Domains\Identity\Http\Resources\UserResource;
use App\Domains\Identity\Services\AuthService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;

final class AuthenticatedSessionController extends Controller
{
    public function __construct(private readonly AuthService $auth) {}

    /**
     * Log in. Issues a Sanctum token when `device_name` is present (mobile),
     * otherwise establishes a stateful SPA session (Inertia web).
     */
    public function store(LoginRequest $request): JsonResponse
    {
        $data = LoginData::fromRequest($request);

        $user = $this->auth->verifyCredentials($data);

        $payload = ['user' => UserResource::make($user)];

        if ($data->wantsToken()) {
            // Short-lived access token + a rotating refresh token. `token` is kept
            // as an alias of the access token for backward compatibility.
            $pair = $this->auth->issueTokenPair($user, (string) $data->deviceName);
            $payload['token'] = $pair['access']->plainTextToken;
            $payload['access_token'] = $pair['access']->plainTextToken;
            $payload['refresh_token'] = $pair['refresh']->plainTextToken;
            $payload['expires_at'] = $pair['expires_at']->toIso8601String();
        } else {
            $this->auth->loginStateful($user, $data->remember);
            $request->session()->regenerate();
        }

        return response()->json($payload);
    }

    /**
     * Log out the current session or revoke the current token.
     */
    public function destroy(Request $request): JsonResponse
    {
        $user = $request->user();
        $token = $user?->currentAccessToken();

        if ($token instanceof PersonalAccessToken) {
            // Bearer-token client (mobile): revoke just this token.
            $token->delete();
        } else {
            // Stateful SPA session (web): tear the session down.
            auth('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return response()->json(['message' => __('Logged out.')]);
    }
}
