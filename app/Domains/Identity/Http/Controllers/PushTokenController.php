<?php

declare(strict_types=1);

namespace App\Domains\Identity\Http\Controllers;

use App\Domains\Identity\Http\Requests\RegisterPushTokenRequest;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Manages the authenticated user's push-notification tokens (one device's token
 * per call). Tokens are stored on `users.push_tokens` and consumed by the push
 * notification channel.
 */
final class PushTokenController extends Controller
{
    public function store(RegisterPushTokenRequest $request): JsonResponse
    {
        $user = $request->user();
        $token = $request->string('token')->value();

        $tokens = $user->push_tokens ?? [];
        if (! in_array($token, $tokens, true)) {
            $tokens[] = $token;
            $user->forceFill(['push_tokens' => array_values($tokens)])->save();
        }

        return response()->json(['message' => __('Push token registered.')], Response::HTTP_CREATED);
    }

    public function destroy(RegisterPushTokenRequest $request): Response
    {
        $user = $request->user();
        $token = $request->string('token')->value();

        $tokens = array_values(array_filter($user->push_tokens ?? [], static fn (string $t): bool => $t !== $token));
        $user->forceFill(['push_tokens' => $tokens])->save();

        return response()->noContent();
    }
}
