<?php

declare(strict_types=1);

namespace App\Domains\Identity\Listeners;

use App\Domains\Identity\Events\UserRegistered;
use App\Domains\Shared\Listeners\QueuedListener;

/**
 * Sends the email-verification link to a freshly registered user. Queued so the
 * registration response stays fast (queue-first design).
 *
 * Idempotent: no-ops once the address is verified, so a retry never re-sends to
 * an already-verified user.
 */
final class SendEmailVerificationLink extends QueuedListener
{
    public function handle(UserRegistered $event): void
    {
        if (! $event->user->hasVerifiedEmail()) {
            $event->user->sendEmailVerificationNotification();
        }
    }
}
