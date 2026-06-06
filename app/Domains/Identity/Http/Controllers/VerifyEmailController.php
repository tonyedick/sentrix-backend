<?php

declare(strict_types=1);

namespace App\Domains\Identity\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Verifies an email address from a signed link. Stateless by design: the link's
 * signature plus the email hash authenticate the request, so it works for both
 * the cookie-based dashboard and token-based mobile clients (which may not carry
 * a session when the link is opened). On completion it redirects to the
 * front-end with a status flag rather than returning JSON.
 */
final class VerifyEmailController extends Controller
{
    public function __invoke(Request $request, string $id, string $hash): RedirectResponse
    {
        /** @var User $user */
        $user = User::findOrFail($id);

        if (! hash_equals($hash, sha1((string) $user->getEmailForVerification()))) {
            abort(Response::HTTP_FORBIDDEN, 'Invalid verification link.');
        }

        if (! $user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
            event(new Verified($user));
        }

        $base = rtrim((string) config('app.frontend_url'), '/');

        return redirect()->away("{$base}/email/verified?status=success");
    }
}
