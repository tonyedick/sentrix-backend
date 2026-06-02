<?php

declare(strict_types=1);

namespace App\Domains\Authorization\Database\Seeders;

use App\Domains\Authorization\Services\RoleService;
use Illuminate\Database\Seeder;

final class PermissionCatalogueSeeder extends Seeder
{
    public function run(RoleService $roles): void
    {
        // Seeds the global permission catalogue. Roles are provisioned per
        // organization at creation time, not here.
        $roles->syncPermissionCatalogue();
    }
}
