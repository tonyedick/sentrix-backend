<?php

declare(strict_types=1);

namespace App\Domains\Responder\Notifications;

use App\Domains\Notification\Notifications\OperationalNotification;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * Reminds a responder that one of their certifications is approaching expiry.
 */
final class CertificationExpiringNotification extends OperationalNotification
{
    public function __construct(
        private readonly string $certificationName,
        private readonly ?string $expiresAt,
    ) {}

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Certification expiring soon')
            ->line("Your certification \"{$this->certificationName}\" is approaching its expiry date.")
            ->line($this->expiresAt !== null ? "Expires: {$this->expiresAt}" : 'Please review your certifications.')
            ->line('Renew it to keep your responder capabilities active.');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'responder.certification_expiring',
            'certification' => $this->certificationName,
            'expires_at' => $this->expiresAt,
        ];
    }
}
