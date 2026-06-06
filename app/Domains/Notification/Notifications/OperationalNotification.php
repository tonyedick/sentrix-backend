<?php

declare(strict_types=1);

namespace App\Domains\Notification\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Base for operational responder notifications. Queued and resilient (bounded
 * retries + failure logging), and channel selection is driven by config so
 * operators can enable/disable channels without code changes.
 *
 * Concrete notifications provide the payload ({@see toArray()}) and the email
 * body ({@see toMail()}); the broadcast channel reuses the array payload.
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
     * Channels are the intersection of the configured set and those this
     * notification actually implements — so an unimplemented channel in config
     * is ignored rather than throwing at delivery time.
     *
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        $configured = (array) config('sentrix.notifications.channels', ['mail', 'database', 'broadcast']);

        return array_values(array_intersect($configured, $this->supportedChannels()));
    }

    /**
     * @return list<string>
     */
    protected function supportedChannels(): array
    {
        return ['mail', 'database', 'broadcast'];
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
}
