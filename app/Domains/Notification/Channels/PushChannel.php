<?php

declare(strict_types=1);

namespace App\Domains\Notification\Channels;

use App\Domains\Notification\Contracts\PushProvider;
use Illuminate\Notifications\Notification;

/**
 * Custom notification channel delivering via the configured PushProvider. Device
 * tokens come from the notifiable's routeNotificationForPush(); content from the
 * notification's toPush() (['title','body','data']).
 */
final class PushChannel
{
    public function __construct(private readonly PushProvider $provider) {}

    public function send(object $notifiable, Notification $notification): void
    {
        if (! method_exists($notification, 'toPush')) {
            return;
        }

        $tokens = (array) $notifiable->routeNotificationFor('push', $notification);

        if ($tokens === []) {
            return; // No registered devices.
        }

        /** @var array<string, mixed> $payload */
        $payload = $notification->toPush($notifiable);

        $this->provider->send(
            array_values($tokens),
            (string) ($payload['title'] ?? ''),
            (string) ($payload['body'] ?? ''),
            (array) ($payload['data'] ?? []),
        );
    }
}
