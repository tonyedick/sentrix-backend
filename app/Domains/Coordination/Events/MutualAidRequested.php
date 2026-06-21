<?php

declare(strict_types=1);

namespace App\Domains\Coordination\Events;

use App\Domains\Coordination\Models\MutualAidRequest;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A command requested mutual aid from another agency's command. Plain platform
 * event (NOT an organization record event) — the coordination layer is national/
 * cross-tenant.
 */
final class MutualAidRequested
{
    use Dispatchable;

    public function __construct(
        public readonly MutualAidRequest $request,
        public readonly ?string $actorId = null,
    ) {}
}
