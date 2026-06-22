<?php

declare(strict_types=1);

namespace App\Domains\Webhooks\Listeners;

use App\Domains\Shared\Events\OrganizationRecordEvent;
use App\Domains\Webhooks\Jobs\DeliverWebhook;
use App\Domains\Webhooks\Models\Webhook;
use Illuminate\Support\Str;

/**
 * Fans an organization-scoped operational event out to that org's subscribed
 * webhook endpoints. A lightweight SYNCHRONOUS listener: it only resolves the
 * matching active webhooks and enqueues one {@see DeliverWebhook} job per
 * endpoint — all HTTP work happens off-request in those queued jobs.
 */
final class DispatchWebhooksForEvent
{
    public function handle(OrganizationRecordEvent $event): void
    {
        $organizationId = $event->organizationId();

        // Postgres: never compare a uuid column to a non-uuid string.
        if (! Str::isUuid($organizationId)) {
            return;
        }

        $eventKey = $event->action();
        $payload = $this->buildPayload($event, $eventKey, $organizationId);

        Webhook::query()
            ->where('organization_id', $organizationId)
            ->where('active', true)
            ->whereJsonContains('events', $eventKey)
            ->get()
            ->each(static function (Webhook $webhook) use ($eventKey, $payload): void {
                DeliverWebhook::dispatch((string) $webhook->getKey(), $eventKey, $payload);
            });
    }

    /**
     * Compact, signed delivery body.
     *
     * @return array<string, mixed>
     */
    private function buildPayload(OrganizationRecordEvent $event, string $eventKey, string $organizationId): array
    {
        return [
            'event' => $eventKey,
            'organization_id' => $organizationId,
            'occurred_at' => now()->toIso8601String(),
            'data' => $event->broadcastWith(),
        ];
    }
}
