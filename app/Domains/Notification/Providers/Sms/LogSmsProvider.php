<?php

declare(strict_types=1);

namespace App\Domains\Notification\Providers\Sms;

use App\Domains\Notification\Contracts\SmsProvider;
use Illuminate\Support\Facades\Log;

/**
 * Default SMS provider: logs instead of calling an external gateway. Safe for
 * local/dev/test and as a fallback. Swap to a real provider (e.g. Twilio/Vonage)
 * via sentrix.notifications.sms.provider — no other code changes needed.
 */
final class LogSmsProvider implements SmsProvider
{
    public function send(string $to, string $message): void
    {
        Log::info('sms.dispatch', ['to' => $to, 'message' => $message, 'provider' => $this->name()]);
    }

    public function name(): string
    {
        return 'log';
    }
}
