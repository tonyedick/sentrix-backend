<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Normalises every JSON response on the API into the platform envelope:
 *
 *   success: { "success": true,  "message": "...", "data": ... }
 *   error:   { "success": false, "message": "...", "errors": {...} }
 *
 * Applied at the edge (the `api` middleware group) so controllers stay thin and
 * return plain API Resources / paginators. Because Laravel's routing pipeline
 * renders thrown exceptions to responses *before* they propagate back up the
 * middleware stack, this also envelopes validation and error responses.
 *
 * Pagination metadata (`links`, `meta`) emitted by resource collections is
 * preserved alongside `data`.
 */
final class WrapApiResponse
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! $response instanceof JsonResponse) {
            return $response;
        }

        $status = $response->getStatusCode();

        // 204 No Content (and other empty-bodied responses) carry no payload.
        if ($status === Response::HTTP_NO_CONTENT) {
            return $response;
        }

        /** @var mixed $original */
        $original = $response->getData(true);

        // Already enveloped — leave it untouched (idempotent).
        if (is_array($original) && array_key_exists('success', $original)) {
            return $response;
        }

        $envelope = $status >= Response::HTTP_BAD_REQUEST
            ? $this->errorEnvelope($original)
            : $this->successEnvelope($original);

        $response->setData($envelope);

        return $response;
    }

    /**
     * @param  mixed  $original
     * @return array<string, mixed>
     */
    private function successEnvelope(mixed $original): array
    {
        $message = 'Success';
        $extra = [];
        $data = $original;

        if (is_array($original)) {
            if (array_key_exists('message', $original)) {
                $message = (string) $original['message'];
                unset($original['message']);
            }

            if (array_key_exists('data', $original)) {
                $data = $original['data'];

                foreach (['links', 'meta'] as $key) {
                    if (array_key_exists($key, $original)) {
                        $extra[$key] = $original[$key];
                    }
                }
            } else {
                $data = $original === [] ? null : $original;
            }
        }

        return array_merge(['success' => true, 'message' => $message, 'data' => $data], $extra);
    }

    /**
     * @param  mixed  $original
     * @return array<string, mixed>
     */
    private function errorEnvelope(mixed $original): array
    {
        $message = is_array($original) ? (string) ($original['message'] ?? 'Error') : 'Error';
        $errors = is_array($original) ? ($original['errors'] ?? null) : null;

        return [
            'success' => false,
            'message' => $message,
            'errors' => $errors ?? (object) [],
        ];
    }
}
