<?php

declare(strict_types=1);

namespace App\Domains\Cad\Events;

use App\Domains\Cad\Models\UnitDispatch;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A field unit was dispatched (assigned) to a command incident. Plain platform
 * event (NOT an organization record event) — the CAD layer is national/
 * cross-tenant.
 */
final class UnitDispatched
{
    use Dispatchable;

    public function __construct(
        public readonly UnitDispatch $dispatch,
        public readonly ?string $actorId = null,
    ) {}
}
