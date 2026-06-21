<?php

declare(strict_types=1);

namespace App\Domains\Identity\Http\Controllers;

use App\Domains\Identity\Http\Requests\ResendOtpRequest;
use App\Domains\Identity\Http\Requests\VerifyOtpRequest;
use App\Domains\Identity\Services\OtpService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Consumer OTP verification (mobile onboarding). Operates on the authenticated
 * user — the mobile client already holds a token from register/login.
 */
final class OtpController extends Controller
{
    public function __construct(private readonly OtpService $otp) {}

    public function verify(VerifyOtpRequest $request): JsonResponse
    {
        $verified = $this->otp->verify(
            $request->user(),
            $request->string('channel')->value(),
            $request->string('code')->value(),
        );

        if (! $verified) {
            return response()->json([
                'message' => __('The verification code is invalid or has expired.'),
                'errors' => ['code' => [__('The verification code is invalid or has expired.')]],
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return response()->json(['message' => __('Verified.')]);
    }

    public function resend(ResendOtpRequest $request): JsonResponse
    {
        $this->otp->issue($request->user(), $request->string('channel')->value());

        return response()->json(['message' => __('A new code has been sent.')]);
    }
}
