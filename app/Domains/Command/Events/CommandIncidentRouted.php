<?php

declare(strict_types=1);

namespace App\Domains\Command\Events;

use App\Domains\Command\Models\CommandIncident;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * An incident was routed to a lead command and an envelope was opened. Plain
 * platform event (NOT an organization record event) — the Command layer is
 * national/cross-tenant. Broadcasting to a command channel can come later.
 */
final class CommandIncidentRouted
{
    use Dispatchable;

    public function __construct(
        public readonly CommandIncident $incident,
    ) {}
}
