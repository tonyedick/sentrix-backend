<?php

declare(strict_types=1);

namespace App\Domains\Organization\Notifications;

use App\Domains\Organization\Models\OrganizationInvitation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Throwable;

final class OrganizationInvitationNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /** Bounded retries against transient mail-transport failures. */
    public int $tries = 5;

    public int $maxExceptions = 3;

    public int $timeout = 30;

    public function __construct(private readonly OrganizationInvitation $invitation) {}

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [30, 120, 300];
    }

    public function failed(Throwable $exception): void
    {
        Log::error('OrganizationInvitationNotification failed to send', [
            'invitation_id' => $this->invitation->getKey(),
            'email' => $this->invitation->email,
            'exception' => $exception->getMessage(),
        ]);
    }

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $base = rtrim((string) config('app.frontend_url', config('app.url')), '/');
        $url = "{$base}/invitations/{$this->invitation->token}/accept";

        return (new MailMessage)
            ->subject("You've been invited to join {$this->invitation->organization->name}")
            ->line("You've been invited to join {$this->invitation->organization->name} as {$this->invitation->role}.")
            ->action('Accept invitation', $url)
            ->line('This invitation expires on '.$this->invitation->expires_at?->toFormattedDateString().'.');
    }
}
