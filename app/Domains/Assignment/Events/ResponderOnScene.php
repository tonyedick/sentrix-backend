<?php

declare(strict_types=1);

namespace App\Domains\Assignment\Events;

use App\Domains\Shared\Events\OrganizationRecordEvent;

/**
 * A responder has arrived on scene.
 */
final class ResponderOnScene extends OrganizationRecordEvent
{
    public function action(): string
    {
        return 'assignment.responder_on_scene';
    }
}
