<?php

declare(strict_types=1);

namespace App\Domains\Ledger\Http\Middleware;

use App\Domains\Ledger\Models\LedgerSource;
use App\Domains\Ledger\Support\Enums\SourceStatus;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Authenticates a Ledger ingest request by its X-Ledger-Key header (NOT sanctum).
 *
 * Hashes the incoming key, looks up the matching source, and:
 *   - 404 {error: unknown_source}   if no source matches,
 *   - 409 {error: source_suspended} / {error: source_revoked} if not active,
 *   - otherwise binds the resolved source onto the request and proceeds.
 *
 * The resolved source is available downstream via $request->attributes->get('ledger_source').
 */
final class AuthenticateLedgerSource
{
    public function handle(Request $request, Closure $next): Response
    {
        $rawKey = $request->header('X-Ledger-Key');

        if (! is_string($rawKey) || $rawKey === '') {
            return $this->reject('unknown_source', Response::HTTP_NOT_FOUND);
        }

        $source = LedgerSource::query()
            ->where('key_hash', hash('sha256', $rawKey))
            ->first();

        if ($source === null) {
            return $this->reject('unknown_source', Response::HTTP_NOT_FOUND);
        }

        if ($source->status !== SourceStatus::Active) {
            return $this->reject('source_'.$source->status->value, Response::HTTP_CONFLICT);
        }

        $request->attributes->set('ledger_source', $source);

        return $next($request);
    }

    /**
     * Build a rejection response whose `error` code survives the platform error
     * envelope: WrapApiResponse rebuilds 4xx bodies as
     * { success:false, message, errors }, preserving an explicit `errors` key —
     * so the code is exposed at both `message` and `errors.error`.
     */
    private function reject(string $code, int $status): JsonResponse
    {
        return new JsonResponse([
            'message' => $code,
            'errors' => ['error' => $code],
        ], $status);
    }
}
