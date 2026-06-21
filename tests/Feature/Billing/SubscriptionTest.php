<?php

declare(strict_types=1);

namespace Tests\Feature\Billing;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class SubscriptionTest extends TestCase
{
    use RefreshDatabase;

    public function test_plan_catalogue_is_listed(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/plans')
            ->assertOk()
            ->assertJsonCount(3, 'data');
    }

    public function test_new_user_defaults_to_free_plan(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/me/subscription')
            ->assertOk()
            ->assertJsonPath('data.plan_key', 'free');
    }

    public function test_subscribe_to_premium_grants_entitlements_and_invoice(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/me/subscription', ['plan' => 'premium_monthly'])
            ->assertOk()
            ->assertJsonPath('data.plan_key', 'premium_monthly')
            ->assertJsonPath('data.status', 'active')
            ->assertJsonFragment(['smart_routing']);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/me/billing/invoices')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_auto_renew_toggle_and_cancel(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user, 'sanctum')->postJson('/api/v1/me/subscription', ['plan' => 'premium_annual'])->assertOk();

        $this->actingAs($user, 'sanctum')
            ->patchJson('/api/v1/me/subscription/auto-renew', ['auto_renew' => false])
            ->assertOk()
            ->assertJsonPath('data.auto_renew', false);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/me/subscription/cancel')
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled');
    }

    public function test_invalid_plan_is_rejected(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/me/subscription', ['plan' => 'nope'])
            ->assertStatus(422);
    }
}
