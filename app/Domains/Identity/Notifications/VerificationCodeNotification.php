<?php

declare(strict_types=1);

namespace App\Domains\Identity\Notifications;

use App\Domains\Notification\Channels\SmsChannel;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Delivers a one-time verification code over the requested channel: email (mail)
 * or phone (SMS via the configured provider). The code is passed in plaintext
 * here only for delivery — it is stored hashed by the OtpService.
 */
final class VerificationCodeNotification extends Notification
{
    public function __construct(
        public readonly string $code,
        public readonly string $channel,
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return $this->channel === 'phone' ? [SmsChannel::class] : ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Your Sentrix verification code')
            ->greeting('Verify your account')
            ->line('Your verification code is:')
            ->line($this->code)
            ->line('This code expires shortly. If you did not request it, you can ignore this email.');
    }

    public function toSms(object $notifiable): string
    {
        return "Your Sentrix verification code is {$this->code}. It expires shortly.";
    }
}
