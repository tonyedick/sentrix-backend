<?php

declare(strict_types=1);

namespace App\Domains\Insurance\Events;

use App\Domains\Shared\Events\OrganizationRecordEvent;

/**
 * A risk-priced insurance policy was created for an organization.
 */
final class PolicyCreated extends OrganizationRecordEvent
{
    public function action(): string
    {
        return 'policy.created';
    }
}
