<?php

declare(strict_types=1);

namespace App\Domains\Identity\Http\Controllers;

use App\Domains\Identity\DTOs\RegisterUserData;
use App\Domains\Identity\Http\Requests\RegisterRequest;
use App\Domains\Identity\Http\Resources\UserResource;
use App\Domains\Identity\Services\AuthService;
use App\Domains\Identity\Services\OtpService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

final class RegisteredUserController extends Controller
{
    public function __construct(
        private readonly AuthService $auth,
        private readonly OtpService $otp,
    ) {}

    public function store(RegisterRequest $request): JsonResponse
    {
        $data = RegisterUserData::fromRequest($request);

        // Consumer (mobile, token) signups are user-scoped and served by the
        // monitoring org (ADR-0001), so they get no personal workspace; dashboard
        // (session) signups still get one.
        $isConsumer = $data->wantsToken();

        $user = $this->auth->register($data, provisionDefaultOrganization: ! $isConsumer);

        $payload = ['user' => UserResource::make($user)];

        if ($isConsumer) {
            // Mobile onboarding: issue a token plus an email OTP for the 6-digit
            // verification screen (the signed-link flow remains for web).
            $payload['token'] = $this->auth->issueToken($user, (string) $data->deviceName)->plainTextToken;
            $this->otp->issue($user, 'email');
        } else {
            $this->auth->loginStateful($user);
        }

        return response()->json($payload, JsonResponse::HTTP_CREATED);
    }
}
