<?php

declare(strict_types=1);

namespace App\Domains\Audit\Listeners;

use App\Domains\Audit\Contracts\Auditable;
use App\Domains\Audit\Services\AuditLogger;

/**
 * Wildcard event listener: records an audit row for every dispatched event that
 * implements {@see Auditable}. Registered against '*' in AuditServiceProvider so
 * any domain can opt into the trail purely by implementing the contract on its
 * event — no per-event wiring, and no Audit-domain dependency on other domains.
 */
final class RecordAuditTrail
{
    public function __construct(private readonly AuditLogger $logger) {}

    /**
     * @param  array<int, mixed>  $payload
     */
    public function handle(string $eventName, array $payload): void
    {
        $event = $payload[0] ?? null;

        if ($event instanceof Auditable) {
            $this->logger->record($event);
        }
    }
}
