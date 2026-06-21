<?php

declare(strict_types=1);

namespace App\Domains\Notification\Contracts;

/**
 * Provider abstraction for outbound SMS. Implementations wrap a concrete gateway
 * (Twilio, Vonage, …); the binding is selected by config so the provider can be
 * switched without touching channels or notifications. Implementations should
 * throw on delivery failure so the framework records a failed attempt and retries.
 */
interface SmsProvider
{
    public function send(string $to, string $message): void;

    /** Provider identifier, for logging/auditing. */
    public function name(): string;
}
