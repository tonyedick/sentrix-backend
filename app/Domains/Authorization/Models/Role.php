<?php

declare(strict_types=1);

namespace App\Domains\Authorization\Models;

use App\Domains\Shared\Concerns\HasUuid;
use Spatie\Permission\Models\Role as SpatieRole;

/**
 * UUID-keyed, organization-scoped role.
 *
 * The organization_id (team key) is populated automatically by Spatie from the
 * active team id set on each request by SetOrganizationTeam middleware.
 */
final class Role extends SpatieRole
{
    use HasUuid;
}
