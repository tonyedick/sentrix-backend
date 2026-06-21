<?php

declare(strict_types=1);

namespace App\Domains\Notification\Notifications;

use App\Domains\Notification\Channels\PushChannel;
use App\Domains\Notification\Channels\SmsChannel;
use App\Domains\Notification\Services\NotificationPolicyResolver;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Base for operational notifications. Queued and resilient (bounded retries +
 * failure logging). Channel selection is the recipient organization's
 * notification policy (falling back to config), intersected with the channels
 * this notification actually implements:
 *
 *   - database / broadcast: always available (use toArray()).
 *   - mail / sms / push: available when the concrete notification implements
 *     toMail() / toSms() / toPush().
 *
 * All deliveries run on the dedicated `notifications` Horizon queue (viaQueues);
 * outcomes are recorded per channel by RecordNotificationDelivery.
 */
abstract class OperationalNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public int $maxExceptions = 3;

    public int $timeout = 30;

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [10, 30, 60];
    }

    /**
     * The organization this notification concerns, used to resolve the channel
     * policy. Concrete notifications override when they relate to an organization.
     */
    public function organizationId(): ?string
    {
        return null;
    }

    /**
     * Delivery channels = (org policy ∩ what this notification supports), mapped
     * to the identifiers Laravel expects (custom channels referenced by class).
     *
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        $channels = array_values(array_intersect($this->enabledChannels(), $this->supportedChannels()));

        return array_map(static fn (string $channel): string => match ($channel) {
            'sms' => SmsChannel::class,
            'push' => PushChannel::class,
            default => $channel,
        }, $channels);
    }

    /**
     * Run every channel on the dedicated notifications queue (Horizon).
     *
     * @return array<string, string>
     */
    public function viaQueues(): array
    {
        $queue = (string) config('sentrix.notifications.queue', 'notifications');

        return [
            'mail' => $queue,
            'database' => $queue,
            'broadcast' => $queue,
            SmsChannel::class => $queue,
            PushChannel::class => $queue,
        ];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toArray($notifiable));
    }

    /**
     * @return array<string, mixed>
     */
    abstract public function toArray(object $notifiable): array;

    public function failed(Throwable $exception): void
    {
        Log::error(static::class.' failed to deliver', [
            'notification' => static::class,
            'exception' => $exception->getMessage(),
        ]);
    }

    /**
     * @return list<string>
     */
    private function enabledChannels(): array
    {
        $organizationId = $this->organizationId();

        if ($organizationId !== null) {
            return app(NotificationPolicyResolver::class)->for($organizationId)->enabledChannels();
        }

        return (array) config('sentrix.notifications.channels', ['mail', 'database', 'broadcast']);
    }

    /**
     * @return list<string>
     */
    private function supportedChannels(): array
    {
        $supported = ['database', 'broadcast'];

        if (method_exists($this, 'toMail')) {
            $supported[] = 'mail';
        }
        if (method_exists($this, 'toSms')) {
            $supported[] = 'sms';
        }
        if (method_exists($this, 'toPush')) {
            $supported[] = 'push';
        }

        return $supported;
    }
}
