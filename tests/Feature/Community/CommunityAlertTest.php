<?php

declare(strict_types=1);

namespace Tests\Feature\Community;

use App\Domains\Community\Models\CommunityAlert;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Community alerts: report, geo nearby feed, and crowd verification.
 * Geo queries require PostGIS (the project's test DB).
 */
final class CommunityAlertTest extends TestCase
{
    use RefreshDatabase;

    private function report(User $user, array $overrides = []): string
    {
        return $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/me/alerts', array_merge([
                'category' => 'traffic',
                'title' => 'Heavy traffic on Allen Avenue',
                'lat' => 6.5244,
                'lng' => 3.3792,
            ], $overrides))
            ->assertCreated()
            ->json('data.id');
    }

    public function test_user_can_report_an_alert(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/me/alerts', [
                'category' => 'security',
                'title' => 'Suspicious activity',
                'impact' => 'high',
                'lat' => 6.5,
                'lng' => 3.4,
            ])
            ->assertCreated()
            ->assertJsonPath('data.category', 'security')
            ->assertJsonPath('data.impact', 'high')
            ->assertJsonPath('data.mine', true);
    }

    public function test_nearby_feed_returns_only_alerts_in_radius(): void
    {
        $reporter = User::factory()->create();
        $this->report($reporter, ['lat' => 6.5244, 'lng' => 3.3792]);          // near
        $this->report($reporter, ['lat' => 7.5000, 'lng' => 4.5000]);          // far (>100km)

        $viewer = User::factory()->create();
        $this->actingAs($viewer, 'sanctum')
            ->getJson('/api/v1/me/alerts?lat=6.5244&lng=3.3792&radius=3000')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_confirmation_is_one_vote_per_user(): void
    {
        $reporter = User::factory()->create();
        $alertId = $this->report($reporter);

        $voter = User::factory()->create();
        $this->actingAs($voter, 'sanctum')->postJson("/api/v1/me/alerts/{$alertId}/confirm", ['still_active' => true])->assertOk();
        $this->actingAs($voter, 'sanctum')->postJson("/api/v1/me/alerts/{$alertId}/confirm", ['still_active' => true])->assertOk();

        $this->assertSame(1, CommunityAlert::findOrFail($alertId)->confirmations_count);
    }

    public function test_alert_resolves_after_dismiss_threshold(): void
    {
        $reporter = User::factory()->create();
        $alertId = $this->report($reporter);

        foreach (User::factory()->count(3)->create() as $voter) {
            $this->actingAs($voter, 'sanctum')
                ->postJson("/api/v1/me/alerts/{$alertId}/dismiss")
                ->assertOk();
        }

        $this->assertSame('resolved', CommunityAlert::findOrFail($alertId)->status->value);
    }
}
