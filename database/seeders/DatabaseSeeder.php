<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domains\Authorization\Database\Seeders\PermissionCatalogueSeeder;
use App\Domains\Authorization\Services\RoleService;
use App\Domains\Organization\DTOs\CreateOrganizationData;
use App\Domains\Organization\Services\OrganizationService;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * NOTE: WithoutModelEvents is intentionally NOT used — the HasUuid trait
     * generates primary keys on the model `creating` event.
     */
    public function run(OrganizationService $organizations, RoleService $roles): void
    {
        // 1. Global permission catalogue + system roles (SuperAdmin).
        $this->call(PermissionCatalogueSeeder::class);

        // 2. A platform-global SuperAdmin operator (no organization required).
        $admin = User::factory()->create([
            'name' => 'Platform Admin',
            'email' => 'admin@sentrix.test',
        ]);

        $roles->assignSuperAdmin($admin);

        // 3. A demo user who owns an organization (provisioned with the default
        //    organization-scoped role set; the creator becomes OrganizationAdmin).
        $user = User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        $organizations->create(new CreateOrganizationData(
            name: 'Acme Inc',
            owner: $user,
        ));
    }
}
