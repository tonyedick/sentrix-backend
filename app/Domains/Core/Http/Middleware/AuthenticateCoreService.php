<?php

declare(strict_types=1);

namespace App\Domains\Core\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Authenticates a machine (service) caller to the inbound Core event endpoint by
 * the X-Service-Token header — NOT sanctum. This is the trust boundary for a
 * product/detection posting an event, so it is closed by default:
 *
 *   - api_key configured: the header must match it (constant-time hash_equals);
 *     401 otherwise.
 *   - api_key NOT configured: rejected (closed by default) EXCEPT in the local /
 *     testing environments, where it is open so the bridge is testable without a
 *     secret. Never open in production.
 */
final class AuthenticateCoreService
{
    public function handle(Request $request, Closure $next): Response
    {
        $configured = config('sentrix.core.api_key');
        $configured = is_string($configured) && $configured !== '' ? $configured : null;

        if ($configured === null) {
            // No secret set: open only in dev/test, otherwise closed by default.
            if (app()->environment('local', 'testing')) {
                return $next($request);
            }

            return $this->reject();
        }

        $presented = $request->header('X-Service-Token');

        if (! is_string($presented) || ! hash_equals($configured, $presented)) {
            return $this->reject();
        }

        return $next($request);
    }

    private function reject(): JsonResponse
    {
        return new JsonResponse([
            'message' => 'unauthorized',
            'errors' => ['error' => 'unauthorized'],
        ], Response::HTTP_UNAUTHORIZED);
    }
}
