<?php

declare(strict_types=1);

namespace App\Domains\Identity\Http\Controllers;

use App\Domains\Identity\Http\Requests\ChangePasswordRequest;
use App\Domains\Identity\Services\AccountDataExporter;
use App\Domains\Identity\Services\AuthService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * NDPR/GDPR consumer self-service: change password, right-of-access export, and
 * right-to-erasure account deletion. All authenticated and user-scoped — the
 * caller acts only on their own account. Sits under the existing v1/auth group.
 */
final class AccountController extends Controller
{
    public function __construct(
        private readonly AuthService $auth,
        private readonly AccountDataExporter $exporter,
    ) {}

    /**
     * POST v1/auth/change-password — rotate the password and revoke other tokens.
     */
    public function changePassword(ChangePasswordRequest $request): JsonResponse
    {
        $this->auth->changePassword(
            $request->user(),
            (string) $request->input('current_password'),
            (string) $request->input('new_password'),
        );

        return response()->json(['message' => 'Password changed.', 'data' => ['changed' => true]]);
    }

    /**
     * GET v1/auth/me/export — NDPR right of access. Read-only.
     */
    public function export(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->exporter->export($request->user(), $request)]);
    }

    /**
     * DELETE v1/auth/me — NDPR erasure. 422 if the caller still owns a tenant.
     */
    public function destroy(Request $request): JsonResponse
    {
        $this->auth->deleteAccount($request->user());

        return response()->json(['message' => 'Account deleted.', 'data' => ['deleted' => true]]);
    }
}
