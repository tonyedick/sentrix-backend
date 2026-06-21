<?php

declare(strict_types=1);

namespace App\Domains\Assignment\Events;

use App\Domains\Shared\Events\OrganizationRecordEvent;

/**
 * An accepted responder is en route to the scene. (Added so the dispatch
 * progression is a first-class domain event the incident timeline can project,
 * not just a row in assignment_events.)
 */
final class ResponderEnRoute extends OrganizationRecordEvent
{
    public function action(): string
    {
        return 'assignment.responder_en_route';
    }
}
