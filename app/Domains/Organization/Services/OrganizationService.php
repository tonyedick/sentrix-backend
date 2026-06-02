<?php

declare(strict_types=1);

namespace App\Domains\Organization\Services;

use App\Domains\Authorization\Services\RoleService;
use App\Domains\Authorization\Support\Enums\DefaultRole;
use App\Domains\Organization\DTOs\CreateOrganizationData;
use App\Domains\Organization\Events\OrganizationCreated;
use App\Domains\Organization\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final readonly class OrganizationService
{
    public function __construct(
        private RoleService $roles,
        private MembershipService $memberships,
    ) {}

    /**
     * Create an organization, provision its default roles, attach the owner,
     * and switch the owner's active context to it — atomically.
     */
    public function create(CreateOrganizationData $data): Organization
    {
        return DB::transaction(function () use ($data): Organization {
            $organization = Organization::create([
                'name' => $data->name,
                'slug' => $this->uniqueSlug($data->slug ?? $data->name),
                'owner_id' => $data->owner->getKey(),
            ]);

            // Roles are team-scoped, so they must exist before assigning the owner.
            $this->roles->provisionDefaultRoles($organization);

            $this->memberships->addMember($organization, $data->owner, DefaultRole::Owner->value);

            $data->owner->forceFill(['current_organization_id' => $organization->getKey()])->save();

            event(new OrganizationCreated($organization));

            return $organization;
        });
    }

    public function update(Organization $organization, ?string $name, ?string $slug): Organization
    {
        $attributes = array_filter([
            'name' => $name,
            'slug' => $slug !== null ? $this->uniqueSlug($slug, $organization) : null,
        ], static fn (mixed $v): bool => $v !== null);

        $organization->update($attributes);

        return $organization->refresh();
    }

    public function delete(Organization $organization): void
    {
        $organization->delete();
    }

    /**
     * Switch a user's active organization (must be a member).
     */
    public function switchCurrent(User $user, Organization $organization): void
    {
        abort_unless($user->belongsToOrganization($organization), 403);

        $user->forceFill(['current_organization_id' => $organization->getKey()])->save();
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
