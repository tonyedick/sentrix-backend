<?php

declare(strict_types=1);

namespace Tests\Feature\Places;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Server-side geocoding proxies. With NO Google key configured (the default in
 * tests), every endpoint serves the deterministic curated fallback — no real
 * external HTTP is made. One Http::fake() test covers the keyed proxy path.
 */
final class GeocodingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Ensure the no-key fallback path is exercised by default.
        config()->set('sentrix.places.google_api_key', null);
    }

    public function test_autocomplete_returns_curated_suggestions_for_a_query(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/places/autocomplete?q=lekki')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.description', 'Lekki Phase 1, Admiralty Way, Lekki')
            ->assertJsonPath('data.0.place_id', null);
    }

    public function test_autocomplete_requires_a_query(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/places/autocomplete')
            ->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_geocode_resolves_a_known_place_deterministically(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/places/geocode?address=Victoria Island')
            ->assertOk()
            ->assertJsonPath('data.lat', 6.4281)
            ->assertJsonPath('data.lng', 3.4219)
            ->assertJsonPath('data.formatted_address', 'Victoria Island, Adeola Odeku, VI');
    }

    public function test_geocode_returns_empty_for_an_unknown_address(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/places/geocode?address=Nowhere-on-the-Map-ZZZ')
            ->assertOk()
            ->assertExactJson(['success' => true, 'message' => 'Success', 'data' => []]);
    }

    public function test_nearby_returns_category_filtered_curated_pois_nearest_first(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/places/nearby?category=pharmacy&lat=6.4291&lng=3.4221')
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('data.0.category', 'pharmacy');

        // Distances are whole-number metres (ints, no trailing .0) and ascending.
        $distances = array_column($response->json('data'), 'distance_m');
        foreach ($distances as $distance) {
            $this->assertIsInt($distance);
        }
        $sorted = $distances;
        sort($sorted);
        $this->assertSame($sorted, $distances);

        // The nearest curated pharmacy to its own coordinates is itself.
        $response->assertJsonPath('data.0.name', 'HealthPlus Pharmacy');
    }

    public function test_nearby_rejects_an_unknown_category(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/places/nearby?category=nightclub')
            ->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_keyed_autocomplete_proxies_google_when_a_key_is_set(): void
    {
        config()->set('sentrix.places.google_api_key', 'test-key');

        Http::fake([
            'maps.googleapis.com/maps/api/place/autocomplete/json*' => Http::response([
                'status' => 'OK',
                'predictions' => [
                    ['description' => 'Paris, France', 'place_id' => 'paris-123'],
                ],
            ]),
        ]);

        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/places/autocomplete?q=paris')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.description', 'Paris, France')
            ->assertJsonPath('data.0.place_id', 'paris-123');

        Http::assertSentCount(1);
    }
}
