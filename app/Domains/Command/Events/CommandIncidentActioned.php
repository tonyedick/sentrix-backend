<?php

declare(strict_types=1);

namespace App\Domains\Command\Events;

use App\Domains\Command\Models\CommandIncident;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A command incident advanced its lifecycle (acknowledge / en_route / on_scene /
 * escalate / resolve / stand_down). Plain platform event (NOT an organization
 * record event) — the Command layer is national/cross-tenant.
 */
final class CommandIncidentActioned
{
    use Dispatchable;

    public function __construct(
        public readonly CommandIncident $incident,
        public readonly string $action,
        public readonly ?string $actorId = null,
    ) {}
}
