<?php

declare(strict_types=1);

namespace App\Domains\Identity\DTOs;

/**
 * A verified identity from a social provider.
 */
final readonly class SocialIdentity
{
    public function __construct(
        public string $provider,
        public string $providerUserId,
        public string $email,
        public string $name,
    ) {}
}
