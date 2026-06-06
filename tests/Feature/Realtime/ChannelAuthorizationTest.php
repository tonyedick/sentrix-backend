<?php

declare(strict_types=1);

namespace Tests\Feature\Realtime;

use App\Domains\Authorization\Database\Seeders\PermissionCatalogueSeeder;
use App\Domains\Authorization\Support\Enums\OrganizationRole;
use App\Domains\Organization\Models\Organization;
use App\Domains\Organization\Services\MembershipService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Broadcast;
use ReflectionClass;
use Tests\TestCase;

/**
 * Exercises the private-channel authorization callbacks defined in
 * routes/channels.php directly. We invoke the registered closures rather than
 * hitting /broadcasting/auth, because channel enforcement at that endpoint
 * depends on the broadcaster driver (null in the test env), which would not be a
 * faithful test of the authorization rules.
 */
final class ChannelAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionCatalogueSeeder::class);
    }

    /**
     * The channel authorization closures registered from routes/channels.php,
     * keyed by their channel pattern.
     *
     * @return array<string, callable>
     */
    private function registeredChannels(): array
    {
        $broadcaster = Broadcast::driver();
        $property = (new ReflectionClass($broadcaster))->getProperty('channels');
        $property->setAccessible(true);

        /** @var array<string, callable> $channels */
        $channels = $property->getValue($broadcaster);

        return $channels;
    }

    public function test_organization_channel_authorizes_members_only(): void
    {
        $owner = User::factory()->create();
        $organizationId = $this->actingAs($owner, 'sanctum')
            ->postJson('/api/v1/organizations', ['name' => 'Acme Inc'])
            ->json('data.id');

        $member = User::factory()->create();
        app(MembershipService::class)->addMember(
            Organization::find($organizationId),
            $member,
            OrganizationRole::User->value,
        );
        $outsider = User::factory()->create();

        $channel = $this->registeredChannels()['organizations.{organizationId}'];

        $this->assertTrue((bool) $channel($owner->fresh(), $organizationId));
        $this->assertTrue((bool) $channel($member->fresh(), $organizationId));
        $this->assertFalse((bool) $channel($outsider->fresh(), $organizationId));
    }

    public function test_user_channel_authorizes_only_the_owning_user(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $channel = $this->registeredChannels()['App.Models.User.{id}'];

        $this->assertTrue((bool) $channel($user, (string) $user->id));
        $this->assertFalse((bool) $channel($other, (string) $user->id));
    }
}
