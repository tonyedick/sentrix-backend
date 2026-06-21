<?php

declare(strict_types=1);

namespace App\Domains\Organization\Listeners;

use App\Domains\Identity\Events\UserRegistered;
use App\Domains\Organization\DTOs\CreateOrganizationData;
use App\Domains\Organization\Services\OrganizationService;
use App\Domains\Shared\Listeners\QueuedListener;

/**
 * Provisions a personal workspace for every newly registered user. Queued so
 * registration responses stay fast (queue-first design).
 *
 * Idempotent: it no-ops when the user already belongs to an organization, so a
 * retry after a downstream failure never creates a duplicate workspace. The
 * provisioning itself runs in a DB transaction (see OrganizationService), so a
 * mid-flight failure rolls back cleanly and the retry starts from a clean slate.
 */
final class CreateDefaultOrganization extends QueuedListener
{
    public function __construct(private readonly OrganizationService $organizations) {}

    public function handle(UserRegistered $event): void
    {
        // Consumer/mobile signups are user-scoped (ADR-0001) and get no workspace.
        if (! $event->provisionDefaultOrganization) {
            return;
        }

        if ($event->user->organizations()->exists()) {
            return;
        }

        $this->organizations->create(new CreateOrganizationData(
            name: "{$event->user->name}'s Workspace",
            owner: $event->user,
        ));
    }
}
