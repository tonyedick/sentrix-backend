<?php

declare(strict_types=1);

namespace App\Domains\Identity\Contracts;

use App\Domains\Identity\DTOs\SocialIdentity;

/**
 * Verifies a provider id-token (Apple / Google) and returns the authenticated
 * identity. Real drivers validate the token signature against the provider's
 * keys; the stub driver (non-production) trusts the token for local/testing.
 */
interface SocialVerifier
{
    public function verify(string $provider, string $idToken): SocialIdentity;
}
