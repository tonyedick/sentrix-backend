<?php

declare(strict_types=1);

namespace App\Domains\Notification\Listeners;

use App\Domains\Emergency\Events\EmergencyTriggered;
use App\Domains\Identity\Models\SafetyContact;
use App\Domains\Notification\Notifications\SafetyContactEmergencyNotification;
use App\Domains\Shared\Listeners\QueuedListener;
use App\Models\User;
use Illuminate\Support\Facades\Notification;

/**
 * When a consumer raises an emergency (directly via SOS, or transitively when a
 * trip goes overdue / loses contact), alert their trusted safety contacts by
 * SMS (+ email). Mirrors NotifyRespondersOfEmergency, but targets the user's
 * personal circle rather than org responders. Idempotency: delivery is at-least
 * -once; contacts may receive a duplicate on retry, which is acceptable for a
 * life-safety alert.
 */
final class NotifySafetyContactsOfEmergency extends QueuedListener
{
    public function handle(EmergencyTriggered $event): void
    {
        $emergency = $event->record;
        $userId = $emergency->getAttribute('user_id');
        if ($userId === null) {
            return; // operational (non-consumer) emergency — no personal contacts
        }

        $contacts = SafetyContact::query()->where('user_id', $userId)->get();
        if ($contacts->isEmpty()) {
            return;
        }

        $name = User::query()->whereKey($userId)->value('name') ?? 'A Sentrix user';
        $lat = $emergency->getAttribute('lat');
        $lng = $emergency->getAttribute('lng');

        $notification = new SafetyContactEmergencyNotification(
            (string) $name,
            $lat !== null ? (float) $lat : null,
            $lng !== null ? (float) $lng : null,
        );

        foreach ($contacts as $contact) {
            $route = Notification::route('sms', $contact->phone);
            if ($contact->email !== null && $contact->email !== '') {
                $route->route('mail', $contact->email);
            }
            $route->notify($notification);
        }
    }
}
