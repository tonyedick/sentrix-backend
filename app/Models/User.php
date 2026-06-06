<?php

declare(strict_types=1);

namespace App\Models;

use App\Domains\Authorization\Support\Enums\SystemRole;
use App\Domains\Organization\Models\Organization;
use App\Domains\Organization\Models\OrganizationMembership;
use App\Domains\Shared\Concerns\HasUuid;
use Database\Factories\UserFactory;
use Illuminate\Auth\MustVerifyEmail as MustVerifyEmailTrait;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens;
    use HasFactory;
    use HasRoles;
    use HasUuid;
    use MustVerifyEmailTrait;
    use Notifiable;
    use SoftDeletes;

    protected $fillable = [
        'name',
        'email',
        'password',
        'current_organization_id',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Organizations the user belongs to (through the membership pivot).
     *
     * @return BelongsToMany<Organization, $this>
     */
    public function organizations(): BelongsToMany
    {
        return $this->belongsToMany(Organization::class, 'organization_user')
            ->using(OrganizationMembership::class)
            ->withPivot(['id', 'joined_at'])
            ->withTimestamps();
    }

    /**
     * @return HasMany<OrganizationMembership, $this>
     */
    public function memberships(): HasMany
    {
        return $this->hasMany(OrganizationMembership::class);
    }

    /**
     * The organization the user is currently acting within.
     *
     * @return BelongsTo<Organization, $this>
     */
    public function currentOrganization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'current_organization_id');
    }

    public function belongsToOrganization(Organization|string $organization): bool
    {
        $id = $organization instanceof Organization ? $organization->getKey() : $organization;

        return $this->organizations()->whereKey($id)->exists();
    }

    /**
     * Whether the user holds the platform-global SuperAdmin role.
     *
     * Checked team-agnostically (team id NULL) so it resolves the same way
     * regardless of the active organization context. Memoised per request via
     * once() because Gate::before invokes it on every authorization check.
     */
    public function isSuperAdmin(): bool
    {
        return once(function (): bool {
            $tables = config('permission.table_names');
            $columns = config('permission.column_names');
            $rolePivot = $columns['role_pivot_key'] ?? 'role_id';

            return DB::table($tables['model_has_roles'])
                ->join($tables['roles'], $tables['roles'].'.id', '=', $tables['model_has_roles'].'.'.$rolePivot)
                ->where($tables['model_has_roles'].'.model_type', $this->getMorphClass())
                ->where($tables['model_has_roles'].'.'.$columns['model_morph_key'], $this->getKey())
                ->whereNull($tables['model_has_roles'].'.'.$columns['team_foreign_key'])
                ->where($tables['roles'].'.name', SystemRole::SuperAdmin->value)
                ->exists();
        });
    }
}
