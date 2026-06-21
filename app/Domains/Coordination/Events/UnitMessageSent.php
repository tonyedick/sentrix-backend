<?php

declare(strict_types=1);

namespace App\Domains\Coordination\Events;

use App\Domains\Coordination\Models\UnitMessage;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A CAD-to-radio / MDT message was sent on a unit's thread. Plain platform event
 * (NOT an organization record event) — the coordination layer is national/
 * cross-tenant.
 */
final class UnitMessageSent
{
    use Dispatchable;

    public function __construct(
        public readonly UnitMessage $message,
        public readonly ?string $actorId = null,
    ) {}
}
