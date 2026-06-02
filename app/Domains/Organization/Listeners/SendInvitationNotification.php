<?php

declare(strict_types=1);

namespace App\Domains\Organization\Listeners;

use App\Domains\Organization\Events\MemberInvited;
use App\Domains\Organization\Notifications\OrganizationInvitationNotification;
use Illuminate\Support\Facades\Notification;

final class SendInvitationNotification
{
    /**
     * Send the (queued) invitation email to the invitee's address. Decoupled
     * from InvitationService via the MemberInvited event.
     */
    public function handle(MemberInvited $event): void
    {
        Notification::route('mail', $event->invitation->email)
            ->notify(new OrganizationInvitationNotification($event->invitation));
    }
}
