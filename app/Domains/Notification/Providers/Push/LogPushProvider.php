<?php

declare(strict_types=1);

namespace App\Domains\Notification\Providers\Push;

use App\Domains\Notification\Contracts\PushProvider;
use Illuminate\Support\Facades\Log;

/**
 * Default push provider: logs instead of calling FCM/APNs. Swap via
 * sentrix.notifications.push.provider — no other code changes needed.
 */
final class LogPushProvider implements PushProvider
{
    public function send(array $tokens, string $title, string $body, array $data = []): void
    {
        Log::info('push.dispatch', [
            'tokens' => $tokens,
            'title' => $title,
            'body' => $body,
            'data' => $data,
            'provider' => $this->name(),
        ]);
    }

    public function name(): string
    {
        return 'log';
    }
}
