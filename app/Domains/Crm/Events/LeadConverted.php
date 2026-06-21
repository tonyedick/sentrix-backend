<?php

declare(strict_types=1);

namespace App\Domains\Crm\Events;

use App\Domains\Crm\Models\Lead;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A won lead was converted into a live tenant (organization).
 *
 * PLATFORM-scoped plain event (see LeadCreated). The tenant creation itself is
 * already broadcast + audited inside the Organization domain via
 * OrganizationCreated, so this only signals the CRM-side conversion.
 */
final class LeadConverted
{
    use Dispatchable;

    public function __construct(
        public readonly Lead $lead,
        public readonly string $organizationId,
        public readonly ?string $actorId = null,
    ) {}
}
