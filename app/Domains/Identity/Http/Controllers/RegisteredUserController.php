<?php

declare(strict_types=1);

namespace App\Domains\Identity\Http\Controllers;

use App\Domains\Identity\DTOs\RegisterUserData;
use App\Domains\Identity\Http\Requests\RegisterRequest;
use App\Domains\Identity\Http\Resources\UserResource;
use App\Domains\Identity\Services\AuthService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

final class RegisteredUserController extends Controller
{
    public function __construct(private readonly AuthService $auth) {}

    public function store(RegisterRequest $request): JsonResponse
    {
        $data = RegisterUserData::fromRequest($request);

        $user = $this->auth->register($data);

        $payload = ['user' => UserResource::make($user)];

        if ($data->wantsToken()) {
            $payload['token'] = $this->auth->issueToken($user, (string) $data->deviceName)->plainTextToken;
        } else {
            $this->auth->loginStateful($user);
        }

        return response()->json($payload, JsonResponse::HTTP_CREATED);
    }
}
