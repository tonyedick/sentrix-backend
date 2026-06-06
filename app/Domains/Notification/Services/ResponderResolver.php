<?php

declare(strict_types=1);

namespace App\Domains\Notification\Services;

use App\Domains\Organization\Models\Organization;
use App\Models\User;
use Illuminate\Support\Collection;
use Spatie\Permission\PermissionRegistrar;

/**
 * Resolves which members of an organization should be notified of an operational
 * event — those holding the relevant ability — within the organization's team
 * context. Optionally excludes a user (e.g. the person who raised the event).
 */
final readonly class ResponderResolver
{
    public function __construct(private PermissionRegistrar $registrar) {}

    /**
     * Members of the organization who hold $permission, excluding $excludeUserId.
     *
     * @return Collection<int, User>
     */
    public function withPermission(Organization $organization, string $permission, ?string $excludeUserId = null): Collection
    {
        // Scope role/permission resolution to this organization before checking.
        $this->registrar->setPermissionsTeamId($organization->getKey());

        return $organization->members()
            ->get()
            ->filter(static fn (User $member): bool => $member->getKey() !== $excludeUserId && $member->can($permission))
            ->values();
    }
}
