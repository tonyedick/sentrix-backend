<?php

declare(strict_types=1);

namespace Tests\Feature\Assignment;

use App\Domains\Assignment\Events\ResponderAssignmentRecommended;
use App\Domains\Assignment\Jobs\RecommendResponderAssignment;
use App\Domains\Assignment\Services\DispatchRecommender;
use App\Domains\Authorization\Database\Seeders\PermissionCatalogueSeeder;
use App\Domains\Authorization\Support\Enums\OrganizationRole;
use App\Domains\Incident\Models\Incident;
use App\Domains\Organization\Models\Organization;
use App\Domains\Organization\Services\MembershipService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

final class AiDispatchRecommendationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionCatalogueSeeder::class);
    }

    /**
     * @return array{owner: User, orgId: string, responderId: string}
     */
    private function orgWithAvailableResponder(): array
    {
        $owner = User::factory()->create();
        $orgId = $this->actingAs($owner, 'sanctum')
            ->postJson('/api/v1/organizations', ['name' => 'Acme Inc'])
            ->json('data.id');
        $organization = Organization::findOrFail($orgId);

        $bob = User::factory()->create();
        app(MembershipService::class)->addMember($organization, $bob, OrganizationRole::Responder->value);
        $responderId = $this->actingAs($owner, 'sanctum')
            ->postJson("/api/v1/organizations/{$orgId}/responders", ['user_id' => $bob->getKey()])
            ->json('data.id');
        $this->actingAs($bob, 'sanctum')
            ->postJson("/api/v1/organizations/{$orgId}/responders/{$responderId}/status", ['status' => 'available'])
            ->assertOk();

        return ['owner' => $owner, 'orgId' => $orgId, 'responderId' => $responderId];
    }

    public function test_no_recommendation_when_disabled(): void
    {
        config(['sentrix.responders.ai_dispatch_enabled' => false]);
        Queue::fake();
        ['owner' => $owner, 'orgId' => $orgId] = $this->orgWithAvailableResponder();

        $this->actingAs($owner, 'sanctum')
            ->postJson("/api/v1/organizations/{$orgId}/incidents", ['title' => 'No AI'])
            ->assertCreated();

        Queue::assertNotPushed(RecommendResponderAssignment::class);
    }

    public function test_recommendation_is_queued_when_enabled(): void
    {
        config(['sentrix.responders.ai_dispatch_enabled' => true]);
        Queue::fake();
        ['owner' => $owner, 'orgId' => $orgId] = $this->orgWithAvailableResponder();

        $this->actingAs($owner, 'sanctum')
            ->postJson("/api/v1/organizations/{$orgId}/incidents", ['title' => 'With AI'])
            ->assertCreated();

        Queue::assertPushed(RecommendResponderAssignment::class);
    }

    public function test_recommender_emits_shortlist_of_available_responders(): void
    {
        ['owner' => $owner, 'orgId' => $orgId, 'responderId' => $responderId] = $this->orgWithAvailableResponder();

        $incidentId = $this->actingAs($owner, 'sanctum')
            ->postJson("/api/v1/organizations/{$orgId}/incidents", ['title' => 'Rank me'])
            ->json('data.id');

        Event::fake([ResponderAssignmentRecommended::class]);

        (new RecommendResponderAssignment(Incident::class, $incidentId))
            ->handle(app(DispatchRecommender::class));

        Event::assertDispatched(
            ResponderAssignmentRecommended::class,
            static function (ResponderAssignmentRecommended $event) use ($responderId): bool {
                $candidates = $event->context['candidates'] ?? [];

                return collect($candidates)->contains(
                    static fn (array $candidate): bool => $candidate['responder_id'] === $responderId,
                );
            },
        );
    }
}
