<?php

declare(strict_types=1);

namespace App\Domains\Notification\Notifications;

use App\Domains\Incident\Models\Incident;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * Pages a coordinator that an incident has been escalated.
 */
final class IncidentEscalatedNotification extends OperationalNotification
{
    public function __construct(private readonly Incident $incident) {}

    public function toMail(object $notifiable): MailMessage
    {
        $base = rtrim((string) config('app.frontend_url'), '/');

        return (new MailMessage)
            ->error()
            ->subject('Incident escalated — attention required')
            ->line('An incident has been escalated in your organization.')
            ->line('Title: '.$this->incident->title)
            ->line('Severity: '.ucfirst($this->incident->severity->value))
            ->action('View incident', "{$base}/incidents/{$this->incident->getKey()}");
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'incident.escalated',
            'incident_id' => $this->incident->getKey(),
            'organization_id' => $this->incident->organization_id,
            'severity' => $this->incident->severity->value,
            'title' => $this->incident->title,
            'escalated_at' => $this->incident->escalated_at?->toIso8601String(),
        ];
    }
}
