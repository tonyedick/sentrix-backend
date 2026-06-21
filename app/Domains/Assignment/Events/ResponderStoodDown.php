<?php

declare(strict_types=1);

namespace App\Domains\Assignment\Events;

use App\Domains\Shared\Events\OrganizationRecordEvent;

/**
 * A responder was stood down from an assignment (reassignment or cancellation)
 * and released back to availability. Context carries the reason.
 */
final class ResponderStoodDown extends OrganizationRecordEvent
{
    public function action(): string
    {
        return 'assignment.responder_stood_down';
    }
}
