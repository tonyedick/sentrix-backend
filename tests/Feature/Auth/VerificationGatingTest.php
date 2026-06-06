<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Domains\Authorization\Database\Seeders\PermissionCatalogueSeeder;
use App\Domains\Authorization\Support\Enums\OrganizationRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Email verification gates administrative writes only. Life-safety actions and
 * reads stay open — a person in distress must never be blocked by an unverified
 * email.
 */
final class VerificationGatingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionCatalogueSeeder::class);
    }

    private function organizationOwnedBy(User $owner): string
    {
        return $this->actingAs($owner, 'sanctum')
            ->postJson('/api/v1/organizations', ['name' => 'Acme Inc'])
            ->json('data.id');
    }

    public function test_unverified_users_are_blocked_from_admin_writes(): void
    {
        $owner = User::factory()->unverified()->create();
        $organizationId = $this->organizationOwnedBy($owner);

        $this->actingAs($owner, 'sanctum')
            ->patchJson("/api/v1/organizations/{$organizationId}", ['name' => 'Renamed'])
            ->assertForbidden();

        $this->actingAs($owner, 'sanctum')
            ->postJson("/api/v1/organizations/{$organizationId}/invitations", [
                'email' => 'new@example.com',
                'role' => OrganizationRole::User->value,
            ])
            ->assertForbidden();
    }

    public function test_unverified_users_retain_reads_and_life_safety_access(): void
    {
        $owner = User::factory()->unverified()->create();
        $organizationId = $this->organizationOwnedBy($owner);

        // Read endpoint.
        $this->actingAs($owner, 'sanctum')
            ->getJson("/api/v1/organizations/{$organizationId}/members")
            ->assertOk();

        // Life-safety endpoint — must work regardless of verification.
        $this->actingAs($owner, 'sanctum')
            ->postJson("/api/v1/organizations/{$organizationId}/emergencies", ['severity' => 'high'])
            ->assertCreated();
    }

    public function test_verified_users_pass_admin_writes(): void
    {
        Notification::fake();
        $owner = User::factory()->create(); // verified by default
        $organizationId = $this->organizationOwnedBy($owner);

        $this->actingAs($owner, 'sanctum')
            ->postJson("/api/v1/organizations/{$organizationId}/invitations", [
                'email' => 'new@example.com',
                'role' => OrganizationRole::User->value,
            ])
            ->assertCreated();
    }
}
