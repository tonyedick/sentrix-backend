<?php

declare(strict_types=1);

namespace App\Domains\Notification\Contracts;

/**
 * Provider abstraction for outbound push notifications. Implementations wrap a
 * concrete gateway (FCM, APNs, …); selected by config for provider switching.
 * Should throw on delivery failure so the framework records + retries.
 */
interface PushProvider
{
    /**
     * @param  list<string>  $tokens  device tokens
     * @param  array<string, mixed>  $data  data payload
     */
    public function send(array $tokens, string $title, string $body, array $data = []): void;

    /** Provider identifier, for logging/auditing. */
    public function name(): string;
}
