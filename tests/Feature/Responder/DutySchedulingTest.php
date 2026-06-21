<?php

declare(strict_types=1);

namespace Tests\Feature\Responder;

use App\Domains\Authorization\Database\Seeders\PermissionCatalogueSeeder;
use App\Domains\Authorization\Support\Enums\OrganizationRole;
use App\Domains\Organization\Models\Organization;
use App\Domains\Organization\Services\MembershipService;
use App\Domains\Responder\Models\DutyShift;
use App\Domains\Responder\Models\Responder;
use App\Domains\Responder\Services\DutyService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

final class DutySchedulingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionCatalogueSeeder::class);
    }

    public function test_due_shift_puts_responder_on_duty_then_closes_off_duty(): void
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

        $shiftId = $this->actingAs($owner, 'sanctum')
            ->postJson("/api/v1/organizations/{$organizationId}/responders/{$responderId}/shifts", [
                'starts_at' => Carbon::now()->subMinute()->toIso8601String(),
                'ends_at' => Carbon::now()->addHour()->toIso8601String(),
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'scheduled')
            ->json('data.id');

        // Sweep activates the open shift.
        $result = app(DutyService::class)->processDueShifts();
        $this->assertSame(1, $result['activated']);

        $responder = Responder::findOrFail($responderId);
        $this->assertSame('available', $responder->status->value);
        $this->assertTrue($responder->on_duty);
        $this->assertDatabaseHas('duty_shifts', ['id' => $shiftId, 'status' => 'active']);

        // End the window; the next sweep closes the shift and stands the responder down.
        DutyShift::query()->whereKey($shiftId)->update(['ends_at' => Carbon::now()->subSecond()]);
        $result = app(DutyService::class)->processDueShifts();
        $this->assertSame(1, $result['closed']);

        $responder->refresh();
        $this->assertSame('off_duty', $responder->status->value);
        $this->assertFalse($responder->on_duty);
    }

    public function test_scheduling_requires_schedules_manage_permission(): void
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

        // A field user lacks schedules.manage.
        $fieldUser = User::factory()->create();
        app(MembershipService::class)->addMember($organization, $fieldUser, OrganizationRole::User->value);

        $this->actingAs($fieldUser, 'sanctum')
            ->postJson("/api/v1/organizations/{$organizationId}/responders/{$responderId}/shifts", [
                'starts_at' => Carbon::now()->addHour()->toIso8601String(),
                'ends_at' => Carbon::now()->addHours(2)->toIso8601String(),
            ])
            ->assertForbidden();
    }
}
