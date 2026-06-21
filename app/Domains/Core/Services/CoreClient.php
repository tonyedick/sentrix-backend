<?php

declare(strict_types=1);

namespace App\Domains\Core\Services;

use App\Models\User;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Psr\Http\Message\StreamInterface;

/**
 * Thin HTTP wrapper around the external Sentrix Core agent.
 *
 * Keeps the controllers free of transport concerns: it owns the base URL, the
 * service-token + scope headers, and the stream/JSON plumbing. Every method is
 * fail-aware — callers ({@see \App\Domains\Core\Http\Controllers\CoreController})
 * decide the offline fallback; this class only reports whether Core is reachable
 * and forwards/returns raw payloads. Core being offline must NEVER break a
 * Laravel request, so nothing here throws on a missing endpoint.
 */
final readonly class CoreClient
{
    /**
     * Whether a Core endpoint is configured. When false, callers serve their
     * offline/simulated fallback rather than hitting the network.
     */
    public function isConfigured(): bool
    {
        return $this->endpoint() !== null;
    }

    /**
     * Open a streaming POST to Core's /api/core/chat and return the raw PSR-7
     * body so the controller can pump it through as Server-Sent Events.
     *
     * Returns the PSR-7 stream on success, or null if Core is unreachable /
     * responded with an error — the controller then emits the SSE fallback.
     *
     * @param  array<string, mixed>  $body
     */
    public function chatStream(array $body, User $user): ?StreamInterface
    {
        $endpoint = $this->endpoint();

        if ($endpoint === null) {
            return null;
        }

        try {
            $response = Http::withHeaders($this->authHeaders($user))
                ->withOptions(['stream' => true])
                ->timeout(0) // long-lived SSE: no read timeout
                ->connectTimeout($this->timeout())
                ->acceptJson()
                ->withHeaders(['Accept' => 'text/event-stream'])
                ->post($endpoint.'/api/core/chat', $body);

            if ($response->failed()) {
                return null;
            }

            return $response->toPsrResponse()->getBody();
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Execute a confirmed tool action via Core's /api/core/act (JSON, blocking).
     *
     * Returns Core's decoded JSON body on success, or null if Core is
     * unreachable / errored — the controller returns the "offline" payload so a
     * high-stakes action is never fabricated as successful.
     *
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>|null
     */
    public function act(array $body, User $user): ?array
    {
        $endpoint = $this->endpoint();

        if ($endpoint === null) {
            return null;
        }

        try {
            $response = Http::withHeaders($this->authHeaders($user))
                ->timeout($this->timeout())
                ->acceptJson()
                ->post($endpoint.'/api/core/act', $body);

            if ($response->failed()) {
                return null;
            }

            return $this->decode($response);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * The shared headers Core expects: the service token, the principal's
     * effective scopes (permission names, role names as a fallback), and the
     * acting user id.
     *
     * @return array<string, string>
     */
    private function authHeaders(User $user): array
    {
        return [
            'X-Service-Token' => (string) $this->apiKey(),
            'X-Sentrix-Scopes' => $this->scopesFor($user),
            'X-Sentrix-User' => (string) $user->getKey(),
        ];
    }

    /**
     * The user's effective scope list as a comma-joined string. Prefers granular
     * permission names; falls back to role names when no permissions resolve
     * (the Core agent gates tools on scopes, see SENTRIX_INTEGRATION.md).
     */
    private function scopesFor(User $user): string
    {
        $permissions = $user->getAllPermissions()
            ->pluck('name')
            ->filter()
            ->values();

        $scopes = $permissions->isNotEmpty()
            ? $permissions
            : $user->getRoleNames();

        return $scopes->implode(',');
    }

    /**
     * @return array<string, mixed>
     */
    private function decode(Response $response): array
    {
        $json = $response->json();

        return is_array($json) ? $json : [];
    }

    private function endpoint(): ?string
    {
        $endpoint = config('sentrix.core.endpoint');

        if (! is_string($endpoint) || trim($endpoint) === '') {
            return null;
        }

        return rtrim(trim($endpoint), '/');
    }

    private function apiKey(): ?string
    {
        $key = config('sentrix.core.api_key');

        return is_string($key) && $key !== '' ? $key : null;
    }

    private function timeout(): int
    {
        return (int) config('sentrix.core.timeout', 30);
    }
}
