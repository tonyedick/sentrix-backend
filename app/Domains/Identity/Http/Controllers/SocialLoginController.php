<?php

declare(strict_types=1);

namespace App\Domains\Identity\Http\Controllers;

use App\Domains\Identity\Http\Requests\SocialLoginRequest;
use App\Domains\Identity\Http\Resources\UserResource;
use App\Domains\Identity\Services\AuthService;
use App\Domains\Identity\Services\SocialAuthService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

/**
 * "Continue with Apple / Google". Verifies the provider token, resolves or
 * provisions the user, and issues a Sanctum token for the mobile client.
 */
final class SocialLoginController extends Controller
{
    public function __construct(
        private readonly SocialAuthService $social,
        private readonly AuthService $auth,
    ) {}

    public function store(SocialLoginRequest $request): JsonResponse
    {
        $provider = $request->string('provider')->value();

        $user = $this->social->authenticate($provider, $request->string('id_token')->value());

        $device = (string) ($request->input('device_name') ?? "{$provider}-mobile");
        $token = $this->auth->issueToken($user, $device);

        return response()->json([
            'user' => UserResource::make($user),
            'token' => $token->plainTextToken,
        ]);
    }
}
