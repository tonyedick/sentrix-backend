<?php

declare(strict_types=1);

namespace App\Domains\Notification\Notifications;

use App\Domains\Emergency\Models\Emergency;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * Alerts a responder that an emergency was raised in their organization.
 */
final class EmergencyTriggeredNotification extends OperationalNotification
{
    public function __construct(private readonly Emergency $emergency) {}

    public function toMail(object $notifiable): MailMessage
    {
        $base = rtrim((string) config('app.frontend_url'), '/');

        $mail = (new MailMessage)
            ->error()
            ->subject('Emergency triggered — action required')
            ->line('An emergency has been triggered in your organization.')
            ->line('Severity: '.ucfirst($this->emergency->severity->value));

        if ($this->emergency->message !== null && $this->emergency->message !== '') {
            $mail->line('Details: '.$this->emergency->message);
        }

        return $mail
            ->action('View emergency', "{$base}/emergencies/{$this->emergency->getKey()}")
            ->line('Please acknowledge it as soon as possible.');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'emergency.triggered',
            'emergency_id' => $this->emergency->getKey(),
            'organization_id' => $this->emergency->organization_id,
            'severity' => $this->emergency->severity->value,
            'message' => $this->emergency->message,
            'triggered_at' => $this->emergency->triggered_at?->toIso8601String(),
        ];
    }
}
