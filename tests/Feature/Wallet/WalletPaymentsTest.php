<?php

declare(strict_types=1);

namespace Tests\Feature\Wallet;

use App\Domains\Authorization\Database\Seeders\PermissionCatalogueSeeder;
use App\Domains\Wallet\Services\WalletService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Safe Rides — Wallet & payments (user-scoped, ADR-0001). Plain users, no org.
 * ALL MONEY IS INTEGER CENTS — assertions check ints.
 */
final class WalletPaymentsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Harmless for a user-scoped slice, but keeps test parity with org domains.
        $this->seed(PermissionCatalogueSeeder::class);
    }

    public function test_wallet_starts_at_zero(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/me/rides/pay/wallet')
            ->assertOk()
            ->assertJsonPath('data.balance_cents', 0)
            ->assertJsonPath('data.lifetime_topup_cents', 0)
            ->assertJsonPath('data.currency', 'NGN');
    }

    public function test_payment_methods_seed_cash_and_wallet_and_a_card_can_be_added(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/me/rides/payment-methods')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.kind', 'cash');

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/me/rides/payment-methods', ['brand' => 'Visa', 'last4' => '4242'])
            ->assertCreated()
            ->assertJsonPath('data.kind', 'card')
            ->assertJsonPath('data.last4', '4242');

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/me/rides/payment-methods')
            ->assertOk()
            ->assertJsonCount(3, 'data');
    }

    public function test_topup_initiate_then_confirm_credits_the_balance(): void
    {
        $user = User::factory()->create();

        $reference = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/me/rides/pay/topup/initiate', ['amount_cents' => 500000, 'method' => 'transfer'])
            ->assertCreated()
            ->assertJsonPath('data.transaction.status', 'pending')
            ->json('data.transaction.reference');

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/me/rides/pay/topup/confirm', ['reference' => $reference])
            ->assertOk()
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonPath('data.balance_after_cents', 500000);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/me/rides/pay/wallet')
            ->assertOk()
            ->assertJsonPath('data.balance_cents', 500000)
            ->assertJsonPath('data.lifetime_topup_cents', 500000);
    }

    public function test_confirming_the_same_reference_twice_does_not_double_credit(): void
    {
        $user = User::factory()->create();

        $reference = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/me/rides/pay/topup/initiate', ['amount_cents' => 100000, 'method' => 'ussd'])
            ->assertCreated()
            ->json('data.transaction.reference');

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/me/rides/pay/topup/confirm', ['reference' => $reference])
            ->assertOk();

        // Second confirm: idempotent, balance stable.
        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/me/rides/pay/topup/confirm', ['reference' => $reference])
            ->assertOk()
            ->assertJsonPath('data.status', 'completed');

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/me/rides/pay/wallet')
            ->assertOk()
            ->assertJsonPath('data.balance_cents', 100000)
            ->assertJsonPath('data.lifetime_topup_cents', 100000);
    }

    public function test_charge_debits_the_wallet(): void
    {
        $user = User::factory()->create();
        app(WalletService::class)->confirmTopup(
            $user,
            app(WalletService::class)->initiateTopup($user, 300000, \App\Domains\Wallet\Support\Enums\TopupMethod::Transfer)['transaction']->reference,
        );

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/me/rides/pay/charge', ['amount_cents' => 120000, 'description' => 'Ride fare'])
            ->assertCreated()
            ->assertJsonPath('data.direction', 'debit')
            ->assertJsonPath('data.balance_after_cents', 180000);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/me/rides/pay/wallet')
            ->assertOk()
            ->assertJsonPath('data.balance_cents', 180000);
    }

    public function test_charge_over_balance_returns_402_with_shortfall(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/me/rides/pay/charge', ['amount_cents' => 250000])
            ->assertStatus(402)
            ->assertJsonPath('errors.shortfall_cents', 250000);
    }

    public function test_cash_method_cannot_be_deleted(): void
    {
        $user = User::factory()->create();

        $methods = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/me/rides/payment-methods')
            ->assertOk()
            ->json('data');

        $cash = collect($methods)->firstWhere('kind', 'cash');

        $this->actingAs($user, 'sanctum')
            ->deleteJson("/api/v1/me/rides/payment-methods/{$cash['id']}")
            ->assertStatus(422);
    }

    public function test_referral_me_returns_a_code(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/me/rides/referral/me')
            ->assertOk()
            ->assertJsonPath('data.has_claimed', false)
            ->assertJsonStructure(['data' => ['code', 'share_link', 'invited_count', 'total_earned_cents', 'has_claimed']]);
    }

    public function test_referral_claim_credits_both_users_once(): void
    {
        $referrer = User::factory()->create();
        $claimer = User::factory()->create();

        $code = $this->actingAs($referrer, 'sanctum')
            ->getJson('/api/v1/me/rides/referral/me')
            ->json('data.code');

        $reward = (int) config('sentrix.rides.referral_reward_cents', 100000);

        $this->actingAs($claimer, 'sanctum')
            ->postJson('/api/v1/me/rides/referral/claim', ['code' => $code])
            ->assertCreated()
            ->assertJsonPath('data.amount_cents', $reward);

        // Both wallets credited.
        $this->actingAs($claimer, 'sanctum')
            ->getJson('/api/v1/me/rides/pay/wallet')
            ->assertOk()
            ->assertJsonPath('data.balance_cents', $reward);

        $this->actingAs($referrer, 'sanctum')
            ->getJson('/api/v1/me/rides/pay/wallet')
            ->assertOk()
            ->assertJsonPath('data.balance_cents', $reward);

        // Double-claim by the same claimer -> 422.
        $this->actingAs($claimer, 'sanctum')
            ->postJson('/api/v1/me/rides/referral/claim', ['code' => $code])
            ->assertStatus(422);
    }

    public function test_self_claim_is_rejected(): void
    {
        $user = User::factory()->create();

        $code = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/me/rides/referral/me')
            ->json('data.code');

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/me/rides/referral/claim', ['code' => $code])
            ->assertStatus(422);
    }

    public function test_unknown_referral_code_returns_404(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/me/rides/referral/claim', ['code' => 'SR-ZZZZZZ'])
            ->assertStatus(404);
    }
}
