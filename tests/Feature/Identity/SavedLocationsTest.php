<?php

declare(strict_types=1);

namespace Tests\Feature\Identity;

use App\Domains\Authorization\Database\Seeders\PermissionCatalogueSeeder;
use App\Domains\Identity\Models\RecentSearch;
use App\Domains\Identity\Models\SavedLocation;
use App\Domains\Organization\Database\Seeders\MonitoringOrganizationSeeder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class SavedLocationsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionCatalogueSeeder::class);
        $this->seed(MonitoringOrganizationSeeder::class);
    }

    public function test_user_can_create_list_update_and_delete_saved_locations(): void
    {
        $user = User::factory()->create();

        $id = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/me/saved-locations', [
                'label' => 'Home',
                'kind' => 'home',
                'address' => '12 Marina, Lagos',
                'lat' => 6.4541,
                'lng' => 3.3947,
            ])
            ->assertCreated()
            ->assertJsonPath('data.label', 'Home')
            ->assertJsonPath('data.kind', 'home')
            ->assertJsonPath('data.location.lat', 6.4541)
            ->json('data.id');

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/me/saved-locations')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->actingAs($user, 'sanctum')
            ->patchJson("/api/v1/me/saved-locations/{$id}", [
                'label' => 'Office',
                'kind' => 'work',
                'lat' => 6.43,
                'lng' => 3.42,
            ])
            ->assertOk()
            ->assertJsonPath('data.label', 'Office')
            ->assertJsonPath('data.kind', 'work');

        $this->actingAs($user, 'sanctum')
            ->deleteJson("/api/v1/me/saved-locations/{$id}")
            ->assertNoContent();

        $this->assertSame(0, SavedLocation::query()->where('user_id', $user->getKey())->count());
    }

    public function test_validation_rejects_out_of_range_coordinates(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/me/saved-locations', [
                'label' => 'Bad',
                'lat' => 200,
                'lng' => 3.39,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('lat');
    }

    public function test_user_cannot_touch_another_users_saved_location(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();

        $location = SavedLocation::create([
            'user_id' => $owner->getKey(),
            'label' => 'Home',
            'kind' => 'home',
            'lat' => 6.45,
            'lng' => 3.39,
        ]);

        $this->actingAs($intruder, 'sanctum')
            ->patchJson("/api/v1/me/saved-locations/{$location->getKey()}", [
                'label' => 'Hijacked',
                'lat' => 6.45,
                'lng' => 3.39,
            ])
            ->assertNotFound();

        $this->actingAs($intruder, 'sanctum')
            ->deleteJson("/api/v1/me/saved-locations/{$location->getKey()}")
            ->assertNotFound();
    }

    public function test_recent_searches_are_recorded_deduped_and_capped(): void
    {
        $user = User::factory()->create();

        // Repeat the same label twice — should collapse to one, moved to the top.
        foreach (['Yaba', 'Yaba'] as $label) {
            $this->actingAs($user, 'sanctum')
                ->postJson('/api/v1/me/recent-searches', ['label' => $label, 'lat' => 6.5, 'lng' => 3.37])
                ->assertCreated();
        }

        $this->assertSame(1, RecentSearch::query()->where('user_id', $user->getKey())->count());

        // Exceed the cap (10) — only the most recent 10 survive.
        for ($i = 1; $i <= 12; $i++) {
            $this->actingAs($user, 'sanctum')
                ->postJson('/api/v1/me/recent-searches', ['label' => "Place {$i}"])
                ->assertCreated();
        }

        $this->assertSame(10, RecentSearch::query()->where('user_id', $user->getKey())->count());

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/me/recent-searches')
            ->assertOk()
            ->assertJsonCount(10, 'data')
            ->assertJsonPath('data.0.label', 'Place 12'); // newest first

        $this->actingAs($user, 'sanctum')
            ->deleteJson('/api/v1/me/recent-searches')
            ->assertNoContent();

        $this->assertSame(0, RecentSearch::query()->where('user_id', $user->getKey())->count());
    }
}
