<?php

declare(strict_types=1);

namespace App\Domains\Notification\Notifications;

use App\Domains\Notification\Channels\SmsChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Alerts a user's safety contact that the user has raised an emergency (or a
 * trip went overdue → emergency). Delivered by SMS (always) and email (when the
 * contact has one). Sent as an on-demand notification to the contact's
 * phone/email — contacts are not Sentrix users.
 */
final class SafetyContactEmergencyNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $userName,
        public readonly ?float $lat,
        public readonly ?float $lng,
    ) {
        $this->onQueue('notifications');
    }

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        $channels = [SmsChannel::class];
        if ($notifiable->routeNotificationFor('mail')) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    public function toSms(object $notifiable): string
    {
        $where = $this->lat !== null && $this->lng !== null
            ? " Last location: https://maps.google.com/?q={$this->lat},{$this->lng}"
            : '';

        return "SENTRIX SOS: {$this->userName} has triggered an emergency and listed you as a safety contact.{$where}";
    }

    public function toMail(object $notifiable): MailMessage
    {
        $message = (new MailMessage)
            ->subject('Sentrix SOS — '.$this->userName.' needs help')
            ->line($this->userName.' has triggered an emergency in Sentrix and listed you as a trusted safety contact.');

        if ($this->lat !== null && $this->lng !== null) {
            $message->action('View last location', "https://maps.google.com/?q={$this->lat},{$this->lng}");
        }

        return $message->line('If you cannot reach them, contact local emergency services (112).');
    }
}
