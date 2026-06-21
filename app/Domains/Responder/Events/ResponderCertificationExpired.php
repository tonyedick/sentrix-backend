<?php

declare(strict_types=1);

namespace App\Domains\Responder\Events;

use App\Domains\Shared\Events\OrganizationRecordEvent;

/**
 * A responder certification has lapsed; the linked capability should no longer
 * be treated as active.
 */
final class ResponderCertificationExpired extends OrganizationRecordEvent
{
    public function action(): string
    {
        return 'responder.certification_expired';
    }
}
