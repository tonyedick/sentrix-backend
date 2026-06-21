<?php

declare(strict_types=1);

namespace App\Domains\Coordination\Events;

use App\Domains\Coordination\Models\Tasking;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * An HQ duty tasking was routed to an assignee. Plain platform event (NOT an
 * organization record event) — the coordination layer is national/cross-tenant.
 */
final class TaskingRouted
{
    use Dispatchable;

    public function __construct(
        public readonly Tasking $tasking,
        public readonly ?string $actorId = null,
    ) {}
}
