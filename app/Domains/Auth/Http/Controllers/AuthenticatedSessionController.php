<?php

declare(strict_types=1);

namespace App\Domains\Auth\Http\Controllers;

use App\Domains\Auth\DTOs\LoginData;
use App\Domains\Auth\Http\Requests\LoginRequest;
use App\Domains\Auth\Http\Resources\UserResource;
use App\Domains\Auth\Services\AuthService;
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
            $payload['token'] = $this->auth->issueToken($user, (string) $data->deviceName)->plainTextToken;
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
