<?php

declare(strict_types=1);

namespace App\Domains\Organization\Services;

use App\Domains\Authorization\Services\RoleService;
use App\Domains\Authorization\Support\Enums\OrganizationRole;
use App\Domains\Organization\DTOs\CreateOrganizationData;
use App\Domains\Organization\Events\OrganizationCreated;
use App\Domains\Organization\Events\OrganizationDeleted;
use App\Domains\Organization\Events\OwnershipTransferred;
use App\Domains\Organization\Models\Organization;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final readonly class OrganizationService
{
    public function __construct(
        private RoleService $roles,
        private MembershipService $memberships,
    ) {}

    /**
     * Create an organization, provision its default roles, attach the owner,
     * and switch the owner's active context to it — atomically.
     *
     * `slug` is unique at the database level, so two concurrent creates of the
     * same name can race past the in-app uniqueness check; the collision is
     * retried, on which {@see uniqueSlug} sees the now-committed row and yields
     * a fresh suffix.
     */
    public function create(CreateOrganizationData $data): Organization
    {
        return $this->retryOnSlugCollision(fn (): Organization => DB::transaction(function () use ($data): Organization {
            $organization = Organization::create([
                'name' => $data->name,
                'slug' => $this->uniqueSlug($data->slug ?? $data->name),
                'owner_id' => $data->owner->getKey(),
            ]);

            // Roles are team-scoped, so they must exist before assigning the owner.
            $this->roles->provisionDefaultRoles($organization);

            $this->memberships->addMember($organization, $data->owner, OrganizationRole::owner()->value);

            $data->owner->forceFill(['current_organization_id' => $organization->getKey()])->save();

            event(new OrganizationCreated($organization));

            return $organization;
        }));
    }

    public function update(Organization $organization, ?string $name, ?string $slug): Organization
    {
        return $this->retryOnSlugCollision(function () use ($organization, $name, $slug): Organization {
            $attributes = array_filter([
                'name' => $name,
                'slug' => $slug !== null ? $this->uniqueSlug($slug, $organization) : null,
            ], static fn (mixed $v): bool => $v !== null);

            $organization->update($attributes);

            return $organization->refresh();
        });
    }

    /**
     * Soft-delete an organization. Members who were "acting within" it have their
     * active-organization pointer cleared so they are not left scoped to a
     * tenant that resolves to a 404. Emits OrganizationDeleted for the audit trail.
     */
    public function delete(Organization $organization): void
    {
        DB::transaction(function () use ($organization): void {
            User::query()
                ->where('current_organization_id', $organization->getKey())
                ->update(['current_organization_id' => null]);

            $organization->delete();

            event(new OrganizationDeleted($organization));
        });
    }

    /**
     * Transfer ownership to another current member. The new owner is granted the
     * OrganizationAdmin role; the former owner keeps their membership (and may
     * then be managed/removed like any other member).
     */
    public function transferOwnership(Organization $organization, User $newOwner): Organization
    {
        if (! $newOwner->belongsToOrganization($organization)) {
            throw ValidationException::withMessages([
                'user_id' => ['The new owner must already be a member of the organization.'],
            ]);
        }

        if ($organization->owner_id === $newOwner->getKey()) {
            throw ValidationException::withMessages([
                'user_id' => ['This member already owns the organization.'],
            ]);
        }

        return DB::transaction(function () use ($organization, $newOwner): Organization {
            $previousOwnerId = $organization->owner_id;

            $organization->forceFill(['owner_id' => $newOwner->getKey()])->save();

            // The new owner should hold the admin role explicitly (the implicit
            // owner super-grant aside), so nothing breaks if ownership later moves.
            $this->memberships->updateRole($organization, $newOwner, OrganizationRole::OrganizationAdmin->value);

            event(new OwnershipTransferred($organization, auth()->id(), [
                'from' => $previousOwnerId,
                'to' => $newOwner->getKey(),
            ]));

            return $organization->refresh();
        });
    }

    /**
     * Switch a user's active organization (must be a member). A soft-deleted
     * organization is not a valid member relation, so this also rejects switching
     * into a deleted tenant.
     */
    public function switchCurrent(User $user, Organization $organization): void
    {
        abort_unless($user->belongsToOrganization($organization), 403);

        $user->forceFill(['current_organization_id' => $organization->getKey()])->save();
    }

    /**
     * Retry a closure when an organization slug uniquely collides (a concurrent
     * insert won the race). Non-slug unique violations are rethrown untouched.
     *
     * @param  callable(): Organization  $callback
     */
    private function retryOnSlugCollision(callable $callback, int $attempts = 4): Organization
    {
        for ($attempt = 1; ; $attempt++) {
            try {
                return $callback();
            } catch (UniqueConstraintViolationException $e) {
                if ($attempt >= $attempts || ! str_contains(Str::lower($e->getMessage()), 'slug')) {
                    throw $e;
                }
            }
        }
    }

    private function uniqueSlug(string $source, ?Organization $ignore = null): string
    {
        $base = Str::slug($source) ?: Str::lower(Str::random(8));
        $slug = $base;
        $i = 1;

        while (
            Organization::withTrashed()
                ->where('slug', $slug)
                ->when($ignore, fn ($q) => $q->whereKeyNot($ignore->getKey()))
                ->exists()
        ) {
            $slug = $base.'-'.(++$i);
        }

        return $slug;
    }
}
