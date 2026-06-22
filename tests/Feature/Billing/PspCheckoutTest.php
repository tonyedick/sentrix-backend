<?php

declare(strict_types=1);

namespace Tests\Feature\Billing;

use App\Domains\Billing\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class PspCheckoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_catalog_returns_region_currency_and_priced_plans(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/billing/catalog?region=NG')
            ->assertOk()
            ->assertJsonPath('data.region', 'NG')
            ->assertJsonPath('data.currency', 'NGN');

        // Three plans, priced for the region, amounts as integers.
        $plans = $response->json('data.plans');
        $this->assertCount(3, $plans);
        foreach ($plans as $plan) {
            $this->assertIsInt($plan['amount_cents']);
            $this->assertIsInt($plan['subtotal_cents']);
            $this->assertIsInt($plan['tax_cents']);
            $this->assertSame('NGN', $plan['currency']);
        }

        // A different region quotes a different currency for the same plans.
        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/billing/catalog?region=KE')
            ->assertOk()
            ->assertJsonPath('data.currency', 'KES');
    }

    public function test_checkout_creates_pending_payment_with_reference_and_url(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/me/billing/checkout', ['plan_key' => 'premium_monthly', 'region' => 'NG'])
            ->assertCreated()
            ->assertJsonStructure(['data' => ['reference', 'checkout_url', 'amount_cents', 'currency']]);

        $reference = $response->json('data.reference');
        $this->assertIsString($reference);
        $this->assertIsInt($response->json('data.amount_cents'));

        $this->assertDatabaseHas('payments', [
            'reference' => $reference,
            'user_id' => $user->getKey(),
            'status' => 'pending',
            'plan_key' => 'premium_monthly',
        ]);
    }

    public function test_show_checkout_returns_pending_and_is_scoped_to_owner(): void
    {
        $owner = User::factory()->create();
        $reference = $this->actingAs($owner, 'sanctum')
            ->postJson('/api/v1/me/billing/checkout', ['plan_key' => 'premium_monthly'])
            ->json('data.reference');

        $this->actingAs($owner, 'sanctum')
            ->getJson("/api/v1/me/billing/checkout/{$reference}")
            ->assertOk()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.reference', $reference);

        // Another user cannot see it -> 404 (lookup scoped to the caller).
        $other = User::factory()->create();
        $this->actingAs($other, 'sanctum')
            ->getJson("/api/v1/me/billing/checkout/{$reference}")
            ->assertNotFound();
    }

    public function test_simulate_marks_paid_and_activates_subscription(): void
    {
        $user = User::factory()->create();
        $reference = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/me/billing/checkout', ['plan_key' => 'premium_monthly'])
            ->json('data.reference');

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/me/billing/checkout/{$reference}/simulate")
            ->assertOk()
            ->assertJsonPath('data.plan_key', 'premium_monthly')
            ->assertJsonPath('data.status', 'active');

        $this->assertDatabaseHas('payments', ['reference' => $reference, 'status' => 'paid']);
        $this->assertDatabaseHas('subscriptions', ['user_id' => $user->getKey(), 'plan_key' => 'premium_monthly', 'status' => 'active']);

        $payment = Payment::query()->where('reference', $reference)->firstOrFail();
        $this->assertNotNull($payment->paid_at);

        // An invoice was recorded for the charge.
        $this->assertDatabaseHas('invoices', ['user_id' => $user->getKey(), 'plan_key' => 'premium_monthly', 'status' => 'paid']);
    }

    public function test_simulating_twice_does_not_double_extend(): void
    {
        $user = User::factory()->create();
        $reference = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/me/billing/checkout', ['plan_key' => 'premium_monthly'])
            ->json('data.reference');

        $first = $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/me/billing/checkout/{$reference}/simulate")
            ->assertOk()
            ->json('data.current_period_end');

        $second = $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/me/billing/checkout/{$reference}/simulate")
            ->assertOk()
            ->json('data.current_period_end');

        // Idempotent: re-confirming the same reference does not move the period.
        $this->assertSame($first, $second);

        // And only ONE invoice exists for the single paid charge.
        $this->assertSame(1, \App\Domains\Billing\Models\Invoice::query()->where('user_id', $user->getKey())->count());
    }

    public function test_webhook_with_valid_signature_marks_paid_and_activates(): void
    {
        config()->set('sentrix.billing.webhook_secret', 'whsec_test_123');

        $user = User::factory()->create();
        $reference = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/me/billing/checkout', ['plan_key' => 'premium_annual'])
            ->json('data.reference');

        // The webhook HMACs the RAW request body, so send the exact payload bytes
        // we signed via call() (postJson would re-encode and break the digest).
        $payload = json_encode(['event' => 'charge.success', 'data' => ['reference' => $reference]], JSON_THROW_ON_ERROR);
        $signature = hash_hmac('sha256', $payload, 'whsec_test_123');

        $this->call('POST', '/api/v1/billing/webhook', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_SENTRIX_SIGNATURE' => $signature,
        ], $payload)->assertOk();

        $this->assertDatabaseHas('payments', ['reference' => $reference, 'status' => 'paid']);
        $this->assertDatabaseHas('subscriptions', ['user_id' => $user->getKey(), 'plan_key' => 'premium_annual', 'status' => 'active']);
    }

    public function test_webhook_with_invalid_signature_is_rejected(): void
    {
        config()->set('sentrix.billing.webhook_secret', 'whsec_test_123');

        $user = User::factory()->create();
        $reference = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/me/billing/checkout', ['plan_key' => 'premium_monthly'])
            ->json('data.reference');

        $payload = json_encode(['event' => 'charge.success', 'data' => ['reference' => $reference]], JSON_THROW_ON_ERROR);

        $this->call('POST', '/api/v1/billing/webhook', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_SENTRIX_SIGNATURE' => 'definitely-wrong',
        ], $payload)->assertStatus(400);

        $this->assertDatabaseHas('payments', ['reference' => $reference, 'status' => 'pending']);
    }

    public function test_webhook_acks_unknown_event_without_changing_payment(): void
    {
        config()->set('sentrix.billing.webhook_secret', 'whsec_test_123');

        $user = User::factory()->create();
        $reference = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/me/billing/checkout', ['plan_key' => 'premium_monthly'])
            ->json('data.reference');

        $payload = json_encode(['event' => 'charge.pending', 'data' => ['reference' => $reference]], JSON_THROW_ON_ERROR);
        $signature = hash_hmac('sha256', $payload, 'whsec_test_123');

        $this->call('POST', '/api/v1/billing/webhook', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_SENTRIX_SIGNATURE' => $signature,
        ], $payload)->assertOk();

        $this->assertDatabaseHas('payments', ['reference' => $reference, 'status' => 'pending']);
    }
}
