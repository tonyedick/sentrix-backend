<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domains\Authorization\Database\Seeders\PermissionCatalogueSeeder;
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
    public function run(OrganizationService $organizations): void
    {
        // 1. Global permission catalogue.
        $this->call(PermissionCatalogueSeeder::class);

        // 2. A demo user with their own organization (owner role + default roles).
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
