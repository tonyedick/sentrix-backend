<?php

declare(strict_types=1);

namespace Tests\Feature\Crm;

use App\Domains\Authorization\Database\Seeders\PermissionCatalogueSeeder;
use App\Domains\Authorization\Services\RoleService;
use App\Domains\Crm\Models\Lead;
use App\Domains\Organization\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

final class LeadLifecycleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake(); // tenant provisioning (org creation) has queued side effects
        $this->seed(PermissionCatalogueSeeder::class);
    }

    private function superAdmin(): User
    {
        $admin = User::factory()->create();
        app(RoleService::class)->assignSuperAdmin($admin);

        return $admin;
    }

    public function test_full_lead_lifecycle_create_update_quote_convert(): void
    {
        $admin = $this->superAdmin();

        // Create.
        $leadId = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/v1/leads', [
                'name' => 'Unilorin',
                'client_type' => 'university',
                'contact_name' => "Bursar's Office",
                'contact_email' => 'ICT@unilorin.example',
                'region' => 'Kwara',
                'source' => 'inbound',
            ])
            ->assertCreated()
            ->assertJsonPath('data.stage', 'new')
            ->assertJsonPath('data.client_type', 'university')
            ->assertJsonPath('data.contact_email', 'ict@unilorin.example')
            ->json('data.id');

        // Update stage + notes.
        $this->actingAs($admin, 'sanctum')
            ->patchJson("/api/v1/leads/{$leadId}", [
                'stage' => 'qualified',
                'notes' => 'Pilot at main gate.',
            ])
            ->assertOk()
            ->assertJsonPath('data.stage', 'qualified')
            ->assertJsonPath('data.notes', 'Pilot at main gate.');

        // Attach a quote snapshot.
        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/leads/{$leadId}/quote", [
                'quote' => ['planLabel' => 'Business', 'monthly' => 250000, 'currency' => 'NGN'],
            ])
            ->assertOk()
            ->assertJsonPath('data.quote.planLabel', 'Business')
            ->assertJsonPath('data.quote.monthly', 250000);

        $this->assertDatabaseCount('organizations', 0);

        // Convert -> provisions a live tenant.
        $orgId = $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/leads/{$leadId}/convert")
            ->assertOk()
            ->assertJsonPath('data.lead.stage', 'won')
            ->json('data.organization_id');

        $this->assertNotNull($orgId);
        $this->assertDatabaseHas('organizations', ['id' => $orgId, 'name' => 'Unilorin']);
        $this->assertDatabaseHas('leads', [
            'id' => $leadId,
            'stage' => 'won',
            'converted_organization_id' => $orgId,
        ]);
        // An owner user was provisioned from the lead's contact email.
        $this->assertDatabaseHas('users', ['email' => 'ict@unilorin.example']);
    }

    public function test_convert_is_idempotent(): void
    {
        $admin = $this->superAdmin();

        $leadId = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/v1/leads', [
                'name' => 'Acme Estate',
                'client_type' => 'estate',
                'contact_name' => 'Estate Manager',
                'contact_email' => 'admin@acme.example',
            ])
            ->json('data.id');

        $firstOrgId = $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/leads/{$leadId}/convert")
            ->assertOk()
            ->json('data.organization_id');

        $secondOrgId = $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/leads/{$leadId}/convert")
            ->assertOk()
            ->json('data.organization_id');

        $this->assertSame($firstOrgId, $secondOrgId);
        $this->assertSame(1, Organization::query()->count());
    }

    public function test_a_lost_lead_cannot_be_converted(): void
    {
        $admin = $this->superAdmin();

        $leadId = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/v1/leads', [
                'name' => 'Dead Co',
                'client_type' => 'company',
                'contact_name' => 'Nobody',
                'contact_email' => 'nobody@dead.example',
            ])
            ->json('data.id');

        $this->actingAs($admin, 'sanctum')
            ->patchJson("/api/v1/leads/{$leadId}", ['stage' => 'lost'])
            ->assertOk();

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/leads/{$leadId}/convert")
            ->assertStatus(422);

        $this->assertSame(0, Organization::query()->count());
    }

    public function test_a_non_superadmin_is_forbidden(): void
    {
        $outsider = User::factory()->create();

        $this->actingAs($outsider, 'sanctum')
            ->getJson('/api/v1/leads')
            ->assertForbidden();

        $this->actingAs($outsider, 'sanctum')
            ->postJson('/api/v1/leads', [
                'name' => 'X',
                'client_type' => 'other',
                'contact_name' => 'Y',
                'contact_email' => 'y@x.example',
            ])
            ->assertForbidden();

        // A lead created by an admin cannot be converted by a non-superadmin.
        $admin = $this->superAdmin();
        $lead = Lead::query()->create([
            'created_by' => $admin->getKey(),
            'name' => 'Guarded',
            'client_type' => 'company',
            'contact_name' => 'Z',
            'contact_email' => 'z@x.example',
            'stage' => 'qualified',
        ]);

        $this->actingAs($outsider, 'sanctum')
            ->postJson("/api/v1/leads/{$lead->getKey()}/convert")
            ->assertForbidden();
    }
}
