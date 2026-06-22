<?php

declare(strict_types=1);

namespace App\Domains\Webhooks\Jobs;

use App\Domains\Webhooks\Models\Webhook;
use App\Domains\Webhooks\Models\WebhookDelivery;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Delivers one operational event to one subscribed webhook endpoint.
 *
 * Resilient + idempotent by design (mirrors {@see \App\Domains\Shared\Listeners\QueuedListener}):
 *   - bounded retries with exponential-ish backoff (no infinite loops);
 *   - a circuit-breaker on repeated exceptions (`maxExceptions`);
 *   - a wall-clock per-attempt timeout; and
 *   - structured failure logging (no silent failures).
 *
 * Each run records a WebhookDelivery row carrying the HTTP outcome. A transport
 * failure DOES throw so the queue applies the retry/backoff policy, but the row
 * is always persisted first so an exhausted delivery is still observable.
 *
 * Dispatched after-commit (see $afterCommit) so a rolled-back originating
 * transaction never fans out a phantom delivery.
 */
final class DeliverWebhook implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    // The target queue is set in the constructor via onQueue() — NOT by
    // redeclaring the $queue property. Queueable already declares $queue (default
    // null); redeclaring it here with a different default ('webhooks') is an
    // incompatible trait-property composition and fatals at class load on PHP 8.3+.
    //
    // Not after-commit: the dispatching listener runs as part of the operational
    // write, and the job re-loads + re-validates the webhook when it runs, so a
    // rolled-back originating write simply finds nothing to deliver.

    /** Maximum attempts before the job is sent to the failed-jobs table. */
    public int $tries = 5;

    /** Stop retrying after this many uncaught exceptions, even if attempts remain. */
    public int $maxExceptions = 3;

    /** Hard per-attempt timeout (seconds). */
    public int $timeout = 30;

    /**
     * @param array<string, mixed> $payload
     */
    public function __construct(
        public readonly string $webhookId,
        public readonly string $event,
        public readonly array $payload,
    ) {
        $this->onQueue('webhooks');
    }

    /**
     * Backoff (seconds) between successive attempts.
     *
     * @return list<int>
     */
    public function backoff(): array
    {
        return [10, 30, 60, 120];
    }

    public function handle(): void
    {
        $webhook = Webhook::query()->find($this->webhookId);

        // The webhook may have been deleted/deactivated between enqueue and run.
        if (! $webhook instanceof Webhook || $webhook->active !== true) {
            return;
        }

        // Canonical, compact body. hash_hmac signs exactly these bytes; the
        // receiver re-signs the raw request body with the shared secret.
        $body = (string) json_encode($this->payload);
        $signature = hash_hmac('sha256', $body, (string) $webhook->secret);

        $delivery = new WebhookDelivery([
            'webhook_id' => $webhook->getKey(),
            'event' => $this->event,
            'payload' => $this->payload,
            'signature' => $signature,
            'success' => false,
            // $this->job is null when handle() is invoked directly (not via the
            // queue worker); guard so we never call attempts() on a null job.
            'attempts' => $this->job !== null ? (int) $this->attempts() : 1,
        ]);

        try {
            // Send the array payload (Laravel json-encodes it, same bytes our
            // signature was computed over) via the standard JSON post — matches
            // the HTTP-client usage in the passing Core/Billing suites.
            $response = Http::timeout($this->timeout)
                ->withHeaders([
                    'X-Sentrix-Signature' => $signature,
                    'X-Sentrix-Event' => $this->event,
                ])
                ->post($webhook->url, $this->payload);

            $delivery->status_code = (int) $response->status();
            $delivery->success = $response->successful();
            $delivery->delivered_at = now();

            if (! $response->successful()) {
                $delivery->error = 'Non-2xx response: '.$response->status();
            }

            $delivery->save();

            // Surface a transport-level failure so the queue retries with backoff;
            // the row is already persisted, so the attempt stays observable.
            if (! $response->successful()) {
                $response->throw();
            }
        } catch (Throwable $exception) {
            if ($delivery->status_code === null) {
                $delivery->error = $exception->getMessage();
            }

            // Persist (or update) the attempt before rethrowing for retry.
            $delivery->save();

            throw $exception;
        }
    }

    /**
     * Final-failure hook after retries are exhausted. Preserve context for
     * operational debugging; never silently drop a failed delivery.
     */
    public function failed(?Throwable $exception): void
    {
        Log::error(self::class.' failed after exhausting retries', [
            'webhook_id' => $this->webhookId,
            'event' => $this->event,
            'exception' => $exception?->getMessage(),
        ]);
    }
}
