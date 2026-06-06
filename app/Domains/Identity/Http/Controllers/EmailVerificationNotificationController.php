<?php

declare(strict_types=1);

namespace App\Domains\Identity\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class EmailVerificationNotificationController extends Controller
{
    /**
     * Resend the email-verification link to the authenticated user.
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->hasVerifiedEmail()) {
            return response()->json(['message' => 'Your email address is already verified.']);
        }

        $user->sendEmailVerificationNotification();

        return response()->json(['message' => 'A fresh verification link has been sent to your email address.']);
    }
}
