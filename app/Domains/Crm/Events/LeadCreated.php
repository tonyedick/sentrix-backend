<?php

declare(strict_types=1);

namespace App\Domains\Crm\Events;

use App\Domains\Crm\Models\Lead;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A lead was created in the pipeline.
 *
 * PLATFORM-scoped: a lead has no organization_id until it is converted, so this
 * does NOT extend OrganizationRecordEvent (which broadcasts + audits per tenant).
 * It is a plain dispatchable event other code may listen to if needed.
 */
final class LeadCreated
{
    use Dispatchable;

    public function __construct(
        public readonly Lead $lead,
        public readonly ?string $actorId = null,
    ) {}
}
