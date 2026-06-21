<?php

declare(strict_types=1);

namespace App\Domains\Notification\Listeners;

use App\Domains\Notification\Channels\PushChannel;
use App\Domains\Notification\Channels\SmsChannel;
use App\Domains\Notification\Models\NotificationDelivery;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Events\NotificationFailed;
use Illuminate\Notifications\Events\NotificationSending;
use Illuminate\Notifications\Events\NotificationSent;
use Throwable;

/**
 * Records per-channel delivery outcomes by listening to the framework's
 * notification events — so every channel (mail, database, broadcast, sms, push)
 * is tracked uniformly without each channel having to record itself.
 *
 *   NotificationSending → upsert pending, increment attempts
 *   NotificationSent    → mark sent
 *   NotificationFailed  → mark failed + capture the error
 *
 * Correlation key is (notification id, channel); retries reuse the same row, so
 * `attempts` reflects the true number of delivery attempts.
 */
final class RecordNotificationDelivery
{
    public function sending(NotificationSending $event): void
    {
        $delivery = $this->locate($event->notifiable, $event->notification, $event->channel);
        $delivery->attempts = ($delivery->attempts ?? 0) + 1;

        if ($delivery->status !== NotificationDelivery::STATUS_SENT) {
            $delivery->status = NotificationDelivery::STATUS_PENDING;
        }

        $delivery->save();
    }

    public function sent(NotificationSent $event): void
    {
        $delivery = $this->locate($event->notifiable, $event->notification, $event->channel);
        $delivery->status = NotificationDelivery::STATUS_SENT;
        $delivery->sent_at = now();
        $delivery->save();
    }

    public function failed(NotificationFailed $event): void
    {
        $delivery = $this->locate($event->notifiable, $event->notification, $event->channel);
        $delivery->status = NotificationDelivery::STATUS_FAILED;
        $delivery->error = $this->stringifyError($event->data);
        $delivery->save();
    }

    private function locate(object $notifiable, object $notification, string $channel): NotificationDelivery
    {
        $organizationId = method_exists($notification, 'organizationId')
            ? $notification->organizationId()
            : null;

        return NotificationDelivery::firstOrNew([
            'notification_id' => $notification->id,
            'channel' => $this->channelName($channel),
        ])->fill([
            'notification_type' => $notification::class,
            'organization_id' => $organizationId,
            'notifiable_type' => $notifiable instanceof Model ? $notifiable->getMorphClass() : null,
            'notifiable_id' => $notifiable instanceof Model ? $notifiable->getKey() : null,
        ]);
    }

    private function channelName(string $channel): string
    {
        return match ($channel) {
            SmsChannel::class => 'sms',
            PushChannel::class => 'push',
            default => $channel,
        };
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function stringifyError(array $data): ?string
    {
        $exception = $data['exception'] ?? null;

        if ($exception instanceof Throwable) {
            return $exception->getMessage();
        }

        return json_encode($data) ?: null;
    }
}
