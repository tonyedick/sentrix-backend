<?php

declare(strict_types=1);

namespace App\Domains\Insurance\Events;

use App\Domains\Shared\Events\OrganizationRecordEvent;

/**
 * A claim was filed against an insurance policy.
 */
final class ClaimFiled extends OrganizationRecordEvent
{
    public function action(): string
    {
        return 'claim.filed';
    }
}
