<?php

declare(strict_types=1);

namespace Tests\Feature\RidesMarket;

use App\Domains\Authorization\Database\Seeders\PermissionCatalogueSeeder;
use App\Domains\Rides\Models\Ride;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Safe Rides — Marketplace (name-your-price) & Sentrix Send (parcel delivery).
 * User-scoped (ADR-0001); plain users, no org. ALL MONEY IS INTEGER CENTS.
 */
final class MarketplaceSendTest extends TestCase
{
    use RefreshDatabase;

    private const ORIGIN = ['origin_lat' => 6.5244, 'origin_lng' => 3.3792];
    private const DEST = ['dest_lat' => 6.6018, 'dest_lng' => 3.3515];
    private const SEND_LEG = [
        'pickup_lat' => 6.5244, 'pickup_lng' => 3.3792,
        'dropoff_lat' => 6.6018, 'dropoff_lng' => 3.3515,
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionCatalogueSeeder::class);
    }

    public function test_creating_a_fair_offer_returns_offer_with_seeded_bids_and_pricing_flag(): void
    {
        $rider = User::factory()->create();

        $response = $this->actingAs($rider, 'sanctum')
            ->postJson('/api/v1/me/rides/market/offers', array_merge(
                self::ORIGIN,
                self::DEST,
                ['proposed_fare_cents' => 250000],
            ))
            ->assertCreated()
            ->assertJsonPath('data.status', 'open')
            ->assertJsonPath('data.proposed_fare_cents', 250000);

        // pricing_flag is one of the enum values, and the fair estimate is a whole
        // integer (cents) — no .0 float in the JSON.
        $flag = $response->json('data.pricing_flag');
        $this->assertContains($flag, ['low', 'fair', 'high']);
        $this->assertIsInt($response->json('data.fair_estimate_cents'));
        $this->assertIsInt($response->json('data.proposed_fare_cents'));

        // 2-3 simulated driver bids seeded, all pending, integer amounts.
        $bids = $response->json('data.bids');
        $this->assertGreaterThanOrEqual(2, count($bids));
        $this->assertLessThanOrEqual(3, count($bids));
        foreach ($bids as $bid) {
            $this->assertSame('pending', $bid['status']);
            $this->assertIsInt($bid['amount_cents']);
            $this->assertContains($bid['kind'], ['accept', 'counter']);
        }
    }

    public function test_offer_below_sixty_percent_of_fair_is_rejected(): void
    {
        $rider = User::factory()->create();

        // 1 cent is unambiguously below 0.6x of any fair estimate.
        $this->actingAs($rider, 'sanctum')
            ->postJson('/api/v1/me/rides/market/offers', array_merge(
                self::ORIGIN,
                self::DEST,
                ['proposed_fare_cents' => 1],
            ))
            ->assertStatus(422)
            ->assertJsonPath('errors.proposed_fare_cents.0', 'offer_too_low');
    }

    public function test_open_board_lists_open_offers(): void
    {
        $rider = User::factory()->create();

        $this->actingAs($rider, 'sanctum')
            ->postJson('/api/v1/me/rides/market/offers', array_merge(
                self::ORIGIN,
                self::DEST,
                ['proposed_fare_cents' => 250000],
            ))
            ->assertCreated();

        // Another user can view the driver-facing open board.
        $driver = User::factory()->create();
        $this->actingAs($driver, 'sanctum')
            ->getJson('/api/v1/me/rides/market/offers/open')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.status', 'open')
            ->assertJsonPath('data.0.proposed_fare_cents', 250000);
    }

    public function test_rider_accepts_a_bid_materialises_a_ride(): void
    {
        $rider = User::factory()->create();

        $offer = $this->actingAs($rider, 'sanctum')
            ->postJson('/api/v1/me/rides/market/offers', array_merge(
                self::ORIGIN,
                self::DEST,
                ['proposed_fare_cents' => 250000],
            ))
            ->assertCreated()
            ->json('data');

        $bid = $offer['bids'][0];

        $response = $this->actingAs($rider, 'sanctum')
            ->postJson("/api/v1/me/rides/market/offers/{$offer['id']}/accept", ['bid_id' => $bid['id']])
            ->assertOk()
            ->assertJsonPath('data.offer.status', 'matched');

        $rideId = $response->json('data.ride_id');
        $matchedRideId = $response->json('data.offer.matched_ride_id');
        $this->assertIsString($rideId);
        $this->assertSame($rideId, $matchedRideId);

        // A Ride row now exists for the rider at the agreed (bid) fare.
        $ride = Ride::query()->findOrFail($rideId);
        $this->assertSame($rider->getKey(), $ride->user_id);
        $this->assertSame('matched', $ride->status->value);
        $this->assertSame($bid['amount_cents'], $ride->fare_estimate_cents);

        // It is visible via the rider's own rides list.
        $this->actingAs($rider, 'sanctum')
            ->getJson('/api/v1/me/rides')
            ->assertOk()
            ->assertJsonPath('data.0.id', $rideId);
    }

    public function test_another_user_cannot_accept_your_offer(): void
    {
        $rider = User::factory()->create();

        $offer = $this->actingAs($rider, 'sanctum')
            ->postJson('/api/v1/me/rides/market/offers', array_merge(
                self::ORIGIN,
                self::DEST,
                ['proposed_fare_cents' => 250000],
            ))
            ->assertCreated()
            ->json('data');

        $intruder = User::factory()->create();

        $this->actingAs($intruder, 'sanctum')
            ->postJson("/api/v1/me/rides/market/offers/{$offer['id']}/accept", ['bid_id' => $offer['bids'][0]['id']])
            ->assertNotFound();
    }

    public function test_send_quote_scales_fare_by_parcel_size(): void
    {
        $sender = User::factory()->create();

        $small = $this->actingAs($sender, 'sanctum')
            ->postJson('/api/v1/me/rides/send/quote', array_merge(self::SEND_LEG, ['parcel_size' => 'small']))
            ->assertOk()
            ->assertJsonPath('data.parcel_size', 'small')
            ->json('data.fare_cents');

        $large = $this->actingAs($sender, 'sanctum')
            ->postJson('/api/v1/me/rides/send/quote', array_merge(self::SEND_LEG, ['parcel_size' => 'large']))
            ->assertOk()
            ->json('data.fare_cents');

        // small (0.85x) < large (1.4x), and both are integer cents.
        $this->assertIsInt($small);
        $this->assertIsInt($large);
        $this->assertLessThan($large, $small);
    }

    public function test_send_book_creates_a_delivery_with_match_code_and_preserves_cod(): void
    {
        $sender = User::factory()->create();

        $matchCode = $this->actingAs($sender, 'sanctum')
            ->postJson('/api/v1/me/rides/send/book', array_merge(self::SEND_LEG, [
                'parcel_size' => 'medium',
                'payment_method' => 'cod',
                'cod_amount_cents' => 500000,
                'recipient_name' => 'Ada O.',
                'recipient_phone' => '+2348000000000',
            ]))
            ->assertCreated()
            ->assertJsonPath('data.status', 'matched')
            ->assertJsonPath('data.parcel_size', 'medium')
            ->assertJsonPath('data.payment_method', 'cod')
            ->assertJsonPath('data.cod_amount_cents', 500000)
            ->json('data.match_code');

        $this->assertIsString($matchCode);
        $this->assertSame(4, strlen($matchCode));
    }
}
