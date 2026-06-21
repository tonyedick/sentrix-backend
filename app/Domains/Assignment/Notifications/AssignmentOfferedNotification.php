<?php

declare(strict_types=1);

namespace App\Domains\Assignment\Notifications;

use App\Domains\Notification\Notifications\OperationalNotification;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * Pages a responder that they have been dispatched and must accept or decline.
 */
final class AssignmentOfferedNotification extends OperationalNotification
{
    public function __construct(
        private readonly string $assignmentResponderId,
        private readonly string $role,
        private readonly ?string $incidentId,
        private readonly ?string $organizationId = null,
    ) {}

    public function organizationId(): ?string
    {
        return $this->organizationId;
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('You have been dispatched')
            ->line("You have been assigned as the {$this->role} responder. Please accept or decline.")
            ->line('Assignment line: '.$this->assignmentResponderId);
    }

    public function toSms(object $notifiable): string
    {
        return "Sentrix: you have been dispatched as {$this->role} responder. Open the app to accept or decline.";
    }

    /**
     * @return array{title: string, body: string, data: array<string, mixed>}
     */
    public function toPush(object $notifiable): array
    {
        return [
            'title' => 'New dispatch',
            'body' => "You have been assigned as the {$this->role} responder.",
            'data' => [
                'type' => 'assignment.responder_offered',
                'assignment_responder_id' => $this->assignmentResponderId,
                'incident_id' => $this->incidentId,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'assignment.responder_offered',
            'assignment_responder_id' => $this->assignmentResponderId,
            'role' => $this->role,
            'incident_id' => $this->incidentId,
        ];
    }
}
