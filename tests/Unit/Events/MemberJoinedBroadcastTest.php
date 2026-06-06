<?php

declare(strict_types=1);

namespace Tests\Unit\Events;

use App\Domains\Organization\Events\MemberJoined;
use App\Domains\Organization\Models\Organization;
use App\Models\User;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Tests\TestCase;

final class MemberJoinedBroadcastTest extends TestCase
{
    public function test_it_broadcasts_to_the_private_organization_channel(): void
    {
        $organization = new Organization();
        $organization->id = '11111111-1111-1111-1111-111111111111';

        $user = new User();
        $user->id = '22222222-2222-2222-2222-222222222222';
        $user->name = 'Grace Hopper';

        $event = new MemberJoined($organization, $user, 'Responder');

        $this->assertInstanceOf(ShouldBroadcast::class, $event);

        $channels = $event->broadcastOn();
        $this->assertInstanceOf(PrivateChannel::class, $channels[0]);
        $this->assertSame('private-organizations.'.$organization->id, (string) $channels[0]);

        $this->assertSame('member.joined', $event->broadcastAs());
        $this->assertSame('Responder', $event->broadcastWith()['role']);
    }
}
