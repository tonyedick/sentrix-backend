<?php

declare(strict_types=1);

namespace App\Domains\Responder\Events;

use App\Domains\Shared\Events\OrganizationRecordEvent;

/**
 * A responder profile was created within an organization.
 */
final class ResponderRegistered extends OrganizationRecordEvent
{
    public function action(): string
    {
        return 'responder.registered';
    }
}
