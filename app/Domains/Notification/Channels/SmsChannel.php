<?php

declare(strict_types=1);

namespace App\Domains\Notification\Channels;

use App\Domains\Notification\Contracts\SmsProvider;
use Illuminate\Notifications\Notification;

/**
 * Custom notification channel delivering via the configured SmsProvider. The
 * destination comes from the notifiable's routeNotificationForSms(); messages
 * come from the notification's toSms(). A throwing provider surfaces as a failed
 * delivery (recorded + retried).
 */
final class SmsChannel
{
    public function __construct(private readonly SmsProvider $provider) {}

    public function send(object $notifiable, Notification $notification): void
    {
        if (! method_exists($notification, 'toSms')) {
            return;
        }

        $to = $notifiable->routeNotificationFor('sms', $notification);

        if (empty($to)) {
            return; // No phone on file — nothing to deliver.
        }

        $this->provider->send((string) $to, (string) $notification->toSms($notifiable));
    }
}
