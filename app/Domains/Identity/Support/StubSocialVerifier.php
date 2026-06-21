<?php

declare(strict_types=1);

namespace App\Domains\Identity\Support;

use App\Domains\Identity\Contracts\SocialVerifier;
use App\Domains\Identity\DTOs\SocialIdentity;

/**
 * Non-production social verifier. Trusts the supplied token: it accepts either a
 * bare email ("amara@example.com") or a "providerUserId:email:Name" form, and
 * derives a stable provider user id. Swap for real Apple/Google drivers (which
 * validate the JWT signature) via config('sentrix.auth.social.driver').
 */
final class StubSocialVerifier implements SocialVerifier
{
    public function verify(string $provider, string $idToken): SocialIdentity
    {
        $parts = explode(':', $idToken);

        if (count($parts) >= 2) {
            [$providerUserId, $email] = $parts;
            $name = $parts[2] ?? $this->nameFromEmail($email);
        } else {
            $email = $idToken;
            $providerUserId = sha1($provider.':'.$email);
            $name = $this->nameFromEmail($email);
        }

        return new SocialIdentity(
            provider: $provider,
            providerUserId: $providerUserId,
            email: mb_strtolower($email),
            name: $name,
        );
    }

    private function nameFromEmail(string $email): string
    {
        $local = strstr($email, '@', true);

        return ucfirst($local !== false && $local !== '' ? $local : 'Member');
    }
}
