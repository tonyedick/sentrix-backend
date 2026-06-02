<?php

declare(strict_types=1);

namespace App\Domains\Authorization\Models;

use App\Domains\Shared\Concerns\HasUuid;
use Spatie\Permission\Models\Permission as SpatiePermission;

/**
 * UUID-keyed permission. Permissions are global (not team-scoped); roles bundle
 * them per organization.
 */
final class Permission extends SpatiePermission
{
    use HasUuid;
}
