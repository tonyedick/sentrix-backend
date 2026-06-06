<?php

declare(strict_types=1);

namespace App\Domains\Organization\Events;

use App\Domains\Shared\Events\OrganizationRecordEvent;

/**
 * Organization ownership moved from one member to another. Broadcast to the org
 * channel (member lists / admin UIs react) and audited. Context carries
 * `from` and `to` user ids.
 */
final class OwnershipTransferred extends OrganizationRecordEvent
{
    public function action(): string
    {
        return 'organization.ownership_transferred';
    }

    /**
     * The record here IS the organization, so its tenant id is its own key — not
     * an `organization_id` column (which it does not have). Overrides the base,
     * which assumes the record is a tenant-scoped child model. This drives both
     * the broadcast channel and the audit organization id.
     */
    public function organizationId(): string
    {
        return (string) $this->record->getKey();
    }
}
