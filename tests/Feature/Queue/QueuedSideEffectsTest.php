<?php

declare(strict_types=1);

namespace Tests\Feature\Queue;

use App\Domains\Identity\Listeners\SendEmailVerificationLink;
use App\Domains\Organization\Listeners\CreateDefaultOrganization;
use App\Domains\Shared\Listeners\QueuedListener;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Proves registration side effects are deferred to the queue (queue-first
 * design) rather than blocking the request.
 */
final class QueuedSideEffectsTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_defers_workspace_provisioning_to_the_queue(): void
    {
        Queue::fake();

        $this->postJson('/api/v1/auth/register', [
            'name' => 'Ada Lovelace',
            'email' => 'ada@example.com',
            'password' => 'secret-password-1',
            'password_confirmation' => 'secret-password-1',
        ])->assertCreated();

        $user = User::where('email', 'ada@example.com')->firstOrFail();

        // Provisioning is a queued listener: with the queue faked it does NOT run
        // inline, so no workspace exists yet. (Were it synchronous, the listener
        // would have created one during the request.) This is the observable proof
        // of queue-first deferral.
        $this->assertDatabaseMissing('organizations', ['owner_id' => $user->id]);
        $this->assertSame(0, $user->organizations()->count());
    }

    public function test_registration_listeners_share_the_resilience_policy(): void
    {
        $this->assertInstanceOf(QueuedListener::class, app(CreateDefaultOrganization::class));
        $this->assertInstanceOf(QueuedListener::class, app(SendEmailVerificationLink::class));

        $listener = app(SendEmailVerificationLink::class);
        $this->assertSame(5, $listener->tries);
        $this->assertNotEmpty($listener->backoff());
    }
}
