<?php

declare(strict_types=1);

namespace App\Domains\Organization\Listeners;

use App\Domains\Auth\Events\UserRegistered;
use App\Domains\Organization\DTOs\CreateOrganizationData;
use App\Domains\Organization\Services\OrganizationService;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * Provisions a personal workspace for every newly registered user. Queued so
 * registration responses stay fast (queue-first design).
 */
final class CreateDefaultOrganization implements ShouldQueue
{
    public function __construct(private readonly OrganizationService $organizations) {}

    public function handle(UserRegistered $event): void
    {
        // Skip if the user already belongs to an organization (e.g. registered
        // via an invitation acceptance flow).
        if ($event->user->organizations()->exists()) {
            return;
        }

        $this->organizations->create(new CreateOrganizationData(
            name: "{$event->user->name}'s Workspace",
            owner: $event->user,
        ));
    }
}
