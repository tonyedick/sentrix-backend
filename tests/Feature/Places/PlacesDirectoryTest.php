<?php

declare(strict_types=1);

namespace Tests\Feature\Places;

use App\Domains\Places\Models\Place;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Places directory: geo nearby search, category + open-now filters.
 * Geo queries require PostGIS (the project's test DB).
 */
final class PlacesDirectoryTest extends TestCase
{
    use RefreshDatabase;

    private function seedPlaces(): void
    {
        Place::create(['name' => 'VI Police', 'category' => 'police', 'lat' => 6.4281, 'lng' => 3.4219, 'rating' => 4.6, 'reviews_count' => 128, 'is_24_7' => true]);
        Place::create(['name' => 'VI Hospital', 'category' => 'hospital', 'lat' => 6.4300, 'lng' => 3.4230, 'rating' => 4.7, 'reviews_count' => 312, 'is_24_7' => true]);
        Place::create(['name' => 'Far Station', 'category' => 'police', 'lat' => 7.5000, 'lng' => 4.5000, 'is_24_7' => true]); // far
    }

    public function test_nearby_returns_places_in_radius_nearest_first(): void
    {
        $this->seedPlaces();
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/places?lat=6.4281&lng=3.4219&radius=5000')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.name', 'VI Police'); // nearest
    }

    public function test_category_filter(): void
    {
        $this->seedPlaces();
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/places?lat=6.4281&lng=3.4219&radius=5000&category=hospital')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.category', 'hospital');
    }

    public function test_show_returns_place(): void
    {
        $this->seedPlaces();
        $user = User::factory()->create();
        $place = Place::query()->where('name', 'VI Police')->firstOrFail();

        $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/places/{$place->getKey()}")
            ->assertOk()
            ->assertJsonPath('data.open_24_7', true)
            ->assertJsonPath('data.hours', 'Open 24/7');
    }
}
