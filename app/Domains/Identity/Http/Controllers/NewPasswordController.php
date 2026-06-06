<?php

declare(strict_types=1);

namespace App\Domains\Identity\Http\Controllers;

use App\Domains\Identity\Http\Requests\ResetPasswordRequest;
use App\Domains\Identity\Services\PasswordResetService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;

final class NewPasswordController extends Controller
{
    public function __construct(private readonly PasswordResetService $passwords) {}

    /**
     * Reset the password using a valid token. On success every issued API token
     * is revoked (handled in the service) so all devices must re-authenticate.
     */
    public function store(ResetPasswordRequest $request): JsonResponse
    {
        $status = $this->passwords->reset($request->validated());

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages([
                'email' => [__($status)],
            ]);
        }

        return response()->json(['message' => __($status)]);
    }
}
