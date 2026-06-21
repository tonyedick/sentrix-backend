<?php

declare(strict_types=1);

namespace Tests\Feature\Responder;

use App\Domains\Authorization\Database\Seeders\PermissionCatalogueSeeder;
use App\Domains\Authorization\Support\Enums\OrganizationRole;
use App\Domains\Organization\Models\Organization;
use App\Domains\Organization\Services\MembershipService;
use App\Domains\Responder\Models\ResponderCertification;
use App\Domains\Responder\Services\ResponderCapabilityService;
use App\Domains\Responder\Support\Enums\CertificationStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

final class ResponderCapabilityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionCatalogueSeeder::class);
    }

    /**
     * @return array{0: User, 1: string, 2: Organization, 3: string}
     */
    private function ownerOrgAndResponder(): array
    {
        $owner = User::factory()->create();
        $organizationId = $this->actingAs($owner, 'sanctum')
            ->postJson('/api/v1/organizations', ['name' => 'Acme Inc'])
            ->json('data.id');
        $organization = Organization::findOrFail($organizationId);

        $bob = User::factory()->create();
        app(MembershipService::class)->addMember($organization, $bob, OrganizationRole::Responder->value);

        $responderId = $this->actingAs($owner, 'sanctum')
            ->postJson("/api/v1/organizations/{$organizationId}/responders", ['user_id' => $bob->getKey()])
            ->json('data.id');

        return [$owner, $organizationId, $organization, $responderId];
    }

    public function test_catalogue_skill_can_be_created_and_attached(): void
    {
        [$owner, $organizationId, , $responderId] = $this->ownerOrgAndResponder();

        $skillId = $this->actingAs($owner, 'sanctum')
            ->postJson("/api/v1/organizations/{$organizationId}/skills", ['code' => 'medic', 'name' => 'Medic'])
            ->assertCreated()
            ->json('data.id');

        $this->actingAs($owner, 'sanctum')
            ->postJson("/api/v1/organizations/{$organizationId}/responders/{$responderId}/skills", [
                'skill_id' => $skillId,
                'proficiency' => 'expert',
            ])
            ->assertOk();

        $this->assertDatabaseHas('responder_skill', [
            'responder_id' => $responderId,
            'skill_id' => $skillId,
            'proficiency' => 'expert',
        ]);
    }

    public function test_certification_can_be_added_and_verified(): void
    {
        [$owner, $organizationId, , $responderId] = $this->ownerOrgAndResponder();

        $certId = $this->actingAs($owner, 'sanctum')
            ->postJson("/api/v1/organizations/{$organizationId}/responders/{$responderId}/certifications", [
                'name' => 'EMT-Basic',
                'authority' => 'State Board',
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'pending')
            ->json('data.id');

        $this->actingAs($owner, 'sanctum')
            ->postJson("/api/v1/organizations/{$organizationId}/responders/{$responderId}/certifications/{$certId}/verify")
            ->assertOk()
            ->assertJsonPath('data.status', 'verified');
    }

    public function test_expiry_sweep_lapses_past_certifications(): void
    {
        [, , , $responderId] = $this->ownerOrgAndResponder();

        $expired = ResponderCertification::create([
            'responder_id' => $responderId,
            'organization_id' => Organization::query()->value('id'),
            'name' => 'Lapsed cert',
            'status' => CertificationStatus::Verified->value,
            'expires_at' => Carbon::now()->subDay(),
        ]);

        $result = app(ResponderCapabilityService::class)->sweepExpiry();

        $this->assertSame(1, $result['expired']);
        $this->assertDatabaseHas('responder_certifications', [
            'id' => $expired->getKey(),
            'status' => 'expired',
        ]);
    }
}
