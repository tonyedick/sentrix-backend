<?php

declare(strict_types=1);

namespace App\Domains\Identity\Http\Controllers;

use App\Domains\Identity\Http\Requests\ForgotPasswordRequest;
use App\Domains\Identity\Services\PasswordResetService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;

final class PasswordResetLinkController extends Controller
{
    public function __construct(private readonly PasswordResetService $passwords) {}

    /**
     * Email a password-reset link.
     *
     * Responds the same way whether or not the email exists, to avoid leaking
     * which addresses have accounts. Only an explicit throttle surfaces an error.
     */
    public function store(ForgotPasswordRequest $request): JsonResponse
    {
        $status = $this->passwords->sendResetLink($request->validated());

        if ($status === Password::RESET_THROTTLED) {
            throw ValidationException::withMessages([
                'email' => [__($status)],
            ]);
        }

        return response()->json([
            'message' => __('If an account exists for that email, a reset link is on its way.'),
        ]);
    }
}
