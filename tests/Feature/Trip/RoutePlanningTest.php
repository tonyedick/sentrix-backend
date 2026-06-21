<?php

declare(strict_types=1);

namespace Tests\Feature\Trip;

use App\Domains\Community\Models\CommunityAlert;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Route planning: returns safest + fastest with distance/ETA, and corridor risk
 * derived from active community alerts near the destination. Geo requires PostGIS.
 */
final class RoutePlanningTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_safest_and_fastest_plans(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/me/routes/plan', [
                'origin_lat' => 6.4281, 'origin_lng' => 3.4219,
                'destination_lat' => 6.5244, 'destination_lng' => 3.3792,
            ])
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.profile', 'safest')
            ->assertJsonPath('data.1.profile', 'fastest');
    }

    public function test_nearby_alerts_lower_the_fastest_safety_score(): void
    {
        $reporter = User::factory()->create();
        // Three active alerts clustered at the destination.
        foreach (range(1, 3) as $i) {
            CommunityAlert::create([
                'reporter_id' => $reporter->getKey(),
                'category' => 'traffic',
                'title' => "Alert {$i}",
                'impact' => 'high',
                'status' => 'active',
                'lat' => 6.5244,
                'lng' => 3.3792,
                'expires_at' => now()->addHour(),
            ]);
        }

        $user = User::factory()->create();
        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/me/routes/plan', [
                'origin_lat' => 6.4281, 'origin_lng' => 3.4219,
                'destination_lat' => 6.5244, 'destination_lng' => 3.3792,
            ])
            ->assertOk();

        $fastest = collect($response->json('data'))->firstWhere('profile', 'fastest');
        $safest = collect($response->json('data'))->firstWhere('profile', 'safest');

        $this->assertSame(3, $fastest['alerts_count']);
        $this->assertLessThan(100, $fastest['safety_score']);
        $this->assertGreaterThan($fastest['safety_score'], $safest['safety_score']);
        $this->assertTrue($safest['recommended']);
    }
}
