<?php

declare(strict_types=1);

namespace App\Domains\Assignment\Notifications;

use App\Domains\Notification\Notifications\OperationalNotification;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * Pages a dispatcher that an assignment's dispatch could not be filled and needs
 * manual intervention.
 */
final class AssignmentEscalatedNotification extends OperationalNotification
{
    public function __construct(
        private readonly string $assignmentId,
        private readonly ?string $incidentId,
        private readonly string $reason,
        private readonly int $level,
    ) {}

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->error()
            ->subject('Assignment dispatch escalated — intervention needed')
            ->line('An assignment could not be filled automatically and needs attention.')
            ->line('Reason: '.$this->reason)
            ->line('Escalation level: '.$this->level);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'assignment.dispatch_escalated',
            'assignment_id' => $this->assignmentId,
            'incident_id' => $this->incidentId,
            'reason' => $this->reason,
            'level' => $this->level,
        ];
    }
}
