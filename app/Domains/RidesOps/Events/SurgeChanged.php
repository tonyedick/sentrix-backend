<?php

declare(strict_types=1);

namespace App\Domains\RidesOps\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * The manual surge was pinned or released by an operator. `multiplier` is the
 * new effective manual surge (1.0 when released). Plain platform event.
 */
final class SurgeChanged
{
    use Dispatchable;

    public function __construct(
        public readonly float $multiplier,
        public readonly bool $pinned,
        public readonly ?string $zone,
        public readonly ?string $actorId,
    ) {}
}
